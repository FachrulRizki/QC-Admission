<?php

namespace App\Http\Controllers\QcAdmission;

use App\Http\Controllers\Controller;
use App\Services\QcAdmission\EdukasiLanjutanService;
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
        ]));

        return response()->json($data);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'tanggal'      => 'required|string',
            'no_mr'        => 'required|string|max:20',
            'nama_pasien'  => 'nullable|string|max:100',
            'bulan'        => 'required|string|max:20',
            'catatan'      => 'nullable|string|max:500',
            'petugas'      => 'required|string|max:100',
        ]);

        $record = $this->service->create($validated);

        return response()->json(['data' => $record, 'message' => 'Data berhasil disimpan.'], 201);
    }

    public function show(int $id): JsonResponse
    {
        return response()->json(['data' => $this->service->findOrFail($id)]);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'catatan' => 'nullable|string|max:500',
            'bulan'   => 'sometimes|string|max:20',
            'petugas' => 'sometimes|string|max:100',
        ]);

        $record = $this->service->update($id, $validated);

        return response()->json(['data' => $record, 'message' => 'Data berhasil diperbarui.']);
    }

    public function destroy(int $id): JsonResponse
    {
        $this->service->delete($id);

        return response()->json(['message' => 'Data berhasil dihapus.']);
    }
}
