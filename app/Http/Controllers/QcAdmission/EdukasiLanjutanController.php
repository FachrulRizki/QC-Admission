<?php

namespace App\Http\Controllers\QcAdmission;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\EdukasiLanjutan;
use App\Models\QualityControl;
use App\Services\QcAdmission\EdukasiLanjutanService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class EdukasiLanjutanController extends Controller
{
    public function __construct(
        private readonly EdukasiLanjutanService $service
    ) {}

    public function index(Request $request): JsonResponse
    {
        $data = $this->service->paginate($request->only([
            'search', 'month', 'year', 'per_page', 'page',
            'status', 'date_from', 'date_to',
        ]));

        return response()->json($data);
    }

    /**
     * Fetch QC records with status "Edukasi lanjutan" that are >= 2 hours old
     * and have no corresponding edukasi_lanjutan record yet (pending population).
     */
    public function pending(): JsonResponse
    {
        $twoHoursAgo = Carbon::now()->subHours(2);

        $qcPending = QualityControl::where('status', 'Edukasi lanjutan')
            ->where('created_at', '<=', $twoHoursAgo)
            ->whereNotExists(function ($query) {
                $query->select('id')
                    ->from('edukasi_lanjutans')
                    ->whereColumn('edukasi_lanjutans.quality_control_id', 'quality_controls.id');
            })
            ->get();

        return response()->json(['data' => $qcPending]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'tanggal'             => 'required|string',
            'no_mr'               => 'required|string|max:20',
            'no_reg'              => 'nullable|string|max:20',
            'nama_pasien'         => 'nullable|string|max:100',
            'jaminan'             => 'nullable|string|max:50',
            'bulan'               => 'required|string|max:20',
            'edukasi_kamar'       => 'nullable|string|max:100',
            'note'                => 'nullable|string|max:500',
            'petugas'             => 'required|string|max:100',
            'keluarga_pasien'     => 'nullable|string|max:100',
            'ttd_keluarga_pasien' => 'nullable|string', // base64 image
            'status'              => 'nullable|in:Menunggu,Selesai',
            'quality_control_id'  => 'nullable|integer|exists:quality_controls,id',
        ]);

        $record = $this->service->create($validated);

        ActivityLog::record('edukasi-lanjutan', 'create',
            "Edukasi Lanjutan {$record->nama_pasien} ({$record->no_mr})");

        return response()->json(['data' => $record, 'message' => 'Data berhasil disimpan.'], 201);
    }

    public function show(int $id): JsonResponse
    {
        return response()->json(['data' => $this->service->findOrFail($id)]);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'edukasi_kamar'       => 'nullable|string|max:100',
            'note'                => 'nullable|string|max:500',
            'petugas'             => 'sometimes|string|max:100',
            'keluarga_pasien'     => 'nullable|string|max:100',
            'ttd_keluarga_pasien' => 'nullable|string',
            'bulan'               => 'sometimes|string|max:20',
            'status'              => 'nullable|in:Menunggu,Selesai',
        ]);

        $record = $this->service->update($id, $validated);

        ActivityLog::record('edukasi-lanjutan', 'update',
            "Edukasi Lanjutan diupdate — {$record->nama_pasien} ({$record->no_mr})");

        return response()->json(['data' => $record, 'message' => 'Data berhasil diperbarui.']);
    }

    /**
     * Sync status Selesai dari DB RSUS.
     * Endpoint: GET /api/edukasi-lanjutan/sync-rsus
     * Dipanggil dari frontend secara periodik (setiap load halaman atau manual refresh).
     */
    public function syncRsus(): JsonResponse
    {
        $this->service->syncStatusFromRsus();
        return response()->json(['message' => 'Sync status dari RSUS selesai.']);
    }

    public function destroy(int $id): JsonResponse
    {
        $record = $this->service->findOrFail($id);
        ActivityLog::record('edukasi-lanjutan', 'delete',
            "Edukasi Lanjutan dihapus — {$record->nama_pasien} ({$record->no_mr})");

        $this->service->delete($id);

        return response()->json(['message' => 'Data berhasil dihapus.']);
    }
}
