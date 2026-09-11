<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('m_document_levels')->updateOrInsert(
            ['kode' => 'level-4'],
            [
                'nama_level' => 'Level IV',
                'nama_dokumen' => 'Dokumen Level IV : Form / Lembar Revisi',
                'prefix' => 'FM',
                'description' => 'Form pengajuan revisi dokumen master sebelum perubahan disahkan.',
                'is_active' => true,
                'sort_order' => 4,
            ],
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('m_document_levels')
            ->where('kode', 'level-4')
            ->delete();
    }
};
