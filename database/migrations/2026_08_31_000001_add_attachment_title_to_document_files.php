<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('t_document_files', function (Blueprint $table): void {
            $table->string('attachment_title')->nullable()->after('type_file');
        });
    }

    public function down(): void
    {
        Schema::table('t_document_files', function (Blueprint $table): void {
            $table->dropColumn('attachment_title');
        });
    }
};
