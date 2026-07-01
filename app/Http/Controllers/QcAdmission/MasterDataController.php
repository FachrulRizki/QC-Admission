<?php

namespace App\Http\Controllers\QcAdmission;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * MasterDataController
 * Menyediakan semua data dropdown (master data) untuk frontend.
 * Data disimpan di cache (database) dan bisa diedit oleh admin.
 * Default values tersedia sebagai fallback jika cache kosong.
 */
class MasterDataController extends Controller
{
    private const CACHE_KEY = 'master_data_custom';

    public function index(): JsonResponse
    {
        return response()->json($this->getMasterData());
    }

    /**
     * Update satu item dalam satu kategori.
     * PUT /api/master-data/{category}
     * Body: { "items": ["item1", "item2", ...] }
     */
    public function update(Request $request, string $category): JsonResponse
    {
        $request->validate([
            'items'   => 'required|array',
            'items.*' => 'required|string|max:100',
        ]);

        $data = $this->getMasterData();

        if (! array_key_exists($category, $data)) {
            return response()->json(['message' => 'Kategori tidak ditemukan.'], 404);
        }

        $data[$category] = array_values(array_unique($request->items));
        $this->saveMasterData($data);

        ActivityLog::record('master-data', 'update', "Master data '{$category}' diperbarui");

        return response()->json(['message' => 'Master data berhasil diperbarui.', 'data' => $data]);
    }

    /**
     * Tambah item baru ke kategori.
     * POST /api/master-data
     * Body: { "category": "ruangan", "item": "Ruang Baru" }
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'category' => 'required|string',
            'item'     => 'required|string|max:100',
        ]);

        $data     = $this->getMasterData();
        $category = $request->category;

        if (! array_key_exists($category, $data)) {
            return response()->json(['message' => 'Kategori tidak ditemukan.'], 404);
        }

        if (in_array($request->item, $data[$category])) {
            return response()->json(['message' => 'Item sudah ada.'], 422);
        }

        $data[$category][] = $request->item;
        $this->saveMasterData($data);

        ActivityLog::record('master-data', 'create',
            "Item '{$request->item}' ditambahkan ke '{$category}'");

        return response()->json(['message' => 'Item berhasil ditambahkan.', 'data' => $data], 201);
    }

    /**
     * Hapus item dari kategori berdasarkan index.
     * DELETE /api/master-data/{category}/{index}
     */
    public function destroy(string $category, int $index): JsonResponse
    {
        $data = $this->getMasterData();

        if (! array_key_exists($category, $data)) {
            return response()->json(['message' => 'Kategori tidak ditemukan.'], 404);
        }

        if (! isset($data[$category][$index])) {
            return response()->json(['message' => 'Item tidak ditemukan.'], 404);
        }

        $item = $data[$category][$index];
        array_splice($data[$category], $index, 1);
        $this->saveMasterData($data);

        ActivityLog::record('master-data', 'delete',
            "Item '{$item}' dihapus dari '{$category}'");

        return response()->json(['message' => 'Item berhasil dihapus.', 'data' => $data]);
    }

    // ── Private helpers ────────────────────────────────────────────────────────

    private function getMasterData(): array
    {
        $custom = Cache::get(self::CACHE_KEY, []);
        $defaults = $this->defaultMasterData();

        // Merge: custom overrides default per category
        return array_merge($defaults, $custom);
    }

    private function saveMasterData(array $data): void
    {
        // Ambil hanya category yang berbeda dari default
        $defaults = $this->defaultMasterData();
        $toSave   = [];
        foreach ($data as $key => $val) {
            $toSave[$key] = $val; // simpan semua agar tidak ada data hilang
        }
        Cache::forever(self::CACHE_KEY, $toSave);
    }

    private function defaultMasterData(): array
    {
        return [
            'ket_bayar'        => $this->ketBayar(),
            'ruangan'          => $this->ruangan(),
            'kelas'            => $this->kelas(),
            'bangsal'          => $this->bangsal(),
            'keterangan_batal' => $this->keteranganBatal(),
            'status_ok'        => $this->statusOk(),
            'ket_up_selling'   => $this->ketUpSelling(),
            'status_ket_qc'    => $this->statusKetQc(),
            'note_kamar'       => $this->noteKamar(),
            'cara_masuk'       => $this->caraMasuk(),
            'diagnosa'         => $this->diagnosa(),
            'jaminan'          => $this->jaminan(),
        ];
    }

    private function ketBayar(): array
    {
        return ['BPJS', 'Umum', 'Asuransi', 'Jasa Raharja', 'BPJS Ketenagakerjaan', 'Gratis', 'Lainnya'];
    }

    private function ruangan(): array
    {
        return [
            'IGD Umum', 'IGD Bedah', 'IGD Anak', 'IGD Kebidanan',
            'Ruang Mawar', 'Ruang Anggrek', 'Ruang Dahlia', 'Ruang Flamboyan',
            'ICU', 'NICU', 'HCU', 'PICU',
            'Ruang Bersalin', 'Ruang Perina', 'OK',
        ];
    }

    private function kelas(): array
    {
        return ['Kelas VIP', 'Kelas 1', 'Kelas 2', 'Kelas 3', 'Suite Room', 'VVIP'];
    }

    private function bangsal(): array
    {
        return [
            'MAWAR', 'ANGGREK', 'DAHLIA', 'FLAMBOYAN',
            'PAHLAWAN ATAS', 'PAHLAWAN BAWAH',
            'KEBIDANAN', 'ANAK', 'BEDAH', 'INTERNIS',
            'ICU', 'NICU', 'HCU',
        ];
    }

    private function keteranganBatal(): array
    {
        return [
            'APS Alih RS Lain',
            'APS Rawat Jalan',
            'Saran Alih RS Lain',
            'Saran Konsul Poli',
            'Sisrute Tidak Dapat Kamar',
            'Batal Rawat',
            'Kamar Penuh',
            'Pasien Menolak',
            'DPJP Tidak Setuju',
            'Keluarga Menolak',
            'Kondisi Membaik',
        ];
    }

    private function statusOk(): array
    {
        return ['Bedah', 'Non Bedah'];
    }

    private function ketUpSelling(): array
    {
        return ['Naik Kelas', 'Perubahan Jaminan'];
    }

    private function statusKetQc(): array
    {
        return ['Belum Dapat Kamar', 'Antri Kamar', 'Sudah Dapat Kamar'];
    }

    private function noteKamar(): array
    {
        return [
            'Kelas 1 Bedah Laki-laki', 'Kelas 2 Bedah Laki-laki', 'Kelas 3 Bedah Laki-laki',
            'Kelas 1 Bedah Perempuan', 'Kelas 2 Bedah Perempuan', 'Kelas 3 Bedah Perempuan',
            'Kelas 1 Internis Laki-laki', 'Kelas 2 Internis Laki-laki', 'Kelas 3 Internis Laki-laki',
            'Kelas 1 Internis Perempuan', 'Kelas 2 Internis Perempuan', 'Kelas 3 Internis Perempuan',
            'Kelas 1 Onkologi Laki-laki', 'Kelas 2 Onkologi Laki-laki', 'Kelas 3 Onkologi Laki-laki',
            'Kelas 1 Onkologi Perempuan', 'Kelas 2 Onkologi Perempuan', 'Kelas 3 Onkologi Perempuan',
            'Kelas 1 Kebidanan', 'Kelas 2 Kebidanan', 'Kelas 3 Kebidanan',
            'Kelas 1 Anak', 'Kelas 2 Anak', 'Kelas 3 Anak',
            'Kelas VIP',
        ];
    }

    private function caraMasuk(): array
    {
        return ['IGD', 'Poli', 'Rujukan', 'Langsung'];
    }

    private function diagnosa(): array
    {
        return [
            'Hipertensi', 'Diabetes Mellitus', 'Stroke', 'Gagal Jantung', 'ISPA',
            'Pneumonia', 'Appendisitis', 'Fraktur', 'Demam Berdarah', 'Typhoid',
            'Gastroenteritis', 'Anemia', 'Asma', 'Epilepsi', 'Lainnya',
        ];
    }

    private function jaminan(): array
    {
        return ['BPJS', 'Umum', 'Asuransi', 'Jasa Raharja', 'BPJS Ketenagakerjaan', 'Gratis', 'Lainnya'];
    }
}
