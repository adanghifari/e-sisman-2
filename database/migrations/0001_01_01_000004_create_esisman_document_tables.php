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
        Schema::create('t_document', function (Blueprint $table) {
            $table->id();
            $table->foreignId('m_status_document_id')->constrained('m_status_document')->restrictOnDelete();
            $table->foreignId('m_document_types_id')->constrained('m_document_types')->restrictOnDelete();
            $table->foreignId('m_proses_bisnis_id')->constrained('m_proses_bisnis')->restrictOnDelete();
            $table->foreignId('m_proses_fungsi_id')->constrained('m_proses_fungsi')->restrictOnDelete();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->string('nama_dokumen');
            $table->string('nomor_dokumen')->nullable();
            $table->unsignedInteger('nomor_revisi')->default(0);
            $table->text('catatan_revisi')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->date('tanggal_terbit')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();

            $table->index(['m_status_document_id', 'm_document_types_id']);
            $table->index(['m_proses_bisnis_id', 'm_proses_fungsi_id']);
            $table->index('user_id');
        });

        Schema::create('document_departments', function (Blueprint $table) {
            $table->foreignId('t_document_id')->constrained('t_document')->restrictOnDelete();
            $table->foreignId('department_id')->constrained('departments')->restrictOnDelete();

            $table->primary(['t_document_id', 'department_id']);
        });

        Schema::create('t_approval', function (Blueprint $table) {
            $table->id();
            $table->foreignId('t_document_id')->constrained('t_document')->restrictOnDelete();
            $table->foreignId('m_approval_status_id')->constrained('m_approval_status')->restrictOnDelete();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('role_id')->nullable()->constrained('roles')->restrictOnDelete();
            $table->foreignId('assigned_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('assigned_at');
            $table->timestamp('responded_at')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->string('stages')->nullable();
            $table->text('catatan')->nullable();

            $table->unique(['t_document_id', 'user_id', 'role_id'], 't_approval_document_user_role_unique');
        });

        Schema::create('t_document_files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('t_document_id')->constrained('t_document')->restrictOnDelete();
            $table->unsignedBigInteger('t_document_files_id')->nullable();
            $table->string('type_file');
            $table->string('path_file');
            $table->foreignId('uploaded_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('updated_at')->nullable();
            $table->string('original_file_name');
            $table->string('stored_file_name');
            $table->foreignId('source_file_id')->nullable()->constrained('t_document_files')->restrictOnDelete();
            $table->unsignedBigInteger('file_size')->nullable();

            $table->index('t_document_files_id');
            $table->index(['t_document_id', 'type_file']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('t_document_files');
        Schema::dropIfExists('t_approval');
        Schema::dropIfExists('document_departments');
        Schema::dropIfExists('t_document');
    }
};
