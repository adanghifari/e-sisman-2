<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('t_document_files', function (Blueprint $table): void {
            $table->string('document_number')->nullable()->after('type_file');
            $table->index('document_number', 't_document_files_document_number_index');
            $table->index(['type_file', 'document_number'], 't_document_files_type_document_number_index');
        });

        $this->backfillMainDocumentFileNumbers();
        $this->backfillRevisionFormNumbers();
    }

    public function down(): void
    {
        Schema::table('t_document_files', function (Blueprint $table): void {
            $table->dropIndex('t_document_files_document_number_index');
            $table->dropIndex('t_document_files_type_document_number_index');
            $table->dropColumn('document_number');
        });
    }

    private function backfillMainDocumentFileNumbers(): void
    {
        DB::table('t_document_files')
            ->join('t_document', 't_document_files.t_document_id', '=', 't_document.id')
            ->whereIn('t_document_files.type_file', ['filled_template', 'revision_content', 'imported_document'])
            ->whereNull('t_document_files.document_number')
            ->whereNotNull('t_document.nomor_dokumen')
            ->select([
                't_document_files.id',
                't_document.nomor_dokumen',
            ])
            ->orderBy('t_document_files.id')
            ->chunkById(100, function ($files): void {
                foreach ($files as $file) {
                    DB::table('t_document_files')
                        ->where('id', $file->id)
                        ->update([
                            'document_number' => $file->nomor_dokumen,
                        ]);
                }
            }, 't_document_files.id', 'id');
    }

    private function backfillRevisionFormNumbers(): void
    {
        DB::table('t_document_files')
            ->join('t_document', 't_document_files.t_document_id', '=', 't_document.id')
            ->join('m_document_levels', 't_document.m_document_level_id', '=', 'm_document_levels.id')
            ->where('t_document_files.type_file', 'revision_form')
            ->whereNull('t_document_files.document_number')
            ->whereNotNull('t_document.nomor_dokumen')
            ->select([
                't_document_files.id',
                't_document.nomor_dokumen',
                'm_document_levels.kode',
            ])
            ->orderBy('t_document_files.id')
            ->chunkById(100, function ($files): void {
                foreach ($files as $file) {
                    DB::table('t_document_files')
                        ->where('id', $file->id)
                        ->update([
                            'document_number' => $this->revisionFormNumber(
                                (string) $file->nomor_dokumen,
                                (string) $file->kode,
                            ),
                        ]);
                }
            }, 't_document_files.id', 'id');
    }

    private function revisionFormNumber(string $documentNumber, string $levelKey): string
    {
        $prefix = match ($levelKey) {
            'level-1' => 'FMSM',
            'level-2' => 'FMPS',
            'level-3' => 'FMIK',
            default => 'FM',
        };

        $segments = collect(explode('-', $documentNumber))
            ->filter()
            ->values();

        if ($segments->isNotEmpty()) {
            $segments->shift();
        }

        return collect([$prefix])
            ->merge($segments)
            ->push('01')
            ->filter()
            ->implode('-');
    }
};
