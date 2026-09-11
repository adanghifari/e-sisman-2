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
        Schema::table('t_approval', function (Blueprint $table): void {
            $table->string('stage_name_snapshot')->nullable();
            $table->unsignedInteger('stage_order_snapshot')->nullable();
            $table->string('approver_name_snapshot')->nullable();
            $table->string('approver_position_snapshot')->nullable();
            $table->string('approver_department_snapshot')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('t_approval', function (Blueprint $table): void {
            $table->dropColumn([
                'stage_name_snapshot',
                'stage_order_snapshot',
                'approver_name_snapshot',
                'approver_position_snapshot',
                'approver_department_snapshot',
            ]);
        });
    }
};
