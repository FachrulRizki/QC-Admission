<?php

namespace App\Http\Controllers\QcAdmission;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Services\QcAdmission\BatalRanapService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

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
            'keterangan_batal'    => 'required|string|max:100',
            'status_ok'           => 'nullable|in:Bedah,Non Bedah',
            'ketersediaan_kamar'  => 'nullable|string|max:100',
            'diagnosa'            => 'nullable|string|max:255',
            'note'                => 'nullable|string|max:255',
            'petugas'             => 'required|string|max:100',
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
            'ketersediaan_kamar' => 'nullable|string|max:100',
            'diagnosa'           => 'nullable|string|max:255',
            'note'               => 'nullable|string|max:255',
            'petugas'            => 'sometimes|string|max:100',
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

    public function destroy(int $id): JsonResponse
    {
        $record = $this->service->findOrFail($id);
        ActivityLog::record('batal-ranap', 'delete',
            "Batal Ranap dihapus — {$record->no_reg}");

        $this->service->delete($id);

        return response()->json(['message' => 'Data berhasil dihapus.']);
    }
}
