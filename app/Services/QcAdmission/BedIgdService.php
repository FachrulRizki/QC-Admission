<?php

namespace App\Services\QcAdmission;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class BedIgdService
{
    private const TOKEN_CACHE_KEY = 'bed_igd_api_token';

    // Public API
    public function getKodeBedByNoReg(string $noReg): ?string
    {
        if (! $noReg) return null;

        // via API
        if ($this->isApiEnabled()) {
            try {
                $token    = $this->getToken();
                $url      = config('services.bed_igd.base_url') . '/master-bed';

                Log::channel('api')->info('BedIgdService::getKodeBedByNoReg request', [
                    'url'    => $url,
                    'no_reg' => $noReg,
                ]);

                $response = Http::withToken($token)
                    ->timeout(5)
                    ->acceptJson()
                    ->get($url);

                $this->logApiResponse('getKodeBedByNoReg', 'GET', $url, $response);

                if ($response->successful()) {
                    $found = collect($response->json()['data'] ?? [])
                        ->first(fn($b) => ($b['BedIgd']['No_Reg'] ?? null) === $noReg
                            && strtoupper($b['BedIgd']['Status'] ?? '') === 'TERISI');

                    Log::channel('api')->info('BedIgdService::getKodeBedByNoReg result', [
                        'no_reg'   => $noReg,
                        'kode_bed' => $found ? ($found['Kode_Bed'] ?? null) : null,
                        'found'    => (bool) $found,
                    ]);

                    if ($found) {
                        return $found['Kode_Bed'] ?? null;
                    }
                    return null;
                }
            } catch (\Exception $e) {
                Log::channel('api')->warning('BedIgdService::getKodeBedByNoReg API failed', [
                    'error'  => $e->getMessage(),
                    'no_reg' => $noReg,
                ]);
            }
        }

        else if ($this->isRsusEnabled()) {
            try {
                $row = DB::connection('rsus')
                    ->table('BI_Bed_Igd')
                    ->where('No_Reg', $noReg)
                    ->where('Status', 'TERISI')
                    ->orderByDesc('updated_reg_at')
                    ->orderByDesc('Tanggal')
                    ->first(['Kode_Bed']);

                Log::channel('api')->info('BedIgdService::getKodeBedByNoReg RSUS result', [
                    'no_reg'   => $noReg,
                    'kode_bed' => $row?->Kode_Bed ?? null,
                    'source'   => 'rsus_db',
                ]);

                return $row?->Kode_Bed ?? null;
            } catch (\Exception $e) {
                Log::channel('api')->warning('BedIgdService::getKodeBedByNoReg RSUS failed', [
                    'error'  => $e->getMessage(),
                    'no_reg' => $noReg,
                ]);
            }
        }

        return null;
    }

    /**
     * Ambil bed yang sedang ditempati pasien berdasarkan No_Reg.
     * @return array{ beds: array, source: string }
     */
    public function getBedsByNoReg(string $noReg): array
    {
        if ($this->isApiEnabled()) {
            try {
                $token = $this->getToken();
                $url   = config('services.bed_igd.base_url') . '/master-bed';

                Log::channel('api')->info('BedIgdService::getBedsByNoReg request', [
                    'url'    => $url,
                    'no_reg' => $noReg,
                ]);

                $response = Http::withToken($token)
                    ->timeout(3)
                    ->acceptJson()
                    ->get($url);

                $this->logApiResponse('getBedsByNoReg', 'GET', $url, $response);

                if (! $response->successful()) {
                    Cache::forget(self::TOKEN_CACHE_KEY);
                    throw new \RuntimeException("GET /master-bed gagal: HTTP {$response->status()}");
                }

                $allBeds  = $response->json()['data'] ?? [];
                $filtered = collect($allBeds)->filter(
                    fn($b) => ($b['BedIgd']['No_Reg'] ?? null) === $noReg
                );

                Log::channel('api')->info('BedIgdService::getBedsByNoReg success', [
                    'no_reg'      => $noReg,
                    'total_beds'  => count($allBeds),
                    'found_count' => $filtered->count(),
                    'source'      => 'bed_igd_api',
                ]);

                return [
                    'beds'   => $filtered->values()->map(fn($b) => $this->normalizeBed($b))->toArray(),
                    'source' => 'bed_igd_api',
                ];
            } catch (\Exception $e) {
                // API timeout/tidak terjangkau — lanjut ke fallback RSUS DB
                Log::channel('api')->warning('BedIgdService::getBedsByNoReg API failed, falling back to RSUS DB', [
                    'error'  => $e->getMessage(),
                    'no_reg' => $noReg,
                ]);
            }
        }

        if ($this->isRsusEnabled()) {
            try {
                $rows = DB::connection('rsus')
                    ->table('BI_Bed_Igd')
                    ->where('No_Reg', $noReg)
                    ->orderByDesc('Tanggal')
                    ->limit(20)
                    ->get();

                Log::channel('api')->info('BedIgdService::getBedsByNoReg RSUS result', [
                    'no_reg'      => $noReg,
                    'found_count' => $rows->count(),
                    'source'      => 'rsus_db',
                ]);

                return [
                    'beds'   => $rows->map(fn($r) => $this->normalizeRow($r))->values()->toArray(),
                    'source' => 'rsus_db',
                ];
            } catch (\Exception $e) {
                Log::channel('api')->warning('BedIgdService::getBedsByNoReg RSUS failed', [
                    'error'  => $e->getMessage(),
                    'no_reg' => $noReg,
                ]);
            }
        }

        // Pasien mungkin menunggu di rumah dan memang tidak punya bed IGD.
        return ['beds' => [], 'source' => 'none'];
    }

    /**
     * Update status bed → KOSONG saat Batal Ranap dikonfirmasi Siap Closing.
     */
    public function releaseBed(string $kodeBed, string $noReg): array
    {
        if (! $kodeBed) {
            return ['success' => false, 'source' => 'none', 'message' => 'Kode_Bed tidak boleh kosong.'];
        }

        $updateMode = config('services.bed_igd.update_mode', 'direct');
        $updatePath = config('services.bed_igd.update_path', '/bed/release/trigger');
        $apiFailMessage = null;

        if ($this->isApiEnabled()) {
            try {
                $baseUrl  = config('services.bed_igd.base_url');
                $fullUrl  = rtrim($baseUrl, '/') . $updatePath;
                $payload  = [
                    'kode_bed' => $kodeBed,
                    'no_reg'   => $noReg,
                    'status'   => 'KOSONG',
                ];

                Log::channel('api')->info('BedIgdService::releaseBed request', [
                    'url'      => $fullUrl,
                    'kode_bed' => $kodeBed,
                    'no_reg'   => $noReg,
                    'payload'  => $payload,
                ]);

                // Kirim request dengan token — token dipilih sesuai BED_IGD_AUTH_MODE
                $token    = $this->getToken();
                $response = Http::withToken($token)
                    ->timeout(10)
                    ->acceptJson()
                    ->post($fullUrl, $payload);

                $this->logApiResponse('releaseBed', 'POST', $fullUrl, $response, $payload);

                if (! $response->successful()) {
                    $errBody = $response->body();
                    // Clear semua cache token agar retry dapat token segar
                    Cache::forget(self::TOKEN_CACHE_KEY);
                    $this->forgetClientToken();

                    // Jika 401 → token expired, coba sekali lagi dengan token baru
                    if ($response->status() === 401) {
                        Log::channel('api')->info('BedIgdService::releaseBed token 401, retry dengan token baru.');
                        $token    = $this->getToken();
                        $response = Http::withToken($token)->timeout(10)->acceptJson()->post($fullUrl, $payload);
                        $this->logApiResponse('releaseBed[retry]', 'POST', $fullUrl, $response, $payload);
                    }

                    if (! $response->successful()) {
                        Log::channel('api')->warning('BedIgdService::releaseBed API gagal setelah retry', [
                            'status'   => $response->status(),
                            'body'     => $response->body(),
                            'kode_bed' => $kodeBed,
                            'no_reg'   => $noReg,
                        ]);
                        throw new \RuntimeException(self::classifyApiError($response));
                    }
                }

                Log::channel('api')->info('BedIgdService::releaseBed success [API]', [
                    'kode_bed' => $kodeBed,
                    'no_reg'   => $noReg,
                    'response' => $response->json(),
                ]);

                return [
                    'success'  => true,
                    'source'   => 'bed_igd_api',
                    'kode_bed' => $kodeBed,
                    'data'     => $response->json(),
                ];
            } catch (\Exception $e) {
                // API gagal (timeout, connection refused, dll) — lanjut ke fallback RSUS DB
                Log::channel('api')->warning('BedIgdService::releaseBed API failed, falling back to RSUS DB', [
                    'error'    => $e->getMessage(),
                    'kode_bed' => $kodeBed,
                    'no_reg'   => $noReg,
                ]);
                $apiFailMessage = self::classifyConnectionError($e);
            }
        }

        if ($this->isRsusEnabled()) {
            try {
                $affected = DB::connection('rsus')->table('BI_Bed_Igd')
                    ->where('Kode_Bed', $kodeBed)
                    ->where('No_Reg',   $noReg)
                    ->update(['Status' => 'KOSONG', 'No_Reg' => null, 'update_at' => now()]);

                Log::channel('api')->info('BedIgdService::releaseBed success [RSUS DB]', [
                    'kode_bed' => $kodeBed,
                    'no_reg'   => $noReg,
                    'affected' => $affected,
                ]);
                return ['success' => true, 'source' => 'rsus_db', 'kode_bed' => $kodeBed];
            } catch (\Exception $e) {
                Log::channel('api')->warning('BedIgdService::releaseBed RSUS failed', [
                    'error'    => $e->getMessage(),
                    'kode_bed' => $kodeBed,
                    'no_reg'   => $noReg,
                ]);
                return ['success' => false, 'source' => 'rsus_db', 'message' => self::classifyDbError($e)];
            }
        }

        Log::channel('api')->info('BedIgdService::releaseBed mock', [
            'kode_bed' => $kodeBed,
            'no_reg'   => $noReg,
        ]);
        return ['success' => true, 'source' => 'mock', 'kode_bed' => $kodeBed];
    }

    // Token management
    private function getToken(): string
    {
        $authMode = config('services.bed_igd.auth_mode', 'login');

        return match ($authMode) {
            'passthrough' => $this->getPassthroughToken(),
            'keycloak'    => $this->getKeycloakClientToken(),
            'static'      => $this->getStaticToken(),
            default       => $this->getLoginToken(),
        };
    }

    /**
     * PASSTHROUGH — forward token Keycloak user yang sedang login.
     */
    private function getPassthroughToken(): string
    {
        $token = session('keycloak_access_token');

        if (! $token) {
            Log::warning('BedIgdService[passthrough]: keycloak_access_token tidak ada di session.');
            throw new \RuntimeException(
                'Token Keycloak user tidak ditemukan di session. ' .
                'Pastikan user login melalui SSO dan sesi masih aktif.'
            );
        }

        // Decode payload JWT tanpa verify signature — cukup untuk cek exp
        $payload = [];
        $parts   = explode('.', $token);
        if (count($parts) === 3) {
            $payload = json_decode(base64_decode(strtr($parts[1], '-_', '+/')), true) ?? [];
            $exp     = $payload['exp'] ?? 0;

            // Refresh jika token expired atau akan expired dalam 60 detik
            if ($exp > 0 && $exp < (time() + 60)) {
                Log::channel('api')->info('BedIgdService[passthrough]: token mendekati/sudah expired, coba refresh.', [
                    'exp'        => date('Y-m-d H:i:s', $exp),
                    'sisa_detik' => $exp - time(),
                ]);
                $token   = $this->tryRefreshKeycloakToken($token);
                // Re-decode payload dari token baru
                $parts2  = explode('.', $token);
                if (count($parts2) === 3) {
                    $payload = json_decode(base64_decode(strtr($parts2[1], '-_', '+/')), true) ?? [];
                }
            }
        }

        Log::channel('api')->info('BedIgdService[passthrough]: menggunakan token Keycloak user dari session.', [
            'sub'      => $payload['sub']                ?? '?',
            'username' => $payload['preferred_username'] ?? '?',
            'exp'      => isset($payload['exp'])
                ? date('Y-m-d H:i:s', $payload['exp'])
                : '?',
        ]);

        return $token;
    }

    /**
     * Coba refresh access token menggunakan refresh_token dari session.
     * Jika berhasil, update session dan kembalikan token baru.
     */
    private function tryRefreshKeycloakToken(string $oldToken): string
    {
        $refreshToken = session('keycloak_refresh_token');

        if (! $refreshToken) {
            throw new \RuntimeException('Refresh token tidak ada di session. User perlu login ulang.');
        }

        $tokenUrl = config('services.keycloak.base_url')
            . '/realms/' . config('services.keycloak.realm')
            . '/protocol/openid-connect/token';

        $response = Http::timeout(5)
            ->asForm()
            ->post($tokenUrl, [
                'grant_type'    => 'refresh_token',
                'client_id'     => config('services.keycloak.client_id'),
                'client_secret' => config('services.keycloak.client_secret'),
                'refresh_token' => $refreshToken,
            ]);

        if (! $response->successful()) {
            throw new \RuntimeException(
                'Refresh token Keycloak gagal: sesi user sudah tidak valid. User perlu login ulang.'
            );
        }

        $newAccessToken  = $response->json()['access_token']  ?? null;
        $newRefreshToken = $response->json()['refresh_token']  ?? $refreshToken;

        if (! $newAccessToken) {
            throw new \RuntimeException('Refresh token berhasil tapi access_token tidak ada di respons.');
        }

        // Update session dengan token baru
        session([
            'keycloak_access_token'  => $newAccessToken,
            'keycloak_refresh_token' => $newRefreshToken,
        ]);

        Log::channel('api')->info('BedIgdService[passthrough]: token berhasil di-refresh dari Keycloak.');

        return $newAccessToken;
    }

    /**
     * KEYCLOAK CLIENT CREDENTIALS
     */
    private function getKeycloakClientToken(): string
    {
        $cacheKey = self::TOKEN_CACHE_KEY . '_kc';

        // Hapus cache jika ada, coba ambil fresh
        if ($token = Cache::get($cacheKey)) {
            return $token;
        }

        $tokenUrl = config('services.keycloak.base_url')
            . '/realms/' . config('services.keycloak.realm')
            . '/protocol/openid-connect/token';

        $response = Http::timeout(10)
            ->asForm()
            ->post($tokenUrl, [
                'grant_type'    => 'client_credentials',
                'client_id'     => config('services.keycloak.client_id'),
                'client_secret' => config('services.keycloak.client_secret'),
            ]);

        if (! $response->successful()) {
            throw new \RuntimeException(
                "Bed IGD Keycloak client_credentials gagal: HTTP {$response->status()} — {$response->body()}"
            );
        }

        $body  = $response->json();
        $token = $body['access_token'] ?? null;

        if (! $token) {
            throw new \RuntimeException('Bed IGD Keycloak: access_token tidak ditemukan di respons.');
        }

        // Cache selama (expires_in - 60 detik) agar tidak terpakai saat hampir expired
        $ttl = max(30, (int) ($body['expires_in'] ?? 300) - 60);
        Cache::put($cacheKey, $token, now()->addSeconds($ttl));

        Log::channel('api')->info('BedIgdService[keycloak]: client_credentials token berhasil di-generate.', [
            'expires_in' => $body['expires_in'] ?? null,
            'cached_ttl' => $ttl,
        ]);

        return $token;
    }

    /**
     * Invalidate cached client credentials token (dipanggil saat API return 401).
     */
    private function forgetClientToken(): void
    {
        Cache::forget(self::TOKEN_CACHE_KEY . '_kc');
    }

    /**
     * LOGIN — POST /auth/login ke API Bed IGD dengan username/password.
     */
    private function getLoginToken(): string
    {
        if ($token = Cache::get(self::TOKEN_CACHE_KEY)) {
            return $token;
        }

        $response = Http::timeout(5)
            ->acceptJson()
            ->post(config('services.bed_igd.base_url') . '/auth/login', [
                'username' => config('services.bed_igd.username'),
                'password' => config('services.bed_igd.password'),
            ]);

        if (! $response->successful()) {
            throw new \RuntimeException(
                "Bed IGD login gagal: HTTP {$response->status()} — {$response->body()}"
            );
        }

        $body  = $response->json();
        $token = $body['token']
            ?? $body['access_token']
            ?? $body['data']['token']
            ?? $body['data']['access_token']
            ?? null;

        if (! $token) {
            throw new \RuntimeException('Bed IGD API: token tidak ditemukan di respons login.');
        }

        $ttl = (int) config('services.bed_igd.token_cache_minutes', 55);
        Cache::put(self::TOKEN_CACHE_KEY, $token, now()->addMinutes($ttl));
        return $token;
    }

    /**
     * STATIC — token tetap dari .env.
     */
    private function getStaticToken(): string
    {
        $token = config('services.bed_igd.static_token', '');
        if (! $token) {
            throw new \RuntimeException('BED_IGD_STATIC_TOKEN belum diset di .env');
        }
        return $token;
    }

    // Helpers───

    /**
     * Klasifikasikan exception database menjadi pesan yang informatif tapi aman untuk ditampilkan ke UI.
     * Detail teknis tetap ada di log, bukan di response.
     */
    private static function classifyDbError(\Exception $e): string
    {
        $msg = $e->getMessage();

        // Permission / hak akses
        if (str_contains($msg, 'permission was denied') || str_contains($msg, 'Permission denied')) {
            return 'Akun database tidak punya hak akses UPDATE pada tabel BI_Bed_Igd. Hubungi DBA untuk grant permission.';
        }

        // Login / auth DB gagal
        if (str_contains($msg, 'Login failed') || str_contains($msg, 'SQLSTATE[28000]')) {
            return 'Login ke database RSUS gagal — cek kredensial DB di konfigurasi server.';
        }

        // Timeout / koneksi
        if (str_contains($msg, 'timed out') || str_contains($msg, 'timeout') || str_contains($msg, 'Connection refused')) {
            return 'Koneksi ke database RSUS timeout atau ditolak — server DB mungkin tidak aktif.';
        }

        // Host tidak ditemukan
        if (str_contains($msg, 'Unable to open') || str_contains($msg, 'could not be resolved') || str_contains($msg, 'No such host')) {
            return 'Host database RSUS tidak dapat dijangkau — periksa konfigurasi IP/hostname.';
        }

        // Tabel/objek tidak ada
        if (str_contains($msg, 'Invalid object name') || str_contains($msg, "doesn't exist")) {
            return 'Tabel BI_Bed_Igd tidak ditemukan di database RSUS — periksa nama database/schema.';
        }

        // Deadlock
        if (str_contains($msg, 'deadlock') || str_contains($msg, 'Deadlock')) {
            return 'Terjadi deadlock pada tabel BI_Bed_Igd — coba ulangi beberapa saat lagi.';
        }

        // Fallback generik
        return 'Gagal update status bed di database RSUS. Detail error telah dicatat di log server.';
    }

    /**
     * Klasifikasikan error dari HTTP response API Bed IGD menjadi pesan yang informatif.
     */
    private static function classifyApiError(\Illuminate\Http\Client\Response $response): string
    {
        $status = $response->status();
        $body   = $response->json() ?? [];

        // Coba ambil pesan dari body API dulu
        $apiMsg = $body['message'] ?? $body['error'] ?? $body['msg'] ?? null;

        return match (true) {
            $status === 401  => 'Token autentikasi ditolak oleh API Bed IGD (401). Token sudah habis atau tidak valid.',
            $status === 403  => 'Akses ditolak oleh API Bed IGD (403) — akun tidak punya hak untuk release bed.',
            $status === 404  => 'Endpoint release bed tidak ditemukan di API Bed IGD (404) — periksa konfigurasi BED_IGD_UPDATE_PATH.',
            $status === 422  => 'API Bed IGD menolak data yang dikirim (422)' . ($apiMsg ? ": {$apiMsg}" : '.'),
            $status === 500  => 'API Bed IGD mengalami error internal (500)' . ($apiMsg ? ": {$apiMsg}" : '.'),
            $status >= 500   => "API Bed IGD error server ({$status})" . ($apiMsg ? ": {$apiMsg}" : '.'),
            $status >= 400   => "API Bed IGD menolak request ({$status})" . ($apiMsg ? ": {$apiMsg}" : '.'),
            default          => 'API Bed IGD mengembalikan response tidak terduga' . ($apiMsg ? ": {$apiMsg}" : '.'),
        };
    }

    /**
     * Log HTTP response dari API eksternal.
     * Hanya mencatat status, durasi, dan body ringkas — aman untuk production.
     */
    private function logApiResponse(
        string $caller,
        string $method,
        string $url,
        \Illuminate\Http\Client\Response $response,
        array $requestPayload = []
    ): void {
        $status     = $response->status();
        $isSuccess  = $response->successful();
        $body       = $response->body();

        // Batasi body log agar tidak membanjiri log file (maks 2000 karakter)
        $bodySnippet = mb_strlen($body) > 2000
            ? mb_substr($body, 0, 2000) . '… [truncated]'
            : $body;

        $context = [
            'caller'          => $caller,
            'method'          => $method,
            'url'             => $url,
            'http_status'     => $status,
            'success'         => $isSuccess,
            'response_body'   => $bodySnippet,
            'content_type'    => $response->header('Content-Type'),
        ];

        // Sertakan payload request (hilangkan field sensitif jika ada)
        if (! empty($requestPayload)) {
            $context['request_payload'] = $requestPayload;
        }

        if ($isSuccess) {
            Log::channel('api')->info("BedIgdService API response [{$method} {$url}]", $context);
        } else {
            Log::channel('api')->warning("BedIgdService API response gagal [{$method} {$url}]", $context);
        }
    }

    /**
     * Klasifikasikan exception koneksi/timeout dari HTTP client.
     */
    private static function classifyConnectionError(\Exception $e): string
    {
        $msg = $e->getMessage();

        if (str_contains($msg, 'timed out') || str_contains($msg, 'timeout')) {
            return 'API Bed IGD tidak merespons dalam batas waktu (timeout). Server API mungkin sedang sibuk atau tidak aktif.';
        }
        if (str_contains($msg, 'Connection refused') || str_contains($msg, 'Failed to connect')) {
            return 'Koneksi ke API Bed IGD ditolak — server API tidak aktif atau port salah.';
        }
        if (str_contains($msg, 'Could not resolve host') || str_contains($msg, 'cURL error 6')) {
            return 'Host API Bed IGD tidak dapat dijangkau — periksa konfigurasi BED_IGD_API_URL.';
        }
        // Pesan sudah di-classify sebelumnya (dari classifyApiError via RuntimeException)
        if (! str_contains($msg, 'SQLSTATE') && ! str_contains($msg, 'permission')) {
            return $msg;
        }
        return 'API Bed IGD tidak dapat dihubungi. Detail error telah dicatat di log server.';
    }

    private function isApiEnabled(): bool
    {
        return (bool) config('services.bed_igd.enabled', false)
            && ! empty(config('services.bed_igd.base_url'));
    }

    private function isRsusEnabled(): bool
    {
        return (bool) config('services.rsus_db_enabled', false);
    }

    private function normalizeBed(array $bed): array
    {
        $b = $bed['BedIgd'] ?? [];
        return [
            'kode_bed'       => $bed['Kode_Bed']     ?? null,
            'bed_id'         => $bed['Kode_Bed']     ?? null,
            'nama_bed'       => $bed['Nama_Bed']     ?? null,
            'ket_bed'        => $bed['Ket_Bed']      ?? null,
            'kode_triase'    => $bed['Kode_Triase']  ?? null,
            'kode_ruang'     => $bed['Kode_Ruang']   ?? null,
            'kode_bangsal'   => $bed['Kode_Bangsal'] ?? 'IGD',
            'bed_igd_id'     => $b['id']             ?? null,
            'status'         => strtoupper($b['Status']  ?? ''),
            'no_reg'         => $b['No_Reg']         ?? null,
            'tanggal'        => $b['Tanggal']        ?? null,
            'updated_reg_at' => $b['updated_reg_at'] ?? null,
        ];
    }

    private function normalizeRow(object $r): array
    {
        return [
            'kode_bed'       => $r->Kode_Bed       ?? null,
            'bed_id'         => $r->Kode_Bed       ?? null,
            'nama_bed'       => null,
            'ket_bed'        => null,
            'kode_triase'    => null,
            'kode_ruang'     => null,
            'kode_bangsal'   => 'IGD',
            'bed_igd_id'     => $r->id              ?? null,
            'status'         => strtoupper($r->Status ?? ''),
            'no_reg'         => $r->No_Reg          ?? null,
            'tanggal'        => $r->Tanggal         ?? null,
            'updated_reg_at' => $r->updated_reg_at  ?? null,
        ];
    }
}
