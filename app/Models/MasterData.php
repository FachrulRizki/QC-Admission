<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MasterData extends Model
{
    protected $table    = 'qcw_master_data';
    protected $fillable = ['category', 'item', 'sort_order'];

    /** Ambil semua kategori sebagai array [category => [item, ...]] */
    public static function allGrouped(): array
    {
        return static::query()
            ->orderBy('category')
            ->orderBy('sort_order')
            ->orderBy('item')
            ->get()
            ->groupBy('category')
            ->map(fn($rows) => $rows->pluck('item')->values()->all())
            ->all();
    }
}
