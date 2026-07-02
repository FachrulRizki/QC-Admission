<?php

namespace App\Http\Controllers\QcAdmission;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Services\QcAdmission\EdukasiLanjutanService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class EdukasiLanjutanController extends Controller
{
    public function __construct(
        private readonly EdukasiLanjutanService $service
    ) {}

    /**
     * List / search sesi edukasi lanjutan.
     */
    public function index(Request $request): JsonResponse
    {
        $data = $this->service->paginate($request->only([
            'search', 'date_from', 'date_to', 'status', 'per_page', 'page',
        ]));

        return response()->json($data);
    }

    /**
     * Catat sesi edukasi lanjutan baru (dipanggil tombol "Tambah Sesi").
     */
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

    /**
     * Detail 1 sesi.
     */
    public function show(int $id): JsonResponse
    {
        return response()->json(['data' => $this->service->findOrFail($id)]);
    }

    /**
     * Update sesi (mis. ubah status Menunggu -> Selesai, atau koreksi data).
     */
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

    /**
     * Hapus sesi.
     */
    public function destroy(int $id): JsonResponse
    {
        $record = $this->service->findOrFail($id);

        ActivityLog::record('edukasi-lanjutan', 'delete',
            "Edukasi lanjutan dihapus — {$record->nama_pasien} ({$record->no_mr})");

        $this->service->delete($id);

        return response()->json(['message' => 'Data berhasil dihapus.']);
    }

    /**
     * Sesi yang masih menunggu bed.
     */
    public function pending(Request $request): JsonResponse
    {
        $data = $this->service->pending($request->only([
            'search', 'date_from', 'date_to', 'per_page', 'page',
        ]));

        return response()->json($data);
    }

    /**
     * Sync status dari RSUS — PLACEHOLDER, lihat catatan di bawah.
     */
    public function syncRsus(Request $request): JsonResponse
    {
        return response()->json([
            'message' => 'syncRsus belum diimplementasikan — perlu konfirmasi logic pencocokan bed.',
        ], 501);
    }
}