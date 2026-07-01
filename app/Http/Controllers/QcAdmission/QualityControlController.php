<?php

namespace App\Http\Controllers\QcAdmission;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Services\QcAdmission\QualityControlService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class QualityControlController extends Controller
{
    public function __construct(
        private readonly QualityControlService $service
    ) {}

    /**
     * List / search records.
     */
    public function index(Request $request): JsonResponse
    {
        $data = $this->service->paginate($request->only([
            'search', 'date_from', 'date_to', 'status', 'per_page', 'page',
        ]));

        return response()->json($data);
    }

    /**
     * Store a new QC record.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'tanggal'           => 'required|string',
            'jam_input'         => 'required|string',
            'tgl_daftar'        => 'nullable|string',
            'jam_daftar'        => 'nullable|string',
            'no_mr'             => 'nullable|string|max:20',
            'no_reg'            => 'required|string|max:20',
            'nama_pasien'       => 'nullable|string|max:100',
            'jaminan'           => 'nullable|string|max:50',
            'status_ket'        => 'nullable|string|max:100',
            'edukasi_kamar'     => 'nullable|string|max:100',
            'durasi_tunggu'     => 'nullable|string|max:20',
            'note'              => 'nullable|string|max:255',
            'petugas'           => 'required|string|max:100',
            'status'            => 'required|in:Edukasi',
            'keluarga_pasien'   => 'nullable|string|max:100',
            'ttd_keluarga_pasien' => 'nullable|string',
        ]);

        $record = $this->service->create($validated);

        ActivityLog::record('quality-control', 'create',
            "QC {$record->nama_pasien} ({$record->no_reg})");

        return response()->json(['data' => $record, 'message' => 'Data berhasil disimpan.'], 201);
    }

    /**
     * Show a single record.
     */
    public function show(int $id): JsonResponse
    {
        $record = $this->service->findOrFail($id);

        return response()->json(['data' => $record]);
    }

    /**
     * Update an existing record.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'tanggal'           => 'sometimes|string',
            'jam_input'         => 'sometimes|string',
            'no_reg'            => 'sometimes|string|max:20',
            'nama_pasien'       => 'nullable|string|max:100',
            'jaminan'           => 'nullable|string|max:50',
            'edukasi_kamar'     => 'nullable|string|max:100',
            'durasi_tunggu'     => 'nullable|string|max:20',
            'note'              => 'nullable|string|max:255',
            'petugas'           => 'sometimes|string|max:100',
            'status'            => 'sometimes|in:Edukasi',
            'keluarga_pasien'   => 'nullable|string|max:100',
            'ttd_keluarga_pasien' => 'nullable|string',
        ]);

        $record = $this->service->update($id, $validated);

        ActivityLog::record('quality-control', 'update',
            "QC diupdate — {$record->nama_pasien} ({$record->no_reg})");

        return response()->json(['data' => $record, 'message' => 'Data berhasil diperbarui.']);
    }

    /**
     * Delete a record.
     */
    public function destroy(int $id): JsonResponse
    {
        $record = $this->service->findOrFail($id);
        ActivityLog::record('quality-control', 'delete',
            "QC dihapus — {$record->nama_pasien} ({$record->no_reg})");

        $this->service->delete($id);

        return response()->json(['message' => 'Data berhasil dihapus.']);
    }
}
