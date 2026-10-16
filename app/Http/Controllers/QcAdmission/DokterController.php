<?php

namespace App\Http\Controllers\QcAdmission;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class DokterController extends Controller
{
    public function search(Request $request): JsonResponse
    {
        $q = trim($request->query('q', ''));

        if (strlen($q) < 2) {
            return response()->json(['data' => [], 'source' => 'none']);
        }

        // ── Coba KPI API dulu ──────────────────────────────────────────────
        try {
            $data = $this->searchFromKpi($q);
            if (! empty($data)) {
                return response()->json(['data' => $data, 'total' => count($data), 'source' => 'kpi_api']);
            }
            // KPI kosong → lanjut ke RSUS
        } catch (\Exception $e) {
            Log::warning('DokterController: KPI API gagal.', ['error' => $e->getMessage()]);
        }

        // ── Fallback RSUS DB ───────────────────────────────────────────────
        if (config('services.rsus_db_enabled', false)) {
            try {
                $data = $this->searchFromRsus($q);
                if (! empty($data)) {
                    return response()->json(['data' => $data, 'total' => count($data), 'source' => 'rsus_db']);
                }
            } catch (\Exception $e) {
                Log::warning('DokterController: RSUS DB gagal.', ['error' => $e->getMessage()]);
            }
        }

        // ── Fallback mock ──────────────────────────────────────────────────
        return response()->json([
            'data'   => $this->mockDokter($q),
            'source' => 'mock_fallback',
        ]);
    }

    // ────────────────────────────────────────────────────────────────────────
    // KPI API
    // ────────────────────────────────────────────────────────────────────────

    private function searchFromKpi(string $search): array
    {
        $token = $this->getKpiToken();

        // Coba endpoint /dokter dulu (mungkin ada di versi baru KPI API)
        $data = $this->tryKpiDokterEndpoint($token, $search);

        if ($data === null) {
            // Endpoint /dokter tidak ada → fallback ke /pegawai dengan filter jabatan
            $data = $this->tryKpiPegawaiDokter($token, $search);
        }

        return $data ?? [];
    }

    /** Coba GET /dokter?search=... — return null jika endpoint tidak ada (404/405). */
    private function tryKpiDokterEndpoint(string $token, string $search): ?array
    {
        $cacheKey = 'kpi_dokter_' . md5(strtolower($search));
        $cacheTtl = (int) config('services.kpi_api.cache_ttl', 300);

        return Cache::remember($cacheKey, $cacheTtl, function () use ($token, $search) {
            $response = Http::timeout(10)
                ->withToken($token)
                ->acceptJson()
                ->get(config('services.kpi_api.base_url') . '/dokter', [
                    'search' => $search,
                    'limit'  => 30,
                ]);

            // Endpoint tidak ada
            if (in_array($response->status(), [404, 405, 501])) {
                return null;
            }

            if (! $response->successful()) {
                Cache::forget('kpi_api_token');
                throw new \RuntimeException("KPI /dokter gagal: HTTP {$response->status()}");
            }

            $body  = $response->json();
            $items = $body['data'] ?? $body['dokter'] ?? (isset($body[0]) ? $body : []);

            return $this->mapKpiDokter($items, $search);
        });
    }

    /** Fallback: GET /pegawai lalu filter lokal by jabatan mengandung 'dokter'. */
    private function tryKpiPegawaiDokter(string $token, string $search): array
    {
        $cacheKey = 'kpi_pegawai_dokter_' . md5(strtolower($search));
        $cacheTtl = (int) config('services.kpi_api.cache_ttl', 300);

        return Cache::remember($cacheKey, $cacheTtl, function () use ($token, $search) {
            $response = Http::timeout(15)
                ->withToken($token)
                ->acceptJson()
                ->get(config('services.kpi_api.base_url') . '/pegawai', [
                    'limit' => 9999,
                    'page'  => 1,
                ]);

            if (! $response->successful()) {
                Cache::forget('kpi_api_token');
                throw new \RuntimeException("KPI /pegawai gagal: HTTP {$response->status()}");
            }

            $body  = $response->json();
            $items = $body['data'] ?? $body['pegawai'] ?? $body['items'] ?? (isset($body[0]) ? $body : []);

            $lower = strtolower($search);

            return collect($items)
                ->filter(fn($p) =>
                    // Hanya yang jabatannya mengandung kata 'dokter'
                    str_contains(strtolower($p['jabatan'] ?? $p['posisi'] ?? ''), 'dokter') &&
                    // Cocok dengan keyword search
                    (str_contains(strtolower($p['nama'] ?? $p['name'] ?? ''), $lower) ||
                     str_contains(strtolower($p['jabatan'] ?? $p['posisi'] ?? ''), $lower))
                )
                ->map(fn($p) => [
                    'kode'      => $p['nip'] ?? $p['id'] ?? null,
                    'nama'      => trim($p['nama'] ?? $p['name'] ?? $p['nama_pegawai'] ?? '—'),
                    'spesialis' => trim($p['jabatan'] ?? $p['posisi'] ?? ''),
                    'label'     => trim(($p['nama'] ?? $p['name'] ?? '') . ($p['jabatan'] ? ' — ' . $p['jabatan'] : '')),
                ])
                ->values()
                ->take(30)
                ->toArray();
        });
    }

    private function mapKpiDokter(array $items, string $search): array
    {
        $lower = strtolower($search);

        return collect($items)
            ->filter(fn($d) =>
                str_contains(strtolower($d['nama'] ?? $d['name'] ?? ''), $lower) ||
                str_contains(strtolower($d['spesialis'] ?? $d['jabatan'] ?? ''), $lower)
            )
            ->map(fn($d) => [
                'kode'      => $d['kode']  ?? $d['id']     ?? $d['nip']  ?? null,
                'nama'      => trim($d['nama'] ?? $d['name'] ?? '—'),
                'spesialis' => trim($d['spesialis'] ?? $d['jabatan'] ?? ''),
                'label'     => trim(($d['nama'] ?? $d['name'] ?? '') .
                               (($d['spesialis'] ?? $d['jabatan'] ?? '') ? ' — ' . ($d['spesialis'] ?? $d['jabatan']) : '')),
            ])
            ->values()
            ->take(30)
            ->toArray();
    }

    private function getKpiToken(): string
    {
        $cacheKey = 'kpi_api_token';

        if ($token = Cache::get($cacheKey)) {
            return $token;
        }

        $response = Http::timeout(15)
            ->acceptJson()
            ->post(config('services.kpi_api.base_url') . '/auth/login', [
                'username' => config('services.kpi_api.username'),
                'password' => config('services.kpi_api.password'),
            ]);

        if (! $response->successful()) {
            throw new \RuntimeException("KPI login gagal: HTTP {$response->status()}");
        }

        $body  = $response->json();
        $token = $body['token'] ?? $body['access_token']
            ?? $body['data']['token'] ?? $body['data']['access_token'] ?? null;

        if (! $token) {
            throw new \RuntimeException('KPI API: token tidak ada di respons login.');
        }

        Cache::put($cacheKey, $token, now()->addMinutes(
            (int) config('services.kpi_api.token_cache_minutes', 55)
        ));

        return $token;
    }

    // ────────────────────────────────────────────────────────────────────────
    // RSUS DB fallback
    // ────────────────────────────────────────────────────────────────────────

    private function searchFromRsus(string $search): array
    {
        $like = "%{$search}%";

        $rows = DB::connection('rsus')
            ->select("
                SELECT TOP 30
                    Kode_Dokter,
                    Nama_Dokter,
                    Spesialis
                FROM dbo.DOKTER
                WHERE Status_Aktif = '1'
                  AND (Nama_Dokter LIKE ? OR Spesialis LIKE ?)
                ORDER BY Nama_Dokter
            ", [$like, $like]);

        return collect($rows)->map(fn($r) => [
            'kode'      => $r->Kode_Dokter ?? '',
            'nama'      => trim($r->Nama_Dokter ?? ''),
            'spesialis' => trim($r->Spesialis   ?? ''),
            'label'     => trim(($r->Nama_Dokter ?? '') . ($r->Spesialis ? ' — ' . $r->Spesialis : '')),
        ])->toArray();
    }

    // ────────────────────────────────────────────────────────────────────────
    // Mock fallback (dev / semua source gagal)
    // ────────────────────────────────────────────────────────────────────────

    private function mockDokter(string $search = ''): array
    {
        $data = [
            ['kode' => 'DR001', 'nama' => 'dr. Ahmad Fauzi, Sp.B',    'spesialis' => 'Bedah Umum'],
            ['kode' => 'DR002', 'nama' => 'dr. Siti Rahayu, Sp.PD',   'spesialis' => 'Penyakit Dalam'],
            ['kode' => 'DR003', 'nama' => 'dr. Budi Santoso, Sp.OG',  'spesialis' => 'Obstetri & Ginekologi'],
            ['kode' => 'DR004', 'nama' => 'dr. Dewi Anggraini, Sp.A', 'spesialis' => 'Anak'],
            ['kode' => 'DR005', 'nama' => 'dr. Hendra Wijaya, Sp.JP', 'spesialis' => 'Jantung & Pembuluh Darah'],
            ['kode' => 'DR006', 'nama' => 'dr. Rina Kusuma, Sp.S',    'spesialis' => 'Saraf'],
            ['kode' => 'DR007', 'nama' => 'dr. Wahyu Pratama, Sp.BS', 'spesialis' => 'Bedah Saraf'],
            ['kode' => 'DR008', 'nama' => 'dr. Lestari Indah, Sp.M',  'spesialis' => 'Mata'],
            ['kode' => 'DR009', 'nama' => 'dr. Rizal Hakim, Sp.U',    'spesialis' => 'Urologi'],
            ['kode' => 'DR010', 'nama' => 'dr. Maya Sari, Sp.An',     'spesialis' => 'Anestesiologi'],
        ];

        $data = array_map(fn($d) => array_merge($d, [
            'label' => $d['nama'] . ($d['spesialis'] ? ' — ' . $d['spesialis'] : ''),
        ]), $data);

        if ($search === '') return $data;

        $s = strtolower($search);
        return array_values(array_filter($data, fn($d) =>
            str_contains(strtolower($d['nama']), $s) ||
            str_contains(strtolower($d['spesialis']), $s)
        ));
    }
}
