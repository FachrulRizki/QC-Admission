<?php

namespace App\Http\Controllers\QcAdmission;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PegawaiController extends Controller
{
    /**
     * Ambil semua petugas dari KPI API departemen Customer Care.
     * Digunakan untuk field "Petugas" di semua form.
     */
    public function index(): JsonResponse
    {
        $cacheKey = 'kpi_pegawai_customer_care';
        $cacheTtl = (int) config('services.kpi_api.cache_ttl', 300);

        $data = Cache::remember($cacheKey, $cacheTtl, function () {
            try {
                $token = $this->getKpiToken();
                return [
                    'data'   => $this->fetchPegawaiByDepartemen($token, config('services.kpi_api.departemen', 'Customer Care')),
                    'source' => 'kpi_api',
                ];
            } catch (\Exception $e) {
                Log::warning('KPI API gagal, fallback hardcoded.', ['error' => $e->getMessage()]);
                return ['data' => $this->fallbackPetugas(), 'source' => 'fallback'];
            }
        });

        return response()->json([
            'data'   => $data['data'],
            'total'  => count($data['data']),
            'source' => $data['source'],
        ]);
    }

    /**
     * GET /api/pegawai/semua
     * Ambil SEMUA pegawai RS tanpa filter departemen — untuk field "Rekomendasi Karyawan RS".
     */
    public function semua(): JsonResponse
    {
        $cacheKey = 'kpi_pegawai_semua';
        $cacheTtl = (int) config('services.kpi_api.cache_ttl', 300);

        $data = Cache::remember($cacheKey, $cacheTtl, function () {
            try {
                $token = $this->getKpiToken();
                return [
                    'data'   => $this->fetchPegawaiByDepartemen($token, null),
                    'source' => 'kpi_api',
                ];
            } catch (\Exception $e) {
                Log::warning('KPI /semua gagal.', ['error' => $e->getMessage()]);
                return ['data' => $this->fallbackPetugas(), 'source' => 'fallback'];
            }
        });

        return response()->json([
            'data'   => $data['data'],
            'total'  => count($data['data']),
            'source' => $data['source'],
        ]);
    }

    /**
     * GET /api/pegawai/search?q=keyword
     */
    public function search(\Illuminate\Http\Request $request): JsonResponse
    {
        $q = trim($request->query('q', ''));

        if ($q === '') {
            return response()->json(['data' => [], 'total' => 0]);
        }

        $cacheTtl = (int) config('services.kpi_api.cache_ttl', 300);
        $cacheKey = 'kpi_pegawai_search_' . md5(strtolower($q));

        $results = Cache::remember($cacheKey, $cacheTtl, function () use ($q) {
            try {
                $token = $this->getKpiToken();
                return $this->fetchPegawaiByDepartemen($token, null, $q);
            } catch (\Exception $e) {
                Log::warning('KPI search gagal.', ['q' => $q, 'error' => $e->getMessage()]);
                $lower = strtolower($q);
                return array_values(array_filter(
                    $this->fallbackPetugas(),
                    fn($p) => str_contains(strtolower($p['nama']), $lower)
                        || str_contains(strtolower((string) ($p['nip'] ?? '')), $lower)
                ));
            }
        });

        return response()->json(['data' => $results, 'total' => count($results)]);
    }

    /**
     * Ambil/refresh token KPI API. Di-cache 55 menit.
     */
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
            throw new \RuntimeException("KPI login gagal: HTTP {$response->status()} — {$response->body()}");
        }

        $body  = $response->json();
        $token = $body['token'] ?? $body['access_token'] ?? $body['data']['token'] ?? $body['data']['access_token'] ?? null;

        if (! $token) {
            throw new \RuntimeException('KPI API: token tidak ada di respons login.');
        }

        Cache::put($cacheKey, $token, now()->addMinutes(
            (int) config('services.kpi_api.token_cache_minutes', 55)
        ));

        return $token;
    }

    /**
     * Fetch pegawai dari KPI API.
     * - $departemen = null  → semua pegawai (tanpa filter departemen)
     * - $departemen = 'X'   → hanya departemen X
     * - $search diisi       → filter lokal berdasarkan nama/NIP
     */
    private function fetchPegawaiByDepartemen(string $token, ?string $departemen, string $search = ''): array
    {
        $params = ['limit' => 9999, 'page' => 1];
        if ($departemen !== null) {
            $params['departemen'] = $departemen;
        }

        $response = Http::timeout(20)
            ->withToken($token)
            ->acceptJson()
            ->get(config('services.kpi_api.base_url') . '/pegawai', $params);

        if (! $response->successful()) {
            Cache::forget('kpi_api_token');
            throw new \RuntimeException("KPI /pegawai gagal: HTTP {$response->status()}");
        }

        $body  = $response->json();
        $items = $body['data'] ?? $body['pegawai'] ?? $body['items'] ?? (isset($body[0]) ? $body : []);

        $mapped = collect($items)->map(fn($p) => [
            'id'         => $p['id']         ?? $p['nip']    ?? null,
            'nama'       => $p['nama']        ?? $p['name']   ?? $p['nama_pegawai'] ?? '—',
            'nip'        => $p['nip']         ?? $p['nik']    ?? null,
            'jabatan'    => $p['jabatan']     ?? $p['posisi'] ?? null,
            'departemen' => $p['departemen']  ?? $p['bagian'] ?? null,
        ])->values();

        if ($search !== '') {
            $lower  = strtolower($search);
            $mapped = $mapped->filter(fn($p) =>
                str_contains(strtolower($p['nama']), $lower) ||
                str_contains(strtolower((string) ($p['nip'] ?? '')), $lower)
            )->values();
        }

        return $mapped->toArray();
    }

    /** Fallback jika KPI API tidak bisa dijangkau (offline / bukan jaringan RS). */
    private function fallbackPetugas(): array
    {
        return [
            ['id' => '1', 'nama' => 'Nurul',           'nip' => null, 'jabatan' => 'Customer Care'],
            ['id' => '2', 'nama' => 'AYU Putri Anisa', 'nip' => null, 'jabatan' => 'Customer Care'],
            ['id' => '3', 'nama' => 'Reskim',          'nip' => null, 'jabatan' => 'Customer Care'],
            ['id' => '4', 'nama' => 'Mulbagus Koyum',  'nip' => null, 'jabatan' => 'Customer Care'],
            ['id' => '5', 'nama' => 'Abdul Hayyi',     'nip' => null, 'jabatan' => 'Customer Care'],
        ];
    }
}
