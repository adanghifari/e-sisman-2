<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('imported_existing_document_departments')) {
            return;
        }

        Schema::create('imported_existing_document_departments', function (Blueprint $table): void {
            $table->unsignedBigInteger('imported_existing_document_id');
            $table->unsignedBigInteger('department_id');

            $table->foreign('imported_existing_document_id', 'ied_depts_document_fk')
                ->references('id')
                ->on('imported_existing_documents')
                ->cascadeOnDelete();
            $table->foreign('department_id', 'ied_depts_department_fk')
                ->references('id')
                ->on('departments')
                ->restrictOnDelete();

            $table->primary(
                ['imported_existing_document_id', 'department_id'],
                'ied_departments_primary',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('imported_existing_document_departments');
    }
};
