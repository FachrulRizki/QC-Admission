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
                $response = Http::withToken($token)
                    ->timeout(10)
                    ->acceptJson()
                    ->get(config('services.bed_igd.base_url') . '/master-bed');

                if ($response->successful()) {
                    $found = collect($response->json()['data'] ?? [])
                        ->first(fn($b) => ($b['BedIgd']['No_Reg'] ?? null) === $noReg
                            && strtoupper($b['BedIgd']['Status'] ?? '') === 'TERISI');

                    if ($found) {
                        return $found['Kode_Bed'] ?? null;
                    }
                }
            } catch (\Exception $e) {
                Log::warning('BedIgdService::getKodeBedByNoReg API failed', ['error' => $e->getMessage()]);
            }
        }

        // Coba via RSUS DB langsung
        if ($this->isRsusEnabled()) {
            try {
                $row = DB::connection('rsus')
                    ->table('BI_Bed_Igd')
                    ->where('No_Reg', $noReg)
                    ->where('Status', 'TERISI')
                    ->orderByDesc('updated_reg_at')
                    ->orderByDesc('Tanggal')
                    ->first(['Kode_Bed']);

                return $row?->Kode_Bed ?? null;
            } catch (\Exception $e) {
                Log::warning('BedIgdService::getKodeBedByNoReg RSUS failed', ['error' => $e->getMessage()]);
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
                $token    = $this->getToken();
                $response = Http::withToken($token)
                    ->timeout(3)
                    ->acceptJson()
                    ->get(config('services.bed_igd.base_url') . '/master-bed');

                if (! $response->successful()) {
                    Cache::forget(self::TOKEN_CACHE_KEY);
                    throw new \RuntimeException("GET /master-bed gagal: HTTP {$response->status()}");
                }

                $allBeds  = $response->json()['data'] ?? [];
                $filtered = collect($allBeds)->filter(
                    fn($b) => ($b['BedIgd']['No_Reg'] ?? null) === $noReg
                );

                return [
                    'beds'   => $filtered->values()->map(fn($b) => $this->normalizeBed($b))->toArray(),
                    'source' => 'bed_igd_api',
                ];
            } catch (\Exception $e) {
                Log::warning('BedIgdService::getBedsByNoReg API failed', ['error' => $e->getMessage()]);
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

                return [
                    'beds'   => $rows->map(fn($r) => $this->normalizeRow($r))->values()->toArray(),
                    'source' => 'rsus_db',
                ];
            } catch (\Exception $e) {
                Log::warning('BedIgdService::getBedsByNoReg RSUS failed', ['error' => $e->getMessage()]);
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

        if ($this->isApiEnabled()) {
            try {
                $baseUrl  = config('services.bed_igd.base_url');
                $fullUrl  = rtrim($baseUrl, '/') . $updatePath;
                $payload  = [
                    'Kode_Bed' => $kodeBed,
                    'No_Reg'   => null,
                    'Status'   => 'KOSONG',
                ];

                Log::info("BedIgdService: trigger release bed", [
                    'url'      => $fullUrl,
                    'kode_bed' => $kodeBed,
                    'no_reg'   => $noReg,
                ]);

                // Kirim request dengan token — token dipilih sesuai BED_IGD_AUTH_MODE
                $token    = $this->getToken();
                $response = Http::withToken($token)
                    ->timeout(10)
                    ->acceptJson()
                    ->post($fullUrl, $payload);

                if (! $response->successful()) {
                    $errBody = $response->body();
                    // Clear semua cache token agar retry dapat token segar
                    Cache::forget(self::TOKEN_CACHE_KEY);
                    $this->forgetClientToken();

                    // Jika 401 → token expired, coba sekali lagi dengan token baru
                    if ($response->status() === 401) {
                        Log::info('BedIgdService: token 401, retry dengan token baru.');
                        $token    = $this->getToken();
                        $response = Http::withToken($token)->timeout(10)->acceptJson()->post($fullUrl, $payload);
                    }

                    if (! $response->successful()) {
                        Log::warning("BedIgdService: release bed gagal", [
                            'status' => $response->status(),
                            'body'   => $response->body(),
                        ]);
                        throw new \RuntimeException(
                            "Bed IGD update gagal: HTTP {$response->status()} — {$response->body()}"
                        );
                    }
                }

                Log::info("BedIgdService: Kode_Bed={$kodeBed} No_Reg={$noReg} → KOSONG [API]", [
                    'response' => $response->json(),
                ]);

                return [
                    'success'  => true,
                    'source'   => 'bed_igd_api',
                    'kode_bed' => $kodeBed,
                    'data'     => $response->json(),
                ];
            } catch (\Exception $e) {
                Log::warning('BedIgdService::releaseBed API failed', ['error' => $e->getMessage()]);
                return ['success' => false, 'source' => 'bed_igd_api', 'message' => $e->getMessage()];
            }
        }

        if ($this->isRsusEnabled()) {
            try {
                $affected = DB::connection('rsus')->table('BI_Bed_Igd')
                    ->where('Kode_Bed', $kodeBed)
                    ->where('No_Reg',   $noReg)
                    ->update(['Status' => 'KOSONG', 'No_Reg' => null, 'update_at' => now()]);

                Log::info("BedIgdService: Kode_Bed={$kodeBed} → KOSONG [RSUS DB]", ['affected' => $affected]);
                return ['success' => true, 'source' => 'rsus_db', 'kode_bed' => $kodeBed];
            } catch (\Exception $e) {
                Log::warning('BedIgdService::releaseBed RSUS failed', ['error' => $e->getMessage()]);
                return ['success' => false, 'source' => 'rsus_db', 'message' => $e->getMessage()];
            }
        }

        Log::info("BedIgdService: mock — Kode_Bed={$kodeBed} No_Reg={$noReg} → KOSONG");
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
        // Ambil token dari session user yang sedang aktif
        $token = session('keycloak_access_token');

        if (! $token) {
            // Fallback: cek apakah token bisa di-refresh dulu
            Log::warning('BedIgdService[passthrough]: keycloak_access_token tidak ada di session.');
            throw new \RuntimeException(
                'Token Keycloak user tidak ditemukan di session. ' .
                'Pastikan user login melalui SSO dan sesi masih aktif.'
            );
        }

        // Cek token belum expired — decode payload tanpa verify signature (cukup untuk cek exp)
        $parts = explode('.', $token);
        if (count($parts) === 3) {
            $payload = json_decode(base64_decode(strtr($parts[1], '-_', '+/')), true) ?? [];
            $exp     = $payload['exp'] ?? 0;

            if ($exp > 0 && $exp < (time() + 30)) {
                // Token expired atau hampir expired — coba refresh
                Log::info('BedIgdService[passthrough]: token mendekati/sudah expired, coba refresh.');
                $token = $this->tryRefreshKeycloakToken($token);
            }
        }

        Log::info('BedIgdService[passthrough]: menggunakan token Keycloak user dari session.', [
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

        Log::info('BedIgdService[passthrough]: token berhasil di-refresh dari Keycloak.');

        return $newAccessToken;
    }

    /**
     * KEYCLOAK CLIENT CREDENTIALS — service account aplikasi ini.
     * Token di-cache otomatis, di-refresh saat mendekati expiry.
     * Tidak bergantung pada user session — aman untuk background job dan
     * proses apapun termasuk saat tidak ada user yang login.
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

        Log::info('BedIgdService[keycloak]: client_credentials token berhasil di-generate.', [
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

    private function fetchBedIgdId(string $token, string $kodeBed): ?int
    {
        $response = Http::withToken($token)->timeout(10)->acceptJson()
            ->get(config('services.bed_igd.base_url') . '/master-bed');

        if (! $response->successful()) return null;

        $found = collect($response->json()['data'] ?? [])
            ->first(fn($b) => ($b['Kode_Bed'] ?? '') === $kodeBed);

        return $found['BedIgd']['id'] ?? null;
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
