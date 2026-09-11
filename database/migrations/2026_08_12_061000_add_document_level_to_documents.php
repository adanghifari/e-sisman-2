<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('t_document', function (Blueprint $table): void {
            $table->foreignId('m_document_level_id')
                ->nullable()
                ->after('id')
                ->constrained('m_document_levels')
                ->restrictOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('t_document', function (Blueprint $table): void {
            $table->dropForeign(['m_document_level_id']);
            $table->dropColumn('m_document_level_id');
        });
    }
};
