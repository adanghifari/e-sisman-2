<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const REFERENCES = 'references';

    private const SUPERSEDED_BY = 'superseded_by';

    public function up(): void
    {
        if (! Schema::hasTable('document_relations')) {
            Schema::create('document_relations', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('source_document_id')->nullable()->constrained('t_document')->cascadeOnDelete();
                $table->foreignId('source_imported_existing_document_id')->nullable()->constrained('imported_existing_documents')->cascadeOnDelete();
                $table->foreignId('target_document_id')->nullable()->constrained('t_document')->restrictOnDelete();
                $table->foreignId('target_imported_existing_document_id')->nullable()->constrained('imported_existing_documents')->restrictOnDelete();
                $table->string('relation_type', 50);
                $table->text('keterangan')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->index(['source_document_id', 'relation_type'], 'document_relations_source_document_type_index');
                $table->index(['source_imported_existing_document_id', 'relation_type'], 'document_relations_source_imported_type_index');
                $table->index(['target_document_id', 'relation_type'], 'document_relations_target_document_type_index');
                $table->index(['target_imported_existing_document_id', 'relation_type'], 'document_relations_target_imported_type_index');
            });

            $this->addCheckConstraints();
        }

        $this->backfillWorkflowReferences();
        $this->backfillImportedExistingRelations();

        if (Schema::hasColumn('t_document', 'reference')) {
            Schema::table('t_document', function (Blueprint $table): void {
                $table->dropForeign(['reference']);
                $table->dropColumn('reference');
            });
        }

        Schema::dropIfExists('imported_existing_document_relations');
    }

    public function down(): void
    {
        if (! Schema::hasColumn('t_document', 'reference')) {
            Schema::table('t_document', function (Blueprint $table): void {
                $table->foreignId('reference')
                    ->nullable()
                    ->after('user_id')
                    ->constrained('t_document')
                    ->restrictOnDelete();
            });
        }

        if (! Schema::hasTable('imported_existing_document_relations')) {
            Schema::create('imported_existing_document_relations', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('imported_existing_document_id');
                $table->unsignedBigInteger('related_imported_existing_document_id')->nullable();
                $table->unsignedBigInteger('related_document_id')->nullable();
                $table->string('relation_type');
                $table->text('keterangan')->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();

                $table->foreign('imported_existing_document_id', 'ied_rel_source_fk')
                    ->references('id')
                    ->on('imported_existing_documents')
                    ->cascadeOnDelete();
                $table->foreign('related_imported_existing_document_id', 'ied_rel_imported_fk')
                    ->references('id')
                    ->on('imported_existing_documents')
                    ->restrictOnDelete();
                $table->foreign('related_document_id', 'ied_rel_document_fk')
                    ->references('id')
                    ->on('t_document')
                    ->restrictOnDelete();
                $table->foreign('created_by', 'ied_rel_creator_fk')
                    ->references('id')
                    ->on('users')
                    ->nullOnDelete();
                $table->index('relation_type');
            });
        }

        $this->restoreWorkflowReferences();
        $this->restoreImportedExistingRelations();

        Schema::dropIfExists('document_relations');
    }

    private function backfillWorkflowReferences(): void
    {
        if (! Schema::hasColumn('t_document', 'reference')) {
            return;
        }

        DB::table('t_document')
            ->whereNotNull('reference')
            ->select(['id', 'reference', 'user_id', 'created_at'])
            ->orderBy('id')
            ->chunkById(100, function ($documents): void {
                foreach ($documents as $document) {
                    DB::table('document_relations')->updateOrInsert(
                        [
                            'source_document_id' => $document->id,
                            'relation_type' => self::REFERENCES,
                        ],
                        [
                            'source_imported_existing_document_id' => null,
                            'target_document_id' => $document->reference,
                            'target_imported_existing_document_id' => null,
                            'keterangan' => 'Migrated from t_document.reference.',
                            'created_by' => $document->user_id,
                            'created_at' => $document->created_at ?? now(),
                            'updated_at' => now(),
                        ],
                    );
                }
            });
    }

    private function backfillImportedExistingRelations(): void
    {
        if (! Schema::hasTable('imported_existing_document_relations')) {
            return;
        }

        DB::table('imported_existing_document_relations')
            ->orderBy('id')
            ->chunkById(100, function ($relations): void {
                foreach ($relations as $relation) {
                    DB::table('document_relations')->updateOrInsert(
                        [
                            'source_imported_existing_document_id' => $relation->imported_existing_document_id,
                            'target_imported_existing_document_id' => $relation->related_imported_existing_document_id,
                            'target_document_id' => $relation->related_document_id,
                            'relation_type' => $relation->relation_type,
                        ],
                        [
                            'source_document_id' => null,
                            'keterangan' => $relation->keterangan,
                            'created_by' => $relation->created_by,
                            'created_at' => $relation->created_at ?? now(),
                            'updated_at' => $relation->updated_at ?? now(),
                        ],
                    );
                }
            });
    }

    private function restoreWorkflowReferences(): void
    {
        DB::table('document_relations')
            ->where('relation_type', self::REFERENCES)
            ->whereNotNull('source_document_id')
            ->whereNotNull('target_document_id')
            ->orderBy('id')
            ->chunkById(100, function ($relations): void {
                foreach ($relations as $relation) {
                    DB::table('t_document')
                        ->where('id', $relation->source_document_id)
                        ->update(['reference' => $relation->target_document_id]);
                }
            });
    }

    private function restoreImportedExistingRelations(): void
    {
        DB::table('document_relations')
            ->whereNotNull('source_imported_existing_document_id')
            ->orderBy('id')
            ->chunkById(100, function ($relations): void {
                foreach ($relations as $relation) {
                    DB::table('imported_existing_document_relations')->insert([
                        'imported_existing_document_id' => $relation->source_imported_existing_document_id,
                        'related_imported_existing_document_id' => $relation->target_imported_existing_document_id,
                        'related_document_id' => $relation->target_document_id,
                        'relation_type' => $relation->relation_type,
                        'keterangan' => $relation->keterangan,
                        'created_by' => $relation->created_by,
                        'created_at' => $relation->created_at,
                        'updated_at' => $relation->updated_at,
                    ]);
                }
            });
    }

    private function addCheckConstraints(): void
    {
        if (! in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb', 'pgsql'], true)) {
            return;
        }

        DB::statement(
            "alter table document_relations add constraint document_relations_one_source_chk check ((source_document_id is null) <> (source_imported_existing_document_id is null))",
        );
        DB::statement(
            "alter table document_relations add constraint document_relations_one_target_chk check ((target_document_id is null) <> (target_imported_existing_document_id is null))",
        );
        DB::statement(
            "alter table document_relations add constraint document_relations_no_self_document_chk check (source_document_id is null or target_document_id is null or source_document_id <> target_document_id)",
        );
        DB::statement(
            "alter table document_relations add constraint document_relations_no_self_imported_chk check (source_imported_existing_document_id is null or target_imported_existing_document_id is null or source_imported_existing_document_id <> target_imported_existing_document_id)",
        );
        DB::statement(
            "alter table document_relations add constraint document_relations_type_chk check (relation_type in ('".self::REFERENCES."', '".self::SUPERSEDED_BY."'))",
        );
    }
};
