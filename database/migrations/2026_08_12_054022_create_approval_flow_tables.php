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
        Schema::create('m_approval_flows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('m_document_types_id')->constrained('m_document_types')->restrictOnDelete();
            $table->string('nama_flow');
            $table->timestamps();

            $table->unique('m_document_types_id');
        });

        Schema::create('m_approval_flow_stages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('m_approval_flow_id')->constrained('m_approval_flows')->cascadeOnDelete();
            $table->unsignedInteger('stage_order');
            $table->string('nama_tahap');
            $table->timestamps();

            $table->unique(['m_approval_flow_id', 'stage_order']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('m_approval_flow_stages');
        Schema::dropIfExists('m_approval_flows');
    }
};
