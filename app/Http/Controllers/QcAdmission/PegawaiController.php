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
     * Alur: cek cache token → POST login → GET /pegawai tanpa limit paginasi.
     */
    public function index(): JsonResponse
    {
        try {
            $token   = $this->getKpiToken();
            $petugas = $this->fetchPegawai($token);

            return response()->json([
                'data'   => $petugas,
                'total'  => count($petugas),
                'source' => 'kpi_api',
            ]);
        } catch (\Exception $e) {
            Log::warning('KPI API gagal, fallback hardcoded.', ['error' => $e->getMessage()]);

            return response()->json([
                'data'   => $this->fallbackPetugas(),
                'total'  => count($this->fallbackPetugas()),
                'source' => 'fallback',
            ]);
        }
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
     * Fetch semua pegawai Customer Care — limit=9999 agar tidak ada paginasi.
     */
    private function fetchPegawai(string $token): array
    {
        $response = Http::timeout(20)
            ->withToken($token)
            ->acceptJson()
            ->get(config('services.kpi_api.base_url') . '/pegawai', [
                'departemen' => config('services.kpi_api.departemen', 'Customer Care'),
                'limit'      => 9999,
                'page'       => 1,
            ]);

        if (! $response->successful()) {
            Cache::forget('kpi_api_token'); // hapus token supaya next request re-login
            throw new \RuntimeException("KPI /pegawai gagal: HTTP {$response->status()}");
        }

        $body  = $response->json();
        $items = $body['data'] ?? $body['pegawai'] ?? $body['items'] ?? (isset($body[0]) ? $body : []);

        return collect($items)->map(fn($p) => [
            'id'      => $p['id']      ?? $p['nip']    ?? null,
            'nama'    => $p['nama']    ?? $p['name']   ?? $p['nama_pegawai'] ?? '—',
            'nip'     => $p['nip']     ?? $p['nik']    ?? null,
            'jabatan' => $p['jabatan'] ?? $p['posisi'] ?? null,
        ])->values()->toArray();
    }

    /** Fallback jika KPI API tidak bisa dijangkau (offline / bukan jaringan RS). */
    private function fallbackPetugas(): array
    {
        return [
            ['id' => 1, 'nama' => 'Nurul',           'nip' => null, 'jabatan' => 'Customer Care'],
            ['id' => 2, 'nama' => 'AYU Putri Anisa', 'nip' => null, 'jabatan' => 'Customer Care'],
            ['id' => 3, 'nama' => 'Reskim',          'nip' => null, 'jabatan' => 'Customer Care'],
            ['id' => 4, 'nama' => 'Mulbagus Koyum',  'nip' => null, 'jabatan' => 'Customer Care'],
            ['id' => 5, 'nama' => 'Abdul Hayyi',     'nip' => null, 'jabatan' => 'Customer Care'],
        ];
    }
}
