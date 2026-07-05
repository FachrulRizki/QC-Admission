<?php

namespace App\Http\Controllers\QcAdmission;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Services\QcAdmission\EdukasiLanjutanService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class EdukasiLanjutanController extends Controller
{
    public function __construct(private readonly EdukasiLanjutanService $service) {}

    public function index(Request $request): JsonResponse
    {
        return response()->json(
            $this->service->paginate($request->only(['search', 'date_from', 'date_to', 'status', 'per_page', 'page']))
        );
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
            'note'                => 'nullable|string',
            'petugas'             => 'required|string|max:100',
            'keluarga_pasien'     => 'required|string|max:100',
            'ttd_keluarga_pasien' => 'nullable|string',
            'status'              => 'nullable|in:Menunggu,Selesai',
            'quality_control_id'  => 'nullable|integer|exists:quality_controls,id',
        ]);

        $record = $this->service->create($validated);
        ActivityLog::record('edukasi-lanjutan', 'create',
            "Sesi edukasi lanjutan — {$record->nama_pasien} ({$record->no_mr})");

        return response()->json(['data' => $record, 'message' => 'Sesi edukasi lanjutan berhasil dicatat.'], 201);
    }

    public function show(int $id): JsonResponse
    {
        return response()->json(['data' => $this->service->findOrFail($id)]);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'nama_pasien'         => 'nullable|string|max:100',
            'jaminan'             => 'nullable|string|max:50',
            'edukasi_kamar'       => 'nullable|string|max:100',
            'note'                => 'nullable|string',
            'petugas'             => 'sometimes|string|max:100',
            'keluarga_pasien'     => 'nullable|string|max:100',
            'ttd_keluarga_pasien' => 'nullable|string',
            'status'              => 'sometimes|in:Menunggu,Selesai',
        ]);

        $record = $this->service->update($id, $validated);
        ActivityLog::record('edukasi-lanjutan', 'update',
            "Edukasi lanjutan diupdate — {$record->nama_pasien} ({$record->no_mr})");

        return response()->json(['data' => $record, 'message' => 'Data berhasil diperbarui.']);
    }

    public function destroy(int $id): JsonResponse
    {
        $record = $this->service->findOrFail($id);
        ActivityLog::record('edukasi-lanjutan', 'delete',
            "Edukasi lanjutan dihapus — {$record->nama_pasien} ({$record->no_mr})");
        $this->service->delete($id);

        return response()->json(['message' => 'Data berhasil dihapus.']);
    }

    /** Update status ranap dari SIMRS. PATCH /api/edukasi-lanjutan/{id}/ranap */
    public function updateRanap(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate(['status_ranap' => 'required|in:Pindah Ranap']);

        $record = $this->service->findOrFail($id);
        $record->update([
            'status_ranap' => $validated['status_ranap'],
            'ranap_at'     => now(),
            'status'       => 'Selesai',
        ]);

        ActivityLog::record('edukasi-lanjutan', 'update',
            "Status ranap Edukasi {$record->nama_pasien} → {$validated['status_ranap']}");

        return response()->json(['data' => $record->fresh(), 'message' => 'Status ranap diperbarui.']);
    }

    public function pending(Request $request): JsonResponse
    {
        return response()->json(
            $this->service->pending($request->only(['search', 'date_from', 'date_to', 'per_page', 'page']))
        );
    }

    /** Sync status rawat inap dari SIMRS — implementasi setelah konfirmasi struktur tabel. */
    public function syncRsus(): JsonResponse
    {
        return response()->json(['message' => 'Sync SIMRS belum diimplementasikan.'], 501);
    }
}
