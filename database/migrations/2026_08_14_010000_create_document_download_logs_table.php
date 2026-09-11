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
        Schema::create('t_document_download_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('t_document_id')->constrained('t_document')->restrictOnDelete();
            $table->foreignId('t_document_file_id')->nullable()->constrained('t_document_files')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('downloaded_at');
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();

            $table->index(['t_document_id', 'downloaded_at']);
            $table->index(['user_id', 'downloaded_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('t_document_download_logs');
    }
};
