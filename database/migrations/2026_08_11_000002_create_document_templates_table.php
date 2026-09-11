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
        Schema::create('document_templates', function (Blueprint $table) {
            $table->id();
            $table->string('document_level', 50);
            $table->unsignedInteger('version_number')->default(1);
            $table->string('title')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('uploaded_by')->constrained('users')->restrictOnDelete();
            $table->boolean('is_active')->default(false);
            $table->string('active_template_key', 50)->nullable()->unique();
            $table->timestamp('activated_at')->nullable();
            $table->timestamps();

            $table->unique(['document_level', 'version_number']);
            $table->index(['document_level', 'is_active']);
            $table->index(['uploaded_by', 'created_at']);
        });

        Schema::create('document_template_files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_template_id')->constrained('document_templates')->cascadeOnDelete();
            $table->unsignedTinyInteger('file_order')->default(1);
            $table->string('disk')->default('local');
            $table->string('path_file');
            $table->string('original_file_name');
            $table->string('stored_file_name');
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            $table->timestamps();

            $table->unique(['document_template_id', 'file_order']);
            $table->index(['document_template_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('document_template_files');
        Schema::dropIfExists('document_templates');
    }
};
