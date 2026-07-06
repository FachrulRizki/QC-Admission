<?php

namespace App\Services\QcAdmission;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class BedIgdService
{
    private const TOKEN_CACHE_KEY = 'bed_igd_api_token';

    public function getBedsByNoReg(string $noReg): array
    {
        // Bed IGD API
        if ($this->isApiEnabled()) {
            try {
                $token    = $this->getToken();
                $response = Http::withToken($token)
                    ->timeout(10)
                    ->acceptJson()
                    ->get(config('services.bed_igd.base_url') . '/beds', [
                        'no_reg' => $noReg,
                    ]);

                if (! $response->successful()) {
                    Cache::forget(self::TOKEN_CACHE_KEY);
                    throw new \RuntimeException("Bed IGD GET /beds gagal: HTTP {$response->status()}");
                }

                $body = $response->json();
                $beds = $body['beds'] ?? $body['data'] ?? (isset($body[0]) ? $body : []);

                return [
                    'beds'   => $this->normalizeBeds($beds),
                    'source' => 'bed_igd_api',
                ];
            } catch (\Exception $e) {
                Log::warning('BedIgdService::getBedsByNoReg API failed', ['error' => $e->getMessage()]);
            }
        }

        // RSUS DB
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
                Log::warning('BedIgdService::getBedsByNoReg RSUS DB failed', ['error' => $e->getMessage()]);
            }
        }

        // Mock
        return [
            'beds'   => $this->mockBeds($noReg),
            'source' => 'mock',
        ];
    }

    /**
     * Update status bed menjadi KOSONG saat Batal Ranap dikonfirmasi Siap Closing.
     *
     * @return array{ success: bool, source: string, message?: string }
     */
    public function releaseBed(string $kodeBed, string $noReg): array
    {
        if (! $kodeBed) {
            return ['success' => false, 'source' => 'none', 'message' => 'Kode bed tidak boleh kosong.'];
        }

        // Bed IGD API
        if ($this->isApiEnabled()) {
            try {
                $token    = $this->getToken();
                $response = Http::withToken($token)
                    ->timeout(10)
                    ->acceptJson()
                    ->post(config('services.bed_igd.base_url') . '/beds/update-status', [
                        'kode_bed'  => $kodeBed,
                        'no_reg'    => $noReg,
                        'status'    => 'KOSONG',
                        'timestamp' => now()->toISOString(),
                    ]);

                if (! $response->successful()) {
                    Cache::forget(self::TOKEN_CACHE_KEY);
                    throw new \RuntimeException("Bed IGD POST update-status gagal: HTTP {$response->status()} — {$response->body()}");
                }

                Log::info("BedIgdService: bed {$kodeBed} (No_Reg: {$noReg}) → KOSONG via API");
                return ['success' => true, 'source' => 'bed_igd_api', 'data' => $response->json()];
            } catch (\Exception $e) {
                Log::warning('BedIgdService::releaseBed API failed', ['error' => $e->getMessage()]);
                return ['success' => false, 'source' => 'bed_igd_api', 'message' => $e->getMessage()];
            }
        }

        // RSUS DB langsung
        if ($this->isRsusEnabled()) {
            try {
                // Kolom timestamp di BI_Bed_Igd: "update_at" (bukan updated_at)
                DB::connection('rsus')->table('BI_Bed_Igd')
                    ->where('Kode_Bed', $kodeBed)
                    ->where('No_Reg', $noReg)
                    ->update([
                        'Status'    => 'KOSONG',
                        'No_Reg'    => null,
                        'update_at' => now(),
                    ]);

                Log::info("BedIgdService: bed {$kodeBed} (No_Reg: {$noReg}) → KOSONG via RSUS DB");
                return ['success' => true, 'source' => 'rsus_db', 'kode_bed' => $kodeBed];
            } catch (\Exception $e) {
                Log::warning('BedIgdService::releaseBed RSUS DB failed', ['error' => $e->getMessage()]);
                return ['success' => false, 'source' => 'rsus_db', 'message' => $e->getMessage()];
            }
        }

        // Mock
        Log::info("BedIgdService: mock release — bed {$kodeBed} (No_Reg: {$noReg}) → KOSONG");
        return ['success' => true, 'source' => 'mock', 'kode_bed' => $kodeBed];
    }

    // ── Token management ──────────────────────────────────────────────────────

    /**
     * Ambil token Bed IGD API. Di-cache sesuai token_cache_minutes (default 55 menit).
     *
     * @throws \RuntimeException jika login gagal
     */
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
            throw new \RuntimeException("Bed IGD login gagal: HTTP {$response->status()} — {$response->body()}");
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

        Log::info("BedIgdService: token baru dicache {$ttl} menit.");
        return $token;
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function isApiEnabled(): bool
    {
        return (bool) config('services.bed_igd.enabled', false)
            && ! empty(config('services.bed_igd.base_url'));
    }

    private function isRsusEnabled(): bool
    {
        return (bool) config('services.rsus_db_enabled', false);
    }

    private function normalizeBeds(array $beds): array
    {
        return collect($beds)->map(function ($b) {
            return [
                'kode_bed'       => $b['kode_bed']       ?? $b['bed_code']  ?? $b['bed_id']   ?? null,
                'bed_id'         => $b['kode_bed']       ?? $b['bed_code']  ?? $b['bed_id']   ?? null,
                'status'         => strtoupper($b['status'] ?? ''),
                'no_reg'         => $b['no_reg']         ?? null,
                'tanggal'        => $b['tanggal']        ?? $b['date']      ?? null,
                'updated_reg_at' => $b['updated_reg_at'] ?? null,
            ];
        })->values()->toArray();
    }

    private function normalizeRow(object $r): array
    {
        return [
            'kode_bed'       => $r->Kode_Bed          ?? null,
            'bed_id'         => $r->Kode_Bed          ?? null,
            'status'         => strtoupper($r->Status ?? ''),
            'no_reg'         => $r->No_Reg            ?? null,
            'tanggal'        => $r->Tanggal           ?? null,
            'updated_reg_at' => $r->updated_reg_at    ?? null,
        ];
    }

    /**
     * Data mock untuk development/testing (tidak ada koneksi RSUS / API).
     */
    private function mockBeds(string $noReg): array
    {
        return [
            ['kode_bed'=>'ED001','bed_id'=>'ED001','status'=>'TERISI','no_reg'=>$noReg,       'tanggal'=>now()->toDateString(),'updated_reg_at'=>null],
            ['kode_bed'=>'ED002','bed_id'=>'ED002','status'=>'KOSONG','no_reg'=>null,          'tanggal'=>now()->toDateString(),'updated_reg_at'=>null],
            ['kode_bed'=>'ED003','bed_id'=>'ED003','status'=>'KOSONG','no_reg'=>null,          'tanggal'=>now()->toDateString(),'updated_reg_at'=>null],
        ];
    }
}
