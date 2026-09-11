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
            $table->string('official_preparer_name_snapshot')->nullable()->after('official_preparer_id');
            $table->string('official_preparer_position_snapshot')->nullable()->after('official_preparer_name_snapshot');
            $table->string('official_preparer_department_snapshot')->nullable()->after('official_preparer_position_snapshot');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('t_document', function (Blueprint $table): void {
            $table->dropColumn([
                'official_preparer_name_snapshot',
                'official_preparer_position_snapshot',
                'official_preparer_department_snapshot',
            ]);
        });
    }
};
