<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('imported_existing_documents')) {
            Schema::create('imported_existing_documents', function (Blueprint $table): void {
                $table->id();
                $table->string('document_state', 30)->default('obsolete');
                $table->string('obsolete_rule_type');
                $table->foreignId('m_document_level_id')->nullable()->constrained('m_document_levels')->restrictOnDelete();
                $table->foreignId('m_document_types_id')->nullable()->constrained('m_document_types')->restrictOnDelete();
                $table->foreignId('m_proses_bisnis_id')->nullable()->constrained('m_proses_bisnis')->restrictOnDelete();
                $table->foreignId('m_proses_fungsi_id')->nullable()->constrained('m_proses_fungsi')->restrictOnDelete();
                $table->foreignId('uploaded_by')->constrained('users')->restrictOnDelete();
                $table->string('nama_dokumen');
                $table->string('nomor_dokumen')->nullable();
                $table->string('nomor_revisi')->nullable();
                $table->date('tanggal_terbit')->nullable();
                $table->date('tanggal_obsolete')->nullable();
                $table->text('catatan')->nullable();
                $table->timestamps();

                $table->index('document_state');
                $table->index('obsolete_rule_type');
                $table->index('nomor_dokumen');
            });
        }

        if (! Schema::hasTable('imported_existing_document_files')) {
            Schema::create('imported_existing_document_files', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('imported_existing_document_id');
                $table->string('type_file');
                $table->string('path_file');
                $table->unsignedBigInteger('uploaded_by');
                $table->string('original_file_name');
                $table->string('stored_file_name');
                $table->unsignedBigInteger('file_size')->nullable();
                $table->timestamps();

                $table->index(['imported_existing_document_id', 'type_file'], 'imported_existing_files_document_type_index');
            });
        }

        $this->addFileForeignKeys();

        if (! Schema::hasTable('imported_existing_document_relations')) {
            Schema::create('imported_existing_document_relations', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('imported_existing_document_id');
                $table->unsignedBigInteger('related_imported_existing_document_id')->nullable();
                $table->unsignedBigInteger('related_document_id')->nullable();
                $table->string('relation_type');
                $table->text('keterangan')->nullable();
                $table->unsignedBigInteger('created_by');
                $table->timestamps();

                $table->index('relation_type');
            });
        }

        $this->addRelationForeignKeys();
        $this->addRelationCheckConstraints();
    }

    public function down(): void
    {
        Schema::dropIfExists('imported_existing_document_relations');
        Schema::dropIfExists('imported_existing_document_files');
        Schema::dropIfExists('imported_existing_documents');
    }

    private function addRelationCheckConstraints(): void
    {
        $driver = DB::connection()->getDriverName();

        if ($this->constraintExists('imported_existing_document_relations', 'iod_rel_one_target_chk')) {
            return;
        }

        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            DB::statement(
                'alter table imported_existing_document_relations add constraint iod_rel_one_target_chk check ((related_imported_existing_document_id is null) <> (related_document_id is null))',
            );
            DB::statement(
                'alter table imported_existing_document_relations add constraint iod_rel_no_self_chk check (related_imported_existing_document_id is null or imported_existing_document_id <> related_imported_existing_document_id)',
            );
        }

        if ($driver === 'pgsql') {
            DB::statement(
                'alter table imported_existing_document_relations add constraint iod_rel_one_target_chk check ((related_imported_existing_document_id is null) <> (related_document_id is null))',
            );
            DB::statement(
                'alter table imported_existing_document_relations add constraint iod_rel_no_self_chk check (related_imported_existing_document_id is null or imported_existing_document_id <> related_imported_existing_document_id)',
            );
        }
    }

    private function addFileForeignKeys(): void
    {
        if (! $this->foreignKeyExists('imported_existing_document_files', 'imported_existing_document_id')) {
            Schema::table('imported_existing_document_files', function (Blueprint $table): void {
                $table->foreign('imported_existing_document_id', 'iod_files_document_fk')
                    ->references('id')
                    ->on('imported_existing_documents')
                    ->cascadeOnDelete();
            });
        }

        if (! $this->foreignKeyExists('imported_existing_document_files', 'uploaded_by')) {
            Schema::table('imported_existing_document_files', function (Blueprint $table): void {
                $table->foreign('uploaded_by', 'iod_files_uploader_fk')
                    ->references('id')
                    ->on('users')
                    ->restrictOnDelete();
            });
        }
    }

    private function addRelationForeignKeys(): void
    {
        if (! $this->foreignKeyExists('imported_existing_document_relations', 'imported_existing_document_id')) {
            Schema::table('imported_existing_document_relations', function (Blueprint $table): void {
                $table->foreign('imported_existing_document_id', 'iod_rel_source_fk')
                    ->references('id')
                    ->on('imported_existing_documents')
                    ->cascadeOnDelete();
            });
        }

        if (! $this->foreignKeyExists('imported_existing_document_relations', 'related_imported_existing_document_id')) {
            Schema::table('imported_existing_document_relations', function (Blueprint $table): void {
                $table->foreign('related_imported_existing_document_id', 'iod_rel_imported_fk')
                    ->references('id')
                    ->on('imported_existing_documents')
                    ->restrictOnDelete();
            });
        }

        if (! $this->foreignKeyExists('imported_existing_document_relations', 'related_document_id')) {
            Schema::table('imported_existing_document_relations', function (Blueprint $table): void {
                $table->foreign('related_document_id', 'iod_rel_document_fk')
                    ->references('id')
                    ->on('t_document')
                    ->restrictOnDelete();
            });
        }

        if (! $this->foreignKeyExists('imported_existing_document_relations', 'created_by')) {
            Schema::table('imported_existing_document_relations', function (Blueprint $table): void {
                $table->foreign('created_by', 'iod_rel_creator_fk')
                    ->references('id')
                    ->on('users')
                    ->restrictOnDelete();
            });
        }
    }

    private function foreignKeyExists(string $table, string $column): bool
    {
        $driver = DB::connection()->getDriverName();

        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            return DB::table('information_schema.KEY_COLUMN_USAGE')
                ->where('TABLE_SCHEMA', DB::getDatabaseName())
                ->where('TABLE_NAME', $table)
                ->where('COLUMN_NAME', $column)
                ->whereNotNull('REFERENCED_TABLE_NAME')
                ->exists();
        }

        return false;
    }

    private function constraintExists(string $table, string $constraint): bool
    {
        $driver = DB::connection()->getDriverName();

        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            return DB::table('information_schema.TABLE_CONSTRAINTS')
                ->where('CONSTRAINT_SCHEMA', DB::getDatabaseName())
                ->where('TABLE_NAME', $table)
                ->where('CONSTRAINT_NAME', $constraint)
                ->exists();
        }

        return false;
    }
};
