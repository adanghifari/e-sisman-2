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
        Schema::create('document_final_artifacts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('t_document_id')->constrained('t_document')->cascadeOnDelete();
            $table->foreignId('source_document_file_id')->constrained('t_document_files')->restrictOnDelete();
            $table->string('artifact_type', 30);
            $table->unsignedInteger('generation_number');
            $table->string('generation_status', 30)->default('pending');
            $table->string('path_file');
            $table->string('generated_file_name');
            $table->string('checksum_sha256')->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            $table->foreignId('generated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('generated_at')->nullable();
            $table->text('generation_error')->nullable();
            $table->timestamps();

            $table->unique(['t_document_id', 'artifact_type', 'generation_number'], 'document_final_artifacts_document_type_generation_unique');
            $table->unique('path_file', 'document_final_artifacts_path_unique');
            $table->index(['t_document_id', 'artifact_type', 'generation_status'], 'document_final_artifacts_document_type_status_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('document_final_artifacts');
    }
};
