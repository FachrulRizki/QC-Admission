<?php

namespace App\Services\QcAdmission;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class BedIgdService
{
    private const TOKEN_CACHE_KEY = 'bed_igd_api_token';

    // ── Public API ────────────────────────────────────────────────────────────
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

        return ['beds' => $this->mockBeds($noReg), 'source' => 'mock'];
    }

    /**
     * Update status bed → KOSONG saat Batal Ranap dikonfirmasi Siap Closing.
     * Endpoint: POST {base_url}/bed/release/trigger
     * Body: { Kode_Bed, No_Reg, Status }
     * @return array{ success: bool, source: string, message?: string, data?: array }
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

                // Coba dengan token auth dulu
                $response = null;
                try {
                    $token    = $this->getToken();
                    $response = Http::withToken($token)
                        ->timeout(10)
                        ->acceptJson()
                        ->post($fullUrl, $payload);
                } catch (\Exception $authErr) {
                    // Jika auth gagal, coba tanpa token (beberapa internal API tidak butuh auth)
                    Log::warning("BedIgdService: auth gagal, coba tanpa token", ['error' => $authErr->getMessage()]);
                    $response = Http::timeout(10)
                        ->acceptJson()
                        ->post($fullUrl, $payload);
                }

                if (! $response->successful()) {
                    $errBody = $response->body();
                    // Clear semua cache token agar retry dapat token segar
                    Cache::forget(self::TOKEN_CACHE_KEY);
                    Cache::forget(self::TOKEN_CACHE_KEY . '_kc');
                    Log::warning("BedIgdService: release bed gagal", [
                        'status' => $response->status(),
                        'body'   => $errBody,
                    ]);
                    throw new \RuntimeException(
                        "Bed IGD update gagal: HTTP {$response->status()} — {$errBody}"
                    );
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

    // ── Token management ──────────────────────────────────────────────────────
    private function getToken(): string
    {
        $authMode = config('services.bed_igd.auth_mode', 'login');

        // ── Mode static: token tetap dari .env ────────────────────────────────
        if ($authMode === 'static') {
            $token = config('services.bed_igd.static_token', '');
            if (! $token) {
                throw new \RuntimeException('BED_IGD_STATIC_TOKEN belum diset di .env');
            }
            return $token;
        }

        // ── Mode keycloak: client credentials grant ───────────────────────────
        if ($authMode === 'keycloak') {
            return $this->getKeycloakToken();
        }

        // ── Mode login (default): POST /auth/login ────────────────────────────
        return $this->getLoginToken();
    }

    private function getLoginToken(): string
    {
        if ($token = Cache::get(self::TOKEN_CACHE_KEY)) {
            return $token;
        }

        $response = Http::timeout(3)
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
    
    private function getKeycloakToken(): string
    {
        $cacheKey = self::TOKEN_CACHE_KEY . '_kc';
        if ($token = Cache::get($cacheKey)) {
            return $token;
        }

        $tokenUrl = config('services.keycloak.base_url')
            . '/realms/' . config('services.keycloak.realm')
            . '/protocol/openid-connect/token';

        $response = Http::timeout(5)
            ->asForm()
            ->post($tokenUrl, [
                'grant_type'    => 'client_credentials',
                'client_id'     => config('services.bed_igd.keycloak_client_id'),
                'client_secret' => config('services.bed_igd.keycloak_secret'),
            ]);

        if (! $response->successful()) {
            throw new \RuntimeException(
                "Bed IGD Keycloak token gagal: HTTP {$response->status()} — {$response->body()}"
            );
        }

        $token = $response->json()['access_token'] ?? null;
        if (! $token) {
            throw new \RuntimeException('Bed IGD Keycloak: access_token tidak ditemukan.');
        }

        $ttl = (int) ($response->json()['expires_in'] ?? 300);
        Cache::put($cacheKey, $token, now()->addSeconds($ttl - 30));
        return $token;
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

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

    private function mockBeds(string $noReg): array
    {
        return [[
            'kode_bed'       => 'ED001',
            'bed_id'         => 'ED001',
            'nama_bed'       => 'EMERGENCY BED 01',
            'ket_bed'        => 'EMERGENCY BED',
            'kode_triase'    => 'MERAH',
            'kode_ruang'     => 'EDR001',
            'kode_bangsal'   => 'IGD',
            'bed_igd_id'     => 221,
            'status'         => 'TERISI',
            'no_reg'         => $noReg,
            'tanggal'        => now()->toDateString(),
            'updated_reg_at' => null,
        ]];
    }
}
