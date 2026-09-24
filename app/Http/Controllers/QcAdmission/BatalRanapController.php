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
            $this->service->paginate($request->only(['search', 'status_ok', 'status_closing', 'belum_closing', 'date_from', 'date_to', 'per_page', 'page']))
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
        Log::info("konfirmasiClosing: START", [
            'id'      => $id,
            'payload' => $request->all(),
            'ip'      => $request->ip(),
        ]);

        $record = $this->service->findOrFail($id);

        Log::info("konfirmasiClosing: record ditemukan", [
            'id'             => $record->id,
            'no_reg'         => $record->no_reg,
            'status_closing' => $record->status_closing,
            'bed_id'         => $record->bed_id,
        ]);

        if ($record->status_closing === 'Siap Closing') {
            Log::warning("konfirmasiClosing: 422 — record sudah Siap Closing", ['id' => $id]);
            return response()->json([
                'message' => 'Status closing sudah dikonfirmasi "Siap Closing" dan tidak dapat diubah.',
            ], 422);
        }

        $validated = $request->validate([
            'status_closing' => 'required|in:Siap Closing,Belum Siap Closing',
            'kode_bed'       => 'nullable|string|max:50',
        ]);

        Log::info("konfirmasiClosing: validasi lolos", ['validated' => $validated]);

        // Auto-resolve kode_bed dari No_Reg jika tidak dikirim dari frontend
        $kodeBed = $validated['kode_bed'] ?? null;

        if ($validated['status_closing'] === 'Siap Closing' && ! $kodeBed) {
            $kodeBed = $this->bedIgd->getKodeBedByNoReg($record->no_reg);
            Log::info("konfirmasiClosing: auto-lookup kode_bed untuk No_Reg={$record->no_reg} → " . ($kodeBed ?? 'NULL'));
        }

        Log::info("konfirmasiClosing: kode_bed resolved", ['kode_bed' => $kodeBed]);

        // update status bed
        $bedUpdateResult = null;
        if ($validated['status_closing'] === 'Siap Closing') {
            if (! $kodeBed) {
                $bedUpdateResult = [
                    'success' => true,
                    'source'  => 'none',
                    'message' => "Pasien No_Reg={$record->no_reg} tidak memiliki bed IGD (kemungkinan menunggu di luar). Bed tidak dibebaskan.",
                ];
                Log::info("konfirmasiClosing: tidak ada bed IGD untuk No_Reg={$record->no_reg} — skip release bed");
            } else {
                Log::info("konfirmasiClosing: mencoba release bed", ['kode_bed' => $kodeBed, 'no_reg' => $record->no_reg]);
                $tempRecord = clone $record;
                $tempRecord->bed_id = $kodeBed;
                $bedUpdateResult = $this->updateBedManagement($tempRecord);

                Log::info("konfirmasiClosing: hasil release bed", ['result' => $bedUpdateResult]);

                if (! $bedUpdateResult['success']) {
                    Log::warning("konfirmasiClosing: release bed GAGAL — closing DITOLAK", [
                        'no_reg'   => $record->no_reg,
                        'kode_bed' => $kodeBed,
                        'error'    => $bedUpdateResult['message'] ?? '-',
                        'source'   => $bedUpdateResult['source'] ?? '-',
                    ]);

                    return response()->json([
                        'data'       => $record->fresh(),
                        'message'    => 'Sistem Bed IGD sedang tidak dapat dihubungi. Silakan coba beberapa saat lagi atau hubungi petugas IT.',
                        'bed_update' => $this->sanitizeBedUpdate($bedUpdateResult),
                    ], 422);
                }
            }
        }

        // Simpan ke DB
        $updateData = ['status_closing' => $validated['status_closing']];
        if ($kodeBed) {
            $updateData['bed_id'] = $kodeBed;
        }

        Log::info("konfirmasiClosing: menyimpan ke DB", ['update_data' => $updateData]);

        try {
            $record->update($updateData);
            $record = $record->fresh();
            Log::info("konfirmasiClosing: DB update sukses", ['id' => $record->id, 'status_closing' => $record->status_closing]);
        } catch (\Exception $e) {
            Log::error("konfirmasiClosing: DB update GAGAL", [
                'id'    => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json(['message' => 'Gagal menyimpan data. Silakan coba lagi atau hubungi petugas IT.'], 500);
        }

        if ($validated['status_closing'] === 'Siap Closing') {
            $bedStatus = $bedUpdateResult['success'] ? 'berhasil' : 'gagal';
            $bedSource = $bedUpdateResult['source'] ?? '?';
            ActivityLog::record('batal-ranap', 'closing',
                "Closing {$record->no_reg} (Bed: {$record->bed_id}) → {$record->status_closing} | Bed IGD: {$bedStatus} [{$bedSource}]");
        } else {
            ActivityLog::record('batal-ranap', 'closing',
                "Closing {$record->no_reg} → {$record->status_closing}");
        }

        $message = 'Status closing berhasil disimpan.';
        if ($bedUpdateResult !== null && ($bedUpdateResult['source'] ?? '') !== 'none') {
            if ($bedUpdateResult['success']) {
                $message .= " Bed {$record->bed_id} berhasil dibebaskan via {$bedUpdateResult['source']}.";
            } else {
                $message .= " Catatan: bed {$record->bed_id} belum dapat dibebaskan otomatis (sistem Bed IGD tidak terjangkau). Harap informasikan ke petugas IT.";
            }
        }

        return response()->json([
            'data'       => $record,
            'message'    => $message,
            'bed_update' => $this->sanitizeBedUpdate($bedUpdateResult),
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

    /**
     * Sanitasi array bed_update sebelum dikirim ke response.
     * Hanya field aman yang dikirim ke frontend — raw exception/SQL tidak pernah dikirim.
     */
    private function sanitizeBedUpdate(?array $result): ?array
    {
        if ($result === null) return null;

        $safeMessages = [
            'Kode_Bed tidak boleh kosong.',
            'Gagal update status bed via database.',
            'Pasien tidak memiliki bed IGD',
        ];

        $rawMessage = $result['message'] ?? null;

        // Jika message adalah salah satu pesan aman, teruskan. Jika tidak, ganti dengan pesan generik.
        $isSafe = $rawMessage === null || collect($safeMessages)->contains(
            fn ($s) => str_contains($rawMessage, $s)
        );

        return [
            'success'  => (bool) ($result['success']  ?? false),
            'source'   => $result['source']   ?? null,
            'kode_bed' => $result['kode_bed'] ?? null,
            'message'  => $isSafe
                ? $rawMessage
                : 'Sistem Bed IGD tidak dapat diproses. Detail error telah dicatat.',
        ];
    }
}
