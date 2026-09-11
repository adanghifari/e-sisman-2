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
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('nama_role')->unique();
        });

        Schema::create('m_status_document', function (Blueprint $table) {
            $table->id();
            $table->string('nama_status')->unique();
        });

        Schema::create('m_approval_status', function (Blueprint $table) {
            $table->id();
            $table->string('kode_status')->unique();
            $table->string('nama_status');
        });

        Schema::create('m_document_types', function (Blueprint $table) {
            $table->id();
            $table->string('nama_types')->unique();
        });

        Schema::create('m_proses_bisnis', function (Blueprint $table) {
            $table->id();
            $table->string('kode')->unique();
            $table->string('nama_proses_bisnis');
            $table->boolean('is_active')->default(true);
        });

        Schema::create('m_proses_fungsi', function (Blueprint $table) {
            $table->id();
            $table->string('kode')->unique();
            $table->string('nama_proses_fungsi')->unique();
            $table->boolean('is_active')->default(true);
        });

        Schema::create('user_roles', function (Blueprint $table) {
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('role_id')->constrained('roles')->restrictOnDelete();

            $table->primary(['user_id', 'role_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_roles');
        Schema::dropIfExists('m_proses_fungsi');
        Schema::dropIfExists('m_proses_bisnis');
        Schema::dropIfExists('m_document_types');
        Schema::dropIfExists('m_approval_status');
        Schema::dropIfExists('m_status_document');
        Schema::dropIfExists('roles');
    }
};
