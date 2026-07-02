<?php

namespace App\Http\Controllers\QcAdmission;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Services\QcAdmission\BatalRanapService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class BatalRanapController extends Controller
{
    public function __construct(
        private readonly BatalRanapService $service
    ) {}

    public function index(Request $request): JsonResponse
    {
        $data = $this->service->paginate($request->only([
            'search', 'date_from', 'date_to', 'status_ok', 'per_page', 'page',
        ]));

        return response()->json($data);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'tanggal'             => 'required|string',
            'jam_input'           => 'required|string',
            'no_reg'              => 'required|string|max:20',
            'no_mr'               => 'nullable|string|max:20',
            'tgl_daftar'          => 'nullable|string',
            'jam_daftar'          => 'nullable|string',
            'nama_pasien'         => 'nullable|string|max:100',
            'keterangan_batal'    => 'required|string|max:100',
            'status_ok'           => 'nullable|in:Bedah,Non Bedah',
            'status_closing'      => 'nullable|in:Siap Closing,Belum Siap Closing',
            'ketersediaan_kamar'  => 'nullable|string|max:100',
            'diagnosa'            => 'nullable|string|max:255',
            'note'                => 'nullable|string|max:1000',
            'petugas'             => 'required|string|max:100',
            'ruangan'             => 'nullable|string|max:100',
            'bed_id'              => 'nullable|string|max:50',
        ]);

        $record = $this->service->create($validated);

        ActivityLog::record('batal-ranap', 'create',
            "Batal Ranap {$record->no_reg} — {$record->keterangan_batal}");

        return response()->json(['data' => $record, 'message' => 'Data berhasil disimpan.'], 201);
    }

    public function show(int $id): JsonResponse
    {
        return response()->json(['data' => $this->service->findOrFail($id)]);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'keterangan_batal'   => 'sometimes|string|max:100',
            'status_ok'          => 'nullable|in:Bedah,Non Bedah',
            'status_closing'     => 'nullable|in:Siap Closing,Belum Siap Closing',
            'ketersediaan_kamar' => 'nullable|string|max:100',
            'diagnosa'           => 'nullable|string|max:255',
            'note'               => 'nullable|string|max:1000',
            'petugas'            => 'sometimes|string|max:100',
            'ruangan'            => 'nullable|string|max:100',
        ]);

        $record = $this->service->update($id, $validated);

        ActivityLog::record('batal-ranap', 'update',
            "Batal Ranap diupdate — {$record->no_reg}");

        return response()->json(['data' => $record, 'message' => 'Data berhasil diperbarui.']);
    }

    /**
     * Verifikasi (patch status_ok only).
     */
    public function verifikasi(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'status_ok' => 'required|in:Bedah,Non Bedah',
            'note'      => 'nullable|string|max:255',
        ]);

        $record = $this->service->update($id, $validated);

        ActivityLog::record('batal-ranap', 'verifikasi',
            "Verifikasi Batal Ranap {$record->no_reg} → {$record->status_ok}");

        return response()->json(['data' => $record, 'message' => 'Verifikasi berhasil.']);
    }

    /**
     * Konfirmasi Closing — update status_closing.
     * Jika "Siap Closing" → trigger Bed Management IGD API dari server (bukan frontend)
     * supaya update bed availability terjadi secara server-to-server.
     */
    public function konfirmasiClosing(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'status_closing' => 'required|in:Siap Closing,Belum Siap Closing',
        ]);

        $record = $this->service->update($id, $validated);

        ActivityLog::record('batal-ranap', 'closing',
            "Closing Batal Ranap {$record->no_reg} → {$record->status_closing}");

        $bedUpdateResult = null;

        // Jika Siap Closing → bebaskan bed IGD via Bed Management API
        if ($validated['status_closing'] === 'Siap Closing') {
            $bedUpdateResult = $this->updateBedManagement($record);
        }

        return response()->json([
            'data'             => $record,
            'message'          => 'Status closing berhasil disimpan.',
            'bed_update'       => $bedUpdateResult,
        ]);
    }

    /**
     * Call Bed Management IGD API untuk update ketersediaan bed → available.
     * Mendukung: external HTTP API (BED_MANAGEMENT_URL) atau RSUS DB langsung.
     */
    private function updateBedManagement(object $record): array
    {
        $bedId   = $record->bed_id   ?? null;
        $ruangan = $record->ruangan  ?? null;

        if (! $bedId && ! $ruangan) {
            return ['success' => false, 'message' => 'bed_id dan ruangan kosong — skip update.', 'source' => 'none'];
        }

        $url = config('services.bed_management.base_url', '');

        // ── External HTTP API ─────────────────────────────────────────────
        if (! empty($url)) {
            try {
                $response = Http::withToken(config('services.bed_management.token', ''))
                    ->timeout(8)
                    ->post("{$url}/api/beds/update-status", [
                        'bed_id'    => $bedId,
                        'ruangan'   => $ruangan,
                        'no_reg'    => $record->no_reg,
                        'status'    => 'available',
                        'timestamp' => now()->toISOString(),
                    ]);

                if ($response->successful()) {
                    Log::info("Bed Management update OK — bed {$bedId} ruangan {$ruangan}");
                    return ['success' => true, 'source' => 'api', 'data' => $response->json()];
                }

                Log::warning("Bed Management API error {$response->status()}: " . $response->body());
                return ['success' => false, 'source' => 'api', 'status' => $response->status(), 'message' => $response->body()];

            } catch (\Exception $e) {
                Log::warning('Bed Management API exception: ' . $e->getMessage());
                return ['success' => false, 'source' => 'api', 'message' => $e->getMessage()];
            }
        }

        // ── RSUS DB langsung (fallback jika tidak ada external API) ───────
        if (config('services.rsus_db_enabled', false)) {
            try {
                \Illuminate\Support\Facades\DB::connection('rsus')
                    ->table('BI_Bed_Igd')
                    ->where('No_Bed', $bedId)
                    ->update([
                        'Status'     => 'Kosong',
                        'updated_at' => now(),
                    ]);
                Log::info("Bed Management RSUS DB update OK — bed {$bedId}");
                return ['success' => true, 'source' => 'rsus_db'];
            } catch (\Exception $e) {
                Log::warning('Bed Management RSUS DB update failed: ' . $e->getMessage());
                return ['success' => false, 'source' => 'rsus_db', 'message' => $e->getMessage()];
            }
        }

        // ── Mock / Dev mode ───────────────────────────────────────────────
        Log::info("Bed Management mock update — bed {$bedId} ruangan {$ruangan} → available");
        return ['success' => true, 'source' => 'mock', 'note' => 'Set BED_MANAGEMENT_URL di .env untuk production.'];
    }

    /**
     * Ambil history bed IGD pasien berdasarkan no_reg dari SIMRS.
     * Digunakan di tab Verifikasi untuk menampilkan bed yang pernah ditempati.
     * GET /api/batal-ranap/{id}/bed-history
     */
    public function bedHistory(int $id): JsonResponse
    {
        $record = $this->service->findOrFail($id);

        $beds = [];
        $source = 'none';

        // Coba ambil dari RSUS DB
        if (config('services.rsus_db_enabled', false)) {
            try {
                $rows = \Illuminate\Support\Facades\DB::connection('rsus')
                    ->table('BI_Bed_Igd')
                    ->where('No_Reg', $record->no_reg)
                    ->orWhere('No_MR', $record->no_mr)
                    ->orderByDesc('Tgl_Masuk')
                    ->limit(10)
                    ->get();

                $beds = $rows->map(fn($r) => [
                    'bed_id'      => $r->No_Bed      ?? $r->Kode_Bed  ?? null,
                    'bed_code'    => $r->No_Bed      ?? $r->Kode_Bed  ?? null,
                    'ruangan'     => $r->Nama_Ruang  ?? null,
                    'bangsal'     => $r->Nama_Bangsal ?? null,
                    'tgl_masuk'   => $r->Tgl_Masuk   ?? null,
                    'tgl_keluar'  => $r->Tgl_Keluar  ?? null,
                    'status'      => $r->Status       ?? null,
                    'keterangan'  => $r->Keterangan   ?? null,
                ])->values()->all();

                $source = 'rsus_db';
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::warning('bed-history RSUS query failed: ' . $e->getMessage());
            }
        }

        // Fallback: kembalikan data dari record batal_ranap itu sendiri (bed_id + ruangan)
        if (empty($beds) && ($record->bed_id || $record->ruangan)) {
            $beds = [[
                'bed_id'     => $record->bed_id,
                'bed_code'   => $record->bed_id,
                'ruangan'    => $record->ruangan,
                'bangsal'    => $record->ruangan,
                'tgl_masuk'  => $record->tgl_daftar,
                'tgl_keluar' => null,
                'status'     => 'occupied',
                'keterangan' => 'Data dari entry Batal Ranap',
            ]];
            $source = 'local';
        }

        return response()->json([
            'data'    => $beds,
            'source'  => $source,
            'no_reg'  => $record->no_reg,
            'no_mr'   => $record->no_mr,
            'pasien'  => $record->nama_pasien,
        ]);
    }

    public function destroy(int $id): JsonResponse
    {
        $record = $this->service->findOrFail($id);
        ActivityLog::record('batal-ranap', 'delete',
            "Batal Ranap dihapus — {$record->no_reg}");

        $this->service->delete($id);

        return response()->json(['message' => 'Data berhasil dihapus.']);
    }
}
