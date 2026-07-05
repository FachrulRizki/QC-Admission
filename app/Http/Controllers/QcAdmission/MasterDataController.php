<?php

namespace App\Http\Controllers\QcAdmission;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\MasterData;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * MasterDataController
 * Data disimpan di tabel `master_data` (persisten, tidak hilang saat cache clear).
 * Seed default: php artisan db:seed --class=MasterDataSeeder
 */
class MasterDataController extends Controller
{
    /** GET /api/master-data — semua kategori */
    public function index(): JsonResponse
    {
        return response()->json(MasterData::allGrouped());
    }

    /**
     * POST /api/master-data
     * Body: { "category": "ruangan", "item": "Ruang Baru" }
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'category' => 'required|string|max:50',
            'item'     => 'required|string|max:150',
        ]);

        // Pastikan kategori valid (harus sudah ada minimal 1 item)
        if (! MasterData::where('category', $request->category)->exists()) {
            return response()->json(['message' => 'Kategori tidak ditemukan.'], 404);
        }

        if (MasterData::where('category', $request->category)->where('item', $request->item)->exists()) {
            return response()->json(['message' => 'Item sudah ada.'], 422);
        }

        $maxOrder = MasterData::where('category', $request->category)->max('sort_order') ?? -1;

        MasterData::create([
            'category'   => $request->category,
            'item'       => $request->item,
            'sort_order' => $maxOrder + 1,
        ]);

        ActivityLog::record('master-data', 'create',
            "Item '{$request->item}' ditambahkan ke '{$request->category}'");

        return response()->json([
            'message' => 'Item berhasil ditambahkan.',
            'data'    => MasterData::allGrouped(),
        ], 201);
    }

    /**
     * PUT /api/master-data/{category}
     * Body: { "items": ["item1", "item2", ...] } — replace seluruh isi kategori
     */
    public function update(Request $request, string $category): JsonResponse
    {
        $request->validate([
            'items'   => 'required|array',
            'items.*' => 'required|string|max:150',
        ]);

        if (! MasterData::where('category', $category)->exists()) {
            return response()->json(['message' => 'Kategori tidak ditemukan.'], 404);
        }

        // Hapus semua item lama, insert ulang
        MasterData::where('category', $category)->delete();

        $rows = [];
        foreach (array_values(array_unique($request->items)) as $order => $item) {
            $rows[] = [
                'category'   => $category,
                'item'       => $item,
                'sort_order' => $order,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }
        if ($rows) MasterData::insert($rows);

        ActivityLog::record('master-data', 'update', "Master data '{$category}' diperbarui");

        return response()->json([
            'message' => 'Master data berhasil diperbarui.',
            'data'    => MasterData::allGrouped(),
        ]);
    }

    /**
     * DELETE /api/master-data/{category}/{index}
     */
    public function destroy(string $category, int $index): JsonResponse
    {
        $items = MasterData::where('category', $category)
            ->orderBy('sort_order')->orderBy('item')
            ->get();

        if (! isset($items[$index])) {
            return response()->json(['message' => 'Item tidak ditemukan.'], 404);
        }

        $item = $items[$index];
        $item->delete();

        ActivityLog::record('master-data', 'delete',
            "Item '{$item->item}' dihapus dari '{$category}'");

        return response()->json([
            'message' => 'Item berhasil dihapus.',
            'data'    => MasterData::allGrouped(),
        ]);
    }
}
