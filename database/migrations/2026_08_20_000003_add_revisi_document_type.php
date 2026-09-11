<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('m_document_types')->updateOrInsert(
            ['nama_types' => 'Revisi'],
            ['nama_types' => 'Revisi', 'is_active' => true],
        );
    }

    public function down(): void
    {
        DB::table('m_document_types')
            ->where('nama_types', 'Revisi')
            ->whereNotExists(function ($query): void {
                $query
                    ->selectRaw('1')
                    ->from('t_document')
                    ->whereColumn('t_document.m_document_types_id', 'm_document_types.id');
            })
            ->delete();
    }
};
