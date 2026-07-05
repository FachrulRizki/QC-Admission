<?php

namespace App\Http\Controllers\QcAdmission;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Services\QcAdmission\QualityControlService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class QualityControlController extends Controller
{
    public function __construct(private readonly QualityControlService $service) {}

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
            'jam_input'           => 'required|string',
            'tgl_daftar'          => 'nullable|string',
            'jam_daftar'          => 'nullable|string',
            'no_mr'               => 'nullable|string|max:20',
            'no_reg'              => 'required|string|max:20',
            'nama_pasien'         => 'nullable|string|max:100',
            'jaminan'             => 'nullable|string|max:50',
            'status_ket'          => 'nullable|string|max:100',
            'edukasi_kamar'       => 'nullable|string|max:100',
            'durasi_tunggu'       => 'nullable|string|max:20',
            'note'                => 'nullable|string|max:255',
            'petugas'             => 'required|string|max:100',
            'status'              => 'required|in:Edukasi',
            'keluarga_pasien'     => 'nullable|string|max:100',
            'ttd_keluarga_pasien' => 'nullable|string',
        ]);

        $record = $this->service->create($validated);
        ActivityLog::record('quality-control', 'create', "QC {$record->nama_pasien} ({$record->no_reg})");

        return response()->json(['data' => $record, 'message' => 'Data berhasil disimpan.'], 201);
    }

    public function show(int $id): JsonResponse
    {
        return response()->json(['data' => $this->service->findOrFail($id)]);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'tanggal'             => 'sometimes|string',
            'jam_input'           => 'sometimes|string',
            'no_reg'              => 'sometimes|string|max:20',
            'nama_pasien'         => 'nullable|string|max:100',
            'jaminan'             => 'nullable|string|max:50',
            'edukasi_kamar'       => 'nullable|string|max:100',
            'durasi_tunggu'       => 'nullable|string|max:20',
            'note'                => 'nullable|string|max:255',
            'petugas'             => 'sometimes|string|max:100',
            'keluarga_pasien'     => 'nullable|string|max:100',
            'ttd_keluarga_pasien' => 'nullable|string',
        ]);

        $record = $this->service->update($id, $validated);
        ActivityLog::record('quality-control', 'update', "QC diupdate — {$record->nama_pasien} ({$record->no_reg})");

        return response()->json(['data' => $record, 'message' => 'Data berhasil diperbarui.']);
    }

    public function destroy(int $id): JsonResponse
    {
        $record = $this->service->findOrFail($id);
        ActivityLog::record('quality-control', 'delete', "QC dihapus — {$record->nama_pasien} ({$record->no_reg})");
        $this->service->delete($id);

        return response()->json(['message' => 'Data berhasil dihapus.']);
    }

    /** Update status ranap dari SIMRS. PATCH /api/quality-control/{id}/ranap */
    public function updateRanap(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate(['status_ranap' => 'required|in:Pindah Ranap']);

        $record = $this->service->findOrFail($id);
        $record->update(['status_ranap' => $validated['status_ranap'], 'ranap_at' => now()]);

        ActivityLog::record('quality-control', 'update',
            "Status ranap QC {$record->nama_pasien} → {$validated['status_ranap']}");

        return response()->json(['data' => $record->fresh(), 'message' => 'Status ranap diperbarui.']);
    }
}
