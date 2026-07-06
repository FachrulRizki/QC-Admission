<?php

namespace App\Services\QcAdmission;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class BedIgdService
{
    private const TOKEN_CACHE_KEY = 'bed_igd_api_token';

    /**
     * Mode update: 'direct' atau 'by_id'
     * Ganti ke 'by_id' jika API hanya terima BedIgd.id (bukan Kode_Bed) di path.
     */
    private const UPDATE_MODE = 'direct';

    /**
     * Path endpoint update.
     * Mode 'direct' → path tanpa {id}, contoh: '/bed/release/trigger'
     * Mode 'by_id'  → path dengan {id}, contoh: '/bed-igd/{id}/status'
     */
    private const UPDATE_PATH = '/bed/release/trigger';

    // ── Public API────────

    /**
     * Ambil bed yang sedang ditempati pasien berdasarkan No_Reg.
     * GET /master-bed → filter client-side berdasarkan BedIgd.No_Reg.
     *
     * @return array{ beds: array, source: string }
     */
    public function getBedsByNoReg(string $noReg): array
    {
        if ($this->isApiEnabled()) {
            try {
                $token    = $this->getToken();
                $response = Http::withToken($token)
                    ->timeout(15)
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
     *
     * Mode 'direct': langsung POST Kode_Bed + No_Reg ke API (1 request).
     * Mode 'by_id':  GET /master-bed dulu untuk ambil BedIgd.id (2 request).
     *
     * @return array{ success: bool, source: string, message?: string }
     */
    public function releaseBed(string $kodeBed, string $noReg): array
    {
        if (! $kodeBed) {
            return ['success' => false, 'source' => 'none', 'message' => 'Kode_Bed tidak boleh kosong.'];
        }

        // Bed IGD API
        if ($this->isApiEnabled()) {
            try {
                $token = $this->getToken();

                if (self::UPDATE_MODE === 'direct') {
                    $response = Http::withToken($token)
                        ->timeout(10)
                        ->acceptJson()
                        ->post(config('services.bed_igd.base_url') . self::UPDATE_PATH, [
                            'Kode_Bed' => $kodeBed,
                            'No_Reg'   => null,
                            'Status'   => 'KOSONG',
                        ]);
                } else {
                    $bedIgdId = $this->fetchBedIgdId($token, $kodeBed);
                    if (! $bedIgdId) {
                        throw new \RuntimeException(
                            "BedIgd.id tidak ditemukan untuk Kode_Bed={$kodeBed}. "
                            . "Coba ganti UPDATE_MODE ke 'direct'."
                        );
                    }
                    $path     = str_replace('{id}', (string) $bedIgdId, self::UPDATE_PATH);
                    $response = Http::withToken($token)
                        ->timeout(10)
                        ->acceptJson()
                        ->post(config('services.bed_igd.base_url') . $path, [
                            'Status' => 'KOSONG',
                            'No_Reg' => null,
                        ]);
                }

                if (! $response->successful()) {
                    Cache::forget(self::TOKEN_CACHE_KEY);
                    throw new \RuntimeException(
                        "Bed IGD update gagal: HTTP {$response->status()} — {$response->body()}"
                    );
                }

                Log::info("BedIgdService: Kode_Bed={$kodeBed} No_Reg={$noReg} → KOSONG [API]");
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

        // RSUS DB langsung
        if ($this->isRsusEnabled()) {
            try {
                DB::connection('rsus')->table('BI_Bed_Igd')
                    ->where('Kode_Bed', $kodeBed)
                    ->where('No_Reg',   $noReg)
                    ->update(['Status' => 'KOSONG', 'No_Reg' => null, 'update_at' => now()]);

                Log::info("BedIgdService: Kode_Bed={$kodeBed} → KOSONG [RSUS DB]");
                return ['success' => true, 'source' => 'rsus_db', 'kode_bed' => $kodeBed];
            } catch (\Exception $e) {
                Log::warning('BedIgdService::releaseBed RSUS failed', ['error' => $e->getMessage()]);
                return ['success' => false, 'source' => 'rsus_db', 'message' => $e->getMessage()];
            }
        }

        // Mock
        Log::info("BedIgdService: mock — Kode_Bed={$kodeBed} No_Reg={$noReg} → KOSONG");
        return ['success' => true, 'source' => 'mock', 'kode_bed' => $kodeBed];
    }

    // Token

    private function getToken(): string
    {
        if ($token = Cache::get(self::TOKEN_CACHE_KEY)) {
            return $token;
        }

        $response = Http::timeout(15)
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

    // Helpers

    /** Fetch BedIgd.id dari /master-bed (dipakai mode 'by_id'). */
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

    /** Normalisasi item dari GET /master-bed ke format standar. */
    private function normalizeBed(array $bed): array
    {
        $b = $bed['BedIgd'] ?? [];
        return [
            'kode_bed'       => $bed['Kode_Bed']          ?? null,
            'bed_id'         => $bed['Kode_Bed']          ?? null,
            'nama_bed'       => $bed['Nama_Bed']          ?? null,
            'ket_bed'        => $bed['Ket_Bed']           ?? null,
            'kode_triase'    => $bed['Kode_Triase']       ?? null,
            'kode_ruang'     => $bed['Kode_Ruang']        ?? null,
            'kode_bangsal'   => $bed['Kode_Bangsal']      ?? 'IGD',
            'bed_igd_id'     => $b['id']                  ?? null,
            'status'         => strtoupper($b['Status']   ?? ''),
            'no_reg'         => $b['No_Reg']              ?? null,
            'tanggal'        => $b['Tanggal']             ?? null,
            'updated_reg_at' => $b['updated_reg_at']      ?? null,
        ];
    }

    /** Normalisasi baris dari BI_Bed_Igd (RSUS DB). */
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
            'kode_bed' => 'ED001', 'bed_id' => 'ED001',
            'nama_bed' => 'EMERGENCY BED 01', 'ket_bed' => 'EMERGENCY BED',
            'kode_triase' => 'MERAH', 'kode_ruang' => 'EDR001', 'kode_bangsal' => 'IGD',
            'bed_igd_id' => 221,
            'status' => 'TERISI', 'no_reg' => $noReg,
            'tanggal' => now()->toDateString(), 'updated_reg_at' => null,
        ]];
    }
}
