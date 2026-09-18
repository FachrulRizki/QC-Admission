<?php

namespace App\Http\Controllers\QcAdmission;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\MasterData;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class MasterDataController extends Controller
{
    /** GET /api/master-data — semua kategori + items */
    public function index(): JsonResponse
    {
        return response()->json(MasterData::allGrouped());
    }

    /**
     * POST /api/master-data/category
     * Buat kategori baru (kosong).
     */
    public function storeCategory(Request $request): JsonResponse
    {
        $request->validate([
            'label' => 'required|string|max:100',
        ]);

        // Buat key dari label: lowercase, spasi → underscore, strip non-alphanumeric
        $key = Str::slug($request->label, '_');
        $key = preg_replace('/[^a-z0-9_]/', '', $key);
        $key = trim($key, '_');

        if (empty($key)) {
            return response()->json(['message' => 'Label tidak valid untuk dijadikan key kategori.'], 422);
        }

        if (MasterData::where('category', $key)->exists()) {
            return response()->json(['message' => "Kategori '{$key}' sudah ada."], 422);
        }

        ActivityLog::record('master-data', 'create-category', "Kategori baru '{$key}' dibuat (label: {$request->label})");

        return response()->json([
            'message'  => 'Kategori berhasil dibuat.',
            'category' => [
                'key'   => $key,
                'label' => $request->label,
            ],
            'data'     => MasterData::allGrouped(),
        ], 201);
    }

    /**
     * DELETE /api/master-data/category/{category}
     * Hapus seluruh kategori beserta semua item-nya.
     */
    public function destroyCategory(string $category): JsonResponse
    {
        $deleted = MasterData::where('category', $category)->delete();

        ActivityLog::record('master-data', 'delete-category',
            "Kategori '{$category}' dihapus ({$deleted} item)");

        return response()->json([
            'message' => "Kategori '{$category}' dan semua item-nya berhasil dihapus.",
            'data'    => MasterData::allGrouped(),
        ]);
    }

    /**
     * POST /api/master-data
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'category' => 'required|string|max:50|regex:/^[a-z0-9_]+$/',
            'item'     => 'required|string|max:150',
        ]);

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
     * Replace seluruh item dalam kategori.
     */
    public function update(Request $request, string $category): JsonResponse
    {
        $request->validate([
            'items'   => 'required|array',
            'items.*' => 'required|string|max:150',
        ]);

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
     * Hapus satu item berdasarkan index urut.
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
