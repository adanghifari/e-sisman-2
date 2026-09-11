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
            $table->foreignId('m_approval_flow_stage_id')
                ->nullable()
                ->after('stages')
                ->constrained('m_approval_flow_stages')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('t_approval', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('m_approval_flow_stage_id');
        });
    }
};
