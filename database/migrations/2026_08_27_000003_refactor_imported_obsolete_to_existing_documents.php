<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->renameImportedObsoleteTables();
        $this->ensureImportedExistingColumns();
        $this->ensureImportedExistingForeignKeys();
        $this->ensureImportedExistingCheckConstraints();
        $this->createNumberingTables();
        $this->ensureDocumentBridgeColumn();
    }

    public function down(): void
    {
        Schema::dropIfExists('document_number_registry');
        Schema::dropIfExists('document_numbering_setups');

        if (Schema::hasColumn('t_document', 'imported_existing_source_id')) {
            Schema::table('t_document', function (Blueprint $table): void {
                $table->dropForeign(['imported_existing_source_id']);
                $table->dropColumn('imported_existing_source_id');
            });
        }
    }

    private function renameImportedObsoleteTables(): void
    {
        if (Schema::hasTable('imported_obsolete_document_relations') && ! Schema::hasTable('imported_existing_document_relations')) {
            Schema::rename('imported_obsolete_document_relations', 'imported_existing_document_relations');
        }

        if (Schema::hasTable('imported_obsolete_document_files') && ! Schema::hasTable('imported_existing_document_files')) {
            Schema::rename('imported_obsolete_document_files', 'imported_existing_document_files');
        }

        if (Schema::hasTable('imported_obsolete_documents') && ! Schema::hasTable('imported_existing_documents')) {
            Schema::rename('imported_obsolete_documents', 'imported_existing_documents');
        }
    }

    private function ensureImportedExistingColumns(): void
    {
        $this->dropKnownImportedObsoleteCheckConstraints();

        if (Schema::hasTable('imported_existing_documents') && ! Schema::hasColumn('imported_existing_documents', 'document_state')) {
            Schema::table('imported_existing_documents', function (Blueprint $table): void {
                $table->string('document_state', 30)->default('obsolete')->after('id')->index();
            });
        }

        if (Schema::hasTable('imported_existing_document_files') && Schema::hasColumn('imported_existing_document_files', 'imported_obsolete_document_id')) {
            Schema::table('imported_existing_document_files', function (Blueprint $table): void {
                $table->renameColumn('imported_obsolete_document_id', 'imported_existing_document_id');
            });
        }

        if (Schema::hasTable('imported_existing_document_relations')) {
            if (Schema::hasColumn('imported_existing_document_relations', 'imported_obsolete_document_id')) {
                Schema::table('imported_existing_document_relations', function (Blueprint $table): void {
                    $table->renameColumn('imported_obsolete_document_id', 'imported_existing_document_id');
                });
            }

            if (Schema::hasColumn('imported_existing_document_relations', 'related_imported_obsolete_document_id')) {
                Schema::table('imported_existing_document_relations', function (Blueprint $table): void {
                    $table->renameColumn('related_imported_obsolete_document_id', 'related_imported_existing_document_id');
                });
            }
        }
    }

    private function dropKnownImportedObsoleteCheckConstraints(): void
    {
        foreach (['iod_rel_one_target_chk', 'iod_rel_no_self_chk'] as $constraint) {
            if ($this->foreignKeyExists('imported_existing_document_relations', $constraint)) {
                DB::statement("alter table imported_existing_document_relations drop constraint {$constraint}");
            }
        }
    }

    private function ensureImportedExistingForeignKeys(): void
    {
        if (! Schema::hasTable('imported_existing_document_files') || ! Schema::hasTable('imported_existing_document_relations')) {
            return;
        }

        $this->dropKnownImportedObsoleteForeignKeys();

        Schema::table('imported_existing_document_files', function (Blueprint $table): void {
            $table->foreign('imported_existing_document_id', 'ied_files_document_fk')
                ->references('id')
                ->on('imported_existing_documents')
                ->cascadeOnDelete();
        });

        Schema::table('imported_existing_document_relations', function (Blueprint $table): void {
            $table->foreign('imported_existing_document_id', 'ied_rel_source_fk')
                ->references('id')
                ->on('imported_existing_documents')
                ->cascadeOnDelete();
            $table->foreign('related_imported_existing_document_id', 'ied_rel_imported_fk')
                ->references('id')
                ->on('imported_existing_documents')
                ->restrictOnDelete();
        });
    }

    private function createNumberingTables(): void
    {
        if (! Schema::hasTable('document_numbering_setups')) {
            Schema::create('document_numbering_setups', function (Blueprint $table): void {
                $table->id();
                $table->string('scope_identifier')->unique();
                $table->unsignedInteger('existing_start_number');
                $table->unsignedInteger('existing_end_number');
                $table->unsignedInteger('v2_start_number');
                $table->foreignId('configured_by')->constrained('users')->restrictOnDelete();
                $table->timestamp('configured_at');
                $table->timestamp('created_at')->nullable();
            });
        }

        if (! Schema::hasTable('document_number_registry')) {
            Schema::create('document_number_registry', function (Blueprint $table): void {
                $table->id();
                $table->string('document_number')->unique();
                $table->string('scope_identifier')->nullable()->index();
                $table->string('source_type', 60);
                $table->unsignedBigInteger('source_id')->nullable();
                $table->foreignId('registered_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('registered_at');
                $table->timestamp('created_at')->nullable();
            });
        }
    }

    private function ensureImportedExistingCheckConstraints(): void
    {
        $driver = DB::connection()->getDriverName();

        if (! in_array($driver, ['mysql', 'mariadb', 'pgsql'], true)) {
            return;
        }

        if (! $this->foreignKeyExists('imported_existing_document_relations', 'ied_rel_one_target_chk')) {
            DB::statement(
                'alter table imported_existing_document_relations add constraint ied_rel_one_target_chk check ((related_imported_existing_document_id is null) <> (related_document_id is null))',
            );
        }

        if (! $this->foreignKeyExists('imported_existing_document_relations', 'ied_rel_no_self_chk')) {
            DB::statement(
                'alter table imported_existing_document_relations add constraint ied_rel_no_self_chk check (related_imported_existing_document_id is null or imported_existing_document_id <> related_imported_existing_document_id)',
            );
        }
    }

    private function ensureDocumentBridgeColumn(): void
    {
        if (! Schema::hasColumn('t_document', 'imported_existing_source_id')) {
            Schema::table('t_document', function (Blueprint $table): void {
                $table->foreignId('imported_existing_source_id')
                    ->nullable()
                    ->after('revised_from')
                    ->constrained('imported_existing_documents')
                    ->restrictOnDelete();
            });
        }
    }

    private function dropKnownImportedObsoleteForeignKeys(): void
    {
        foreach ([
            ['imported_existing_document_files', 'iod_files_document_fk'],
            ['imported_existing_document_relations', 'iod_rel_source_fk'],
            ['imported_existing_document_relations', 'iod_rel_imported_fk'],
        ] as [$table, $foreign]) {
            if ($this->foreignKeyExists($table, $foreign)) {
                Schema::table($table, function (Blueprint $blueprint) use ($foreign): void {
                    $blueprint->dropForeign($foreign);
                });
            }
        }
    }

    private function foreignKeyExists(string $table, string $constraint): bool
    {
        if (! in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb'], true)) {
            return false;
        }

        return DB::table('information_schema.TABLE_CONSTRAINTS')
            ->where('CONSTRAINT_SCHEMA', DB::getDatabaseName())
            ->where('TABLE_NAME', $table)
            ->where('CONSTRAINT_NAME', $constraint)
            ->exists();
    }
};

