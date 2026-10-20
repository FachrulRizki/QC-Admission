<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private array $indexes = [
        'qcw_batal_ranaps'        => ['created_at' => 'idx_batal_created_at'],
        'qcw_up_sellings'         => ['created_at' => 'idx_upselling_created_at'],
        'qcw_alasans'             => ['created_at' => 'idx_alasan_created_at'],
        'qcw_booklist_admissions' => ['created_at' => 'idx_booklist_created_at'],
        'qcw_edukasi_lanjutans'   => [
            'quality_control_id' => 'idx_edukasi_qc_id',
            'no_reg'             => 'idx_edukasi_no_reg',
        ],
        'qcw_quality_controls'    => ['no_reg' => 'idx_qc_no_reg'],
    ];

    public function up(): void
    {
        foreach ($this->indexes as $table => $columns) {
            if (! Schema::hasTable($table)) continue;

            Schema::table($table, function (Blueprint $t) use ($table, $columns) {
                foreach ($columns as $column => $name) {
                    if (Schema::hasColumn($table, $column) && ! Schema::hasIndex($table, $name)) {
                        $t->index($column, $name);
                    }
                }
            });
        }
    }

    public function down(): void
    {
        foreach ($this->indexes as $table => $columns) {
            if (! Schema::hasTable($table)) continue;

            Schema::table($table, function (Blueprint $t) use ($table, $columns) {
                foreach ($columns as $name) {
                    if (Schema::hasIndex($table, $name)) {
                        $t->dropIndex($name);
                    }
                }
            });
        }
    }
};
