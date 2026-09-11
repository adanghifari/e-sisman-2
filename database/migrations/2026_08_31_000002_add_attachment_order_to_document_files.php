<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('t_document_files', function (Blueprint $table): void {
            $table->unsignedInteger('attachment_order')->nullable()->after('attachment_title');
        });
    }

    public function down(): void
    {
        Schema::table('t_document_files', function (Blueprint $table): void {
            $table->dropColumn('attachment_order');
        });
    }
};
