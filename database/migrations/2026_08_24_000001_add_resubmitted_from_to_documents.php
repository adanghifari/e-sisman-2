<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('t_document', function (Blueprint $table): void {
            $table->foreignId('resubmitted_from')
                ->nullable()
                ->after('revised_from')
                ->constrained('t_document')
                ->restrictOnDelete();

            $table->index('nomor_dokumen', 't_document_nomor_dokumen_index');
        });
    }

    public function down(): void
    {
        Schema::table('t_document', function (Blueprint $table): void {
            $table->dropIndex('t_document_nomor_dokumen_index');
            $table->dropForeign(['resubmitted_from']);
            $table->dropColumn('resubmitted_from');
        });
    }
};
