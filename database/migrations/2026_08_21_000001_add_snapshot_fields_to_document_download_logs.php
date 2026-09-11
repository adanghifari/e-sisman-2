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
        Schema::table('t_document_download_logs', function (Blueprint $table): void {
            $table->string('document_name_snapshot')->nullable()->after('t_document_file_id');
            $table->string('document_number_snapshot')->nullable()->after('document_name_snapshot');
            $table->unsignedInteger('document_revision_snapshot')->nullable()->after('document_number_snapshot');
            $table->string('download_context')->nullable()->after('document_revision_snapshot');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('t_document_download_logs', function (Blueprint $table): void {
            $table->dropColumn([
                'document_name_snapshot',
                'document_number_snapshot',
                'document_revision_snapshot',
                'download_context',
            ]);
        });
    }
};
