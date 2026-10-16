<?php

namespace App\Http\Controllers\QcAdmission;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Services\QcAdmission\BooklistAdmissionService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class BooklistAdmissionController extends Controller
{
    public function __construct(
        private readonly BooklistAdmissionService $service,
    ) {}

    public function index(Request $request): JsonResponse
    {
        return response()->json(
            $this->service->paginate(
                $request->only(['search', 'status_pasien', 'date_from', 'date_to', 'per_page', 'page'])
            )
        );
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'tanggal_booklist' => 'required|string|max:30',
            'no_reg'           => 'nullable|string|max:30',
            'no_mr'            => 'nullable|string|max:30',
            'nama_pasien'      => 'nullable|string|max:150',
            'no_hp'            => 'nullable|string|max:50',
            'jaminan'          => 'nullable|string|max:100',
            'kelas'            => 'nullable|string|max:100',
            'dpjp'             => 'nullable|string|max:150',
            'diagnosa'         => 'nullable|string',
            'tanggal_op'       => 'nullable|string|max:30',
            'status_pasien'    => 'nullable|in:Bedah,Non Bedah',
            'catatan'          => 'nullable|string',
            'petugas'          => 'nullable|string|max:150',
        ]);

        $record = $this->service->create($validated);

        ActivityLog::record(
            'booklist-admission',
            'create',
            "Booklist Admission ditambahkan — {$record->nama_pasien} ({$record->no_reg})",
            null,
            null,
            $record->petugas,
        );

        return response()->json(['data' => $record, 'message' => 'Data berhasil disimpan.'], 201);
    }

    public function show(int $id): JsonResponse
    {
        return response()->json(['data' => $this->service->findOrFail($id)]);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'tanggal_booklist' => 'sometimes|string|max:30',
            'no_reg'           => 'nullable|string|max:30',
            'no_mr'            => 'nullable|string|max:30',
            'nama_pasien'      => 'nullable|string|max:150',
            'no_hp'            => 'nullable|string|max:50',
            'jaminan'          => 'nullable|string|max:100',
            'kelas'            => 'nullable|string|max:100',
            'dpjp'             => 'nullable|string|max:150',
            'diagnosa'         => 'nullable|string',
            'tanggal_op'       => 'nullable|string|max:30',
            'status_pasien'    => 'nullable|in:Bedah,Non Bedah',
            'catatan'          => 'nullable|string',
            'petugas'          => 'nullable|string|max:150',
        ]);

        $record = $this->service->update($id, $validated);

        ActivityLog::record(
            'booklist-admission',
            'update',
            "Booklist Admission diperbarui — {$record->nama_pasien} ({$record->no_reg})",
            null,
            null,
            $record->petugas,
        );

        return response()->json(['data' => $record, 'message' => 'Data berhasil diperbarui.']);
    }

    public function destroy(int $id): JsonResponse
    {
        $record = $this->service->findOrFail($id);

        ActivityLog::record(
            'booklist-admission',
            'delete',
            "Booklist Admission dihapus — {$record->nama_pasien} ({$record->no_reg})",
        );

        $this->service->delete($id);

        return response()->json(['message' => 'Data berhasil dihapus.']);
    }
}
