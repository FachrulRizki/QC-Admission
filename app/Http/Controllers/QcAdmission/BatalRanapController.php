<?php

namespace App\Http\Controllers\QcAdmission;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Services\QcAdmission\BatalRanapService;
use App\Services\QcAdmission\BedIgdService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class BatalRanapController extends Controller
{
    public function __construct(
        private readonly BatalRanapService $service,
        private readonly BedIgdService     $bedIgd,
    ) {}

    public function index(Request $request): JsonResponse
    {
        return response()->json(
            $this->service->paginate($request->only(['search', 'date_from', 'date_to', 'status_ok', 'per_page', 'page']))
        );
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'tanggal'            => 'required|string',
            'jam_input'          => 'required|string',
            'no_reg'             => 'required|string|max:20',
            'no_mr'              => 'nullable|string|max:20',
            'tgl_daftar'         => 'nullable|string',
            'jam_daftar'         => 'nullable|string',
            'nama_pasien'        => 'nullable|string|max:100',
            'jaminan'            => 'nullable|string|max:50',
            'keterangan_batal'   => 'required|string|max:100',
            'status_ok'          => 'nullable|in:Bedah,Non Bedah',
            'status_closing'     => 'nullable|in:Siap Closing,Belum Siap Closing',
            'ketersediaan_kamar' => 'nullable|string|max:100',
            'diagnosa'           => 'nullable|string|max:255',
            'note'               => 'nullable|string|max:1000',
            'petugas'            => 'required|string|max:100',
            'ruangan'            => 'nullable|string|max:100',
            'bed_id'             => 'nullable|string|max:50',
        ]);

        $record = $this->service->create($validated);
        ActivityLog::record('batal-ranap', 'create', "Batal Ranap {$record->no_reg} — {$record->keterangan_batal}", null, null, $record->petugas);

        return response()->json(['data' => $record, 'message' => 'Data berhasil disimpan.'], 201);
    }

    public function show(int $id): JsonResponse
    {
        return response()->json(['data' => $this->service->findOrFail($id)]);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $record = $this->service->findOrFail($id);

        if ($record->status_closing === 'Siap Closing') {
            return response()->json([
                'message' => 'Data sudah dikonfirmasi "Siap Closing" dan tidak dapat diedit.',
            ], 422);
        }

        $validated = $request->validate([
            'keterangan_batal'   => 'sometimes|string|max:100',
            'status_ok'          => 'nullable|in:Bedah,Non Bedah',
            'ketersediaan_kamar' => 'nullable|string|max:100',
            'diagnosa'           => 'nullable|string|max:255',
            'note'               => 'nullable|string|max:1000',
            'petugas'            => 'sometimes|string|max:100',
            'ruangan'            => 'nullable|string|max:100',
        ]);

        $record = $this->service->update($id, $validated);
        ActivityLog::record('batal-ranap', 'update', "Batal Ranap diupdate — {$record->no_reg}", null, null, $record->petugas);

        return response()->json(['data' => $record, 'message' => 'Data berhasil diperbarui.']);
    }

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

    /** Update status_closing. Jika "Siap Closing" → auto-lookup kode bed dari No_Reg → trigger Bed Management API. */
    public function konfirmasiClosing(Request $request, int $id): JsonResponse
    {
        $record = $this->service->findOrFail($id);

        if ($record->status_closing === 'Siap Closing') {
            return response()->json([
                'message' => 'Status closing sudah dikonfirmasi "Siap Closing" dan tidak dapat diubah.',
            ], 422);
        }

        $validated = $request->validate([
            'status_closing' => 'required|in:Siap Closing,Belum Siap Closing',
            'kode_bed'       => 'nullable|string|max:50',
        ]);

        // Auto-resolve kode_bed dari No_Reg jika tidak dikirim dari frontend
        $kodeBed = $validated['kode_bed'] ?? null;

        if ($validated['status_closing'] === 'Siap Closing' && ! $kodeBed) {
            $kodeBed = $this->bedIgd->getKodeBedByNoReg($record->no_reg);
            Log::info("konfirmasiClosing: auto-lookup kode_bed untuk No_Reg={$record->no_reg} → {$kodeBed}");
        }

        $updateData = ['status_closing' => $validated['status_closing']];
        if ($kodeBed) {
            $updateData['bed_id'] = $kodeBed;
        }

        $record->update($updateData);
        $record = $record->fresh();

        $bedUpdateResult = null;
        if ($validated['status_closing'] === 'Siap Closing') {
            if (! $kodeBed) {
                // Pasien tidak memiliki bed IGD — kemungkinan menunggu di rumah.
                $bedUpdateResult = [
                    'success' => true,
                    'source'  => 'none',
                    'message' => "Pasien No_Reg={$record->no_reg} tidak memiliki bed IGD (kemungkinan menunggu di luar). Bed tidak dibebaskan.",
                ];
                Log::info("konfirmasiClosing: tidak ada bed IGD untuk No_Reg={$record->no_reg} — pasien mungkin tunggu di rumah, skip release bed");
            } else {
                $bedUpdateResult = $this->updateBedManagement($record);
            }

            $bedStatus = $bedUpdateResult['success'] ? 'berhasil' : 'gagal';
            $bedSource = $bedUpdateResult['source'] ?? '?';
            ActivityLog::record('batal-ranap', 'closing',
                "Closing {$record->no_reg} (Bed: {$record->bed_id}) → {$record->status_closing} | Bed IGD: {$bedStatus} [{$bedSource}]");
        } else {
            ActivityLog::record('batal-ranap', 'closing',
                "Closing {$record->no_reg} → {$record->status_closing}");
        }

        $message = 'Status closing berhasil disimpan.';
        if ($bedUpdateResult !== null) {
            if ($bedUpdateResult['success']) {
                $message .= " Bed {$record->bed_id} berhasil dibebaskan via {$bedUpdateResult['source']}.";
            } else {
                $message .= ' ⚠️ ' . ($bedUpdateResult['message'] ?? 'Trigger Bed IGD gagal.');
            }
        }

        return response()->json([
            'data'       => $record,
            'message'    => $message,
            'bed_update' => $bedUpdateResult,
        ]);
    }

    /** Ambil daftar bed IGD pasien dari Bed IGD API / RSUS DB. GET /api/batal-ranap/{id}/bed-history */
    public function bedHistory(int $id): JsonResponse
    {
        $record = $this->service->findOrFail($id);
        $result = $this->bedIgd->getBedsByNoReg($record->no_reg);
        $beds   = $result['beds'];
        $source = $result['source'];

        // Fallback lokal: ambil dari field bed_id yang tersimpan di record
        if (empty($beds) && $record->bed_id) {
            $beds   = [[
                'kode_bed'       => $record->bed_id,
                'bed_id'         => $record->bed_id,
                'status'         => 'TERISI',
                'no_reg'         => $record->no_reg,
                'tanggal'        => $record->tgl_daftar,
                'updated_reg_at' => null,
            ]];
            $source = 'local';
        }

        return response()->json([
            'data'   => $beds,
            'source' => $source,
            'no_reg' => $record->no_reg,
            'no_mr'  => $record->no_mr,
            'pasien' => $record->nama_pasien,
        ]);
    }

    // public function destroy(int $id): JsonResponse
    // {
    //     $record = $this->service->findOrFail($id);
    //     ActivityLog::record('batal-ranap', 'delete', "Batal Ranap dihapus — {$record->no_reg}");
    //     $this->service->delete($id);

    //     return response()->json(['message' => 'Data berhasil dihapus.']);
    // }

    /** Delegasikan ke BedIgdService: API → RSUS DB → mock (sesuai konfigurasi .env). */
    private function updateBedManagement(object $record): array
    {
        return $this->bedIgd->releaseBed(
            kodeBed: $record->bed_id ?? '',
            noReg:   $record->no_reg ?? '',
        );
    }
}
