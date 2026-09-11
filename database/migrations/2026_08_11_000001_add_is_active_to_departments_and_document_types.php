<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('departments', function (Blueprint $table): void {
            $table->boolean('is_active')->default(true)->after('kode_department');
        });

        Schema::table('m_document_types', function (Blueprint $table): void {
            $table->boolean('is_active')->default(true)->after('nama_types');
        });
    }

    public function down(): void
    {
        Schema::table('m_document_types', function (Blueprint $table): void {
            $table->dropColumn('is_active');
        });

        Schema::table('departments', function (Blueprint $table): void {
            $table->dropColumn('is_active');
        });
    }
};
