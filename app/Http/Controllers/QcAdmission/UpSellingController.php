<?php

namespace App\Http\Controllers\QcAdmission;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Services\QcAdmission\UpSellingService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class UpSellingController extends Controller
{
    public function __construct(
        private readonly UpSellingService $service
    ) {}

    public function index(Request $request): JsonResponse
    {
        $data = $this->service->paginate($request->only([
            'search', 'date_from', 'date_to', 'status', 'per_page', 'page',
        ]));

        return response()->json($data);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'tanggal'            => 'required|string',
            'jam_input'          => 'required|string',
            'no_reg'             => 'required|string|max:20',
            'no_mr'              => 'nullable|string|max:20',
            'tgl_daftar'         => 'nullable|string',
            'nama_pasien'        => 'nullable|string|max:100',
            'jaminan'            => 'nullable|string|max:50',
            'nama_ruang'         => 'nullable|string|max:100',
            'nama_bangsal'       => 'nullable|string|max:100',
            'kelas'              => 'nullable|string|max:50',
            'rekomendasi_kelas'  => 'nullable|string|max:50',
            'kelas_diambil'      => 'nullable|string|max:50',
            'alasan'             => 'nullable|in:Naik Kelas,Perubahan Jaminan',
            'petugas'            => 'required|string|max:100',
            'status'             => 'nullable|in:Berhasil,Tidak Berhasil,Pending',
            'note'               => 'nullable|string|max:1000',
        ]);

        $record = $this->service->create($validated);

        ActivityLog::record('up-selling', 'create',
            "Up Selling {$record->nama_pasien} ({$record->no_reg}) — {$record->status}", null, null, $record->petugas);

        return response()->json(['data' => $record, 'message' => 'Data berhasil disimpan.'], 201);
    }

    public function show(int $id): JsonResponse
    {
        return response()->json(['data' => $this->service->findOrFail($id)]);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'rekomendasi_kelas' => 'nullable|string|max:50',
            'kelas_diambil'     => 'nullable|string|max:50',
            'alasan'            => 'nullable|string|max:255',
            'petugas'           => 'sometimes|string|max:100',
            'status'            => 'nullable|in:Berhasil,Tidak Berhasil,Pending',
            'note'              => 'nullable|string|max:255',
        ]);

        $record = $this->service->update($id, $validated);

        ActivityLog::record('up-selling', 'update',
            "Up Selling diupdate — {$record->nama_pasien} ({$record->no_reg})", null, null, $record->petugas);

        return response()->json(['data' => $record, 'message' => 'Data berhasil diperbarui.']);
    }

    public function destroy(int $id): JsonResponse
    {
        $record = $this->service->findOrFail($id);
        ActivityLog::record('up-selling', 'delete',
            "Up Selling dihapus — {$record->nama_pasien} ({$record->no_reg})");

        $this->service->delete($id);

        return response()->json(['message' => 'Data berhasil dihapus.']);
    }
}
