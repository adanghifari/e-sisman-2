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
            $table->foreignId('official_preparer_id')
                ->nullable()
                ->after('user_id')
                ->constrained('users')
                ->restrictOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('t_document', function (Blueprint $table): void {
            $table->dropForeign(['official_preparer_id']);
            $table->dropColumn('official_preparer_id');
        });
    }
};
