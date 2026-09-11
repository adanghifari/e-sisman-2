<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('t_document', function (Blueprint $table) {
            $table->dropForeign(['m_proses_bisnis_id']);
            $table->dropForeign(['m_proses_fungsi_id']);
        });

        Schema::table('t_document', function (Blueprint $table) {
            $table->unsignedBigInteger('m_proses_bisnis_id')->nullable()->change();
            $table->unsignedBigInteger('m_proses_fungsi_id')->nullable()->change();
        });

        Schema::table('t_document', function (Blueprint $table) {
            $table->foreign('m_proses_bisnis_id')->references('id')->on('m_proses_bisnis')->restrictOnDelete();
            $table->foreign('m_proses_fungsi_id')->references('id')->on('m_proses_fungsi')->restrictOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $fallbackProcessId = DB::table('m_proses_bisnis')->orderBy('id')->value('id');
        $fallbackFunctionId = DB::table('m_proses_fungsi')->orderBy('id')->value('id');

        if ($fallbackProcessId !== null) {
            DB::table('t_document')
                ->whereNull('m_proses_bisnis_id')
                ->update(['m_proses_bisnis_id' => $fallbackProcessId]);
        }

        if ($fallbackFunctionId !== null) {
            DB::table('t_document')
                ->whereNull('m_proses_fungsi_id')
                ->update(['m_proses_fungsi_id' => $fallbackFunctionId]);
        }

        Schema::table('t_document', function (Blueprint $table) {
            $table->dropForeign(['m_proses_bisnis_id']);
            $table->dropForeign(['m_proses_fungsi_id']);
        });

        Schema::table('t_document', function (Blueprint $table) {
            $table->unsignedBigInteger('m_proses_bisnis_id')->nullable(false)->change();
            $table->unsignedBigInteger('m_proses_fungsi_id')->nullable(false)->change();
        });

        Schema::table('t_document', function (Blueprint $table) {
            $table->foreign('m_proses_bisnis_id')->references('id')->on('m_proses_bisnis')->restrictOnDelete();
            $table->foreign('m_proses_fungsi_id')->references('id')->on('m_proses_fungsi')->restrictOnDelete();
        });
    }
};
