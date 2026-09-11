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
            $table->string('request_type', 50)->nullable()->after('revised_from');
            $table->index('request_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('t_document', function (Blueprint $table): void {
            $table->dropIndex(['request_type']);
            $table->dropColumn('request_type');
        });
    }
};
