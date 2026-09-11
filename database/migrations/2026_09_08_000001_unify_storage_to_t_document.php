<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $driver = DB::connection()->getDriverName();
        $isMySql = in_array($driver, ['mysql', 'mariadb'], true);

        // 1. Modifikasi t_document
        if (Schema::hasTable('t_document')) {
            if ($isMySql) {
                $docFks = collect(DB::select("
                    SELECT CONSTRAINT_NAME 
                    FROM information_schema.TABLE_CONSTRAINTS 
                    WHERE TABLE_SCHEMA = DATABASE() 
                      AND TABLE_NAME = 't_document' 
                      AND CONSTRAINT_TYPE = 'FOREIGN KEY'
                "))->pluck('CONSTRAINT_NAME')->all();

                if (in_array('t_document_imported_existing_source_id_foreign', $docFks, true)) {
                    Schema::table('t_document', function (Blueprint $table): void {
                        $table->dropForeign('t_document_imported_existing_source_id_foreign');
                    });
                }

                if (in_array('t_document_m_document_types_id_foreign', $docFks, true)) {
                    Schema::table('t_document', function (Blueprint $table): void {
                        $table->dropForeign('t_document_m_document_types_id_foreign');
                    });
                }
            } else {
                try {
                    Schema::table('t_document', function (Blueprint $table): void {
                        if (Schema::hasColumn('t_document', 'imported_existing_source_id')) {
                            $table->dropForeign(['imported_existing_source_id']);
                        }
                        if (Schema::hasColumn('t_document', 'm_document_types_id')) {
                            $table->dropForeign(['m_document_types_id']);
                        }
                    });
                } catch (\Throwable) {
                    // ignore if foreign key doesn't exist
                }
            }

            if (Schema::hasColumn('t_document', 'm_document_types_id')) {
                Schema::table('t_document', function (Blueprint $table): void {
                    $table->unsignedBigInteger('m_document_types_id')->nullable()->change();
                    $table->foreign('m_document_types_id')->references('id')->on('m_document_types')->restrictOnDelete();
                });
            }

            Schema::table('t_document', function (Blueprint $table): void {
                if (! Schema::hasColumn('t_document', 'origin')) {
                    $table->string('origin', 30)->default('workflow')->after('id')->index();
                }

                if (! Schema::hasColumn('t_document', 'catatan')) {
                    $table->text('catatan')->nullable()->after('catatan_revisi');
                }

                if (Schema::hasColumn('t_document', 'nomor_revisi')) {
                    $table->string('nomor_revisi', 50)->default('00.00')->change();
                }
            });
        }

        $importedDocumentIdMap = $this->migrateImportedExistingDocumentsToTDocument();

        if (Schema::hasTable('t_document') && Schema::hasColumn('t_document', 'imported_existing_source_id')) {
            Schema::table('t_document', function (Blueprint $table): void {
                $table->dropColumn('imported_existing_source_id');
            });
        }

        // 2. Modifikasi document_relations
        if (Schema::hasTable('document_relations')) {
            $this->migrateImportedExistingRelationsToDocumentRelations($importedDocumentIdMap);

            if ($isMySql) {
                $checkConstraints = collect(DB::select("
                    SELECT CONSTRAINT_NAME 
                    FROM information_schema.TABLE_CONSTRAINTS 
                    WHERE TABLE_SCHEMA = DATABASE() 
                      AND TABLE_NAME = 'document_relations' 
                      AND CONSTRAINT_TYPE = 'CHECK'
                "))->pluck('CONSTRAINT_NAME')->all();

                foreach (['document_relations_one_source_chk', 'document_relations_one_target_chk', 'document_relations_no_self_imported_chk'] as $chk) {
                    if (in_array($chk, $checkConstraints, true)) {
                        try {
                            DB::statement("alter table document_relations drop constraint {$chk}");
                        } catch (\Throwable) {
                            // ignore
                        }
                    }
                }

                $relFks = collect(DB::select("
                    SELECT CONSTRAINT_NAME 
                    FROM information_schema.TABLE_CONSTRAINTS 
                    WHERE TABLE_SCHEMA = DATABASE() 
                      AND TABLE_NAME = 'document_relations' 
                      AND CONSTRAINT_TYPE = 'FOREIGN KEY'
                "))->pluck('CONSTRAINT_NAME')->all();

                if (in_array('document_relations_source_imported_existing_document_id_foreign', $relFks, true)) {
                    Schema::table('document_relations', function (Blueprint $table): void {
                        $table->dropForeign('document_relations_source_imported_existing_document_id_foreign');
                    });
                }

                if (in_array('document_relations_target_imported_existing_document_id_foreign', $relFks, true)) {
                    Schema::table('document_relations', function (Blueprint $table): void {
                        $table->dropForeign('document_relations_target_imported_existing_document_id_foreign');
                    });
                }
            } else {
                try {
                    Schema::table('document_relations', function (Blueprint $table): void {
                        if (Schema::hasColumn('document_relations', 'source_imported_existing_document_id')) {
                            $table->dropForeign(['source_imported_existing_document_id']);
                        }
                        if (Schema::hasColumn('document_relations', 'target_imported_existing_document_id')) {
                            $table->dropForeign(['target_imported_existing_document_id']);
                        }
                    });
                } catch (\Throwable) {
                    // ignore
                }
            }

            Schema::table('document_relations', function (Blueprint $table): void {
                try {
                    $table->dropIndex('document_relations_source_imported_type_index');
                } catch (\Throwable) {
                }
                try {
                    $table->dropIndex('document_relations_target_imported_type_index');
                } catch (\Throwable) {
                }

                if (Schema::hasColumn('document_relations', 'source_imported_existing_document_id')) {
                    $table->dropColumn('source_imported_existing_document_id');
                }

                if (Schema::hasColumn('document_relations', 'target_imported_existing_document_id')) {
                    $table->dropColumn('target_imported_existing_document_id');
                }
            });
        }

        // 3. Drop tabel terpisah imported_existing_*
        Schema::dropIfExists('imported_existing_document_relations');
        Schema::dropIfExists('imported_existing_document_departments');
        Schema::dropIfExists('imported_existing_document_files');
        Schema::dropIfExists('imported_existing_documents');
    }

    public function down(): void
    {
        // Rollback via migrate:fresh
    }

    /**
     * @return array<int, int>
     */
    private function migrateImportedExistingDocumentsToTDocument(): array
    {
        if (! Schema::hasTable('imported_existing_documents') || ! Schema::hasTable('t_document')) {
            return [];
        }

        if (! DB::table('imported_existing_documents')->exists()) {
            return [];
        }

        $approvedStatusId = $this->statusDocumentId('APPROVED');
        $obsoleteStatusId = $this->statusDocumentId('OBSOLETE');
        $now = now();
        $idMap = [];

        DB::table('imported_existing_documents')
            ->orderBy('id')
            ->chunkById(100, function ($documents) use (&$idMap, $approvedStatusId, $obsoleteStatusId, $now): void {
                foreach ($documents as $imported) {
                    $isMaster = ($imported->document_state ?? 'obsolete') === 'master';
                    $isCurrentRule = ($imported->obsolete_rule_type ?? 'legacy_rule') === 'current_rule';
                    $origin = ($isMaster || $isCurrentRule) ? 'imported_current' : 'imported_legacy';
                    $statusId = $isMaster ? $approvedStatusId : $obsoleteStatusId;

                    $newDocumentId = DB::table('t_document')->insertGetId([
                        'origin' => $origin,
                        'm_status_document_id' => $statusId,
                        'm_document_level_id' => $imported->m_document_level_id,
                        'm_document_types_id' => $imported->m_document_types_id,
                        'm_proses_bisnis_id' => $imported->m_proses_bisnis_id,
                        'm_proses_fungsi_id' => $imported->m_proses_fungsi_id,
                        'user_id' => $imported->uploaded_by,
                        'nama_dokumen' => $imported->nama_dokumen,
                        'nomor_dokumen' => $imported->nomor_dokumen,
                        'nomor_revisi' => $this->importedRevisionValue($imported->nomor_revisi ?? null, $isMaster || $isCurrentRule),
                        'catatan' => $imported->catatan,
                        'created_at' => $imported->created_at ?? $now,
                        'tanggal_terbit' => $imported->tanggal_terbit,
                        'approved_at' => $isMaster ? ($imported->tanggal_terbit ?? $imported->created_at ?? $now) : null,
                        'obsolete_at' => $isMaster ? null : ($imported->tanggal_obsolete ?? $imported->created_at ?? $now),
                    ]);

                    $idMap[(int) $imported->id] = (int) $newDocumentId;
                }
            });

        $this->migrateImportedExistingDepartments($idMap);
        $this->migrateImportedExistingFiles($idMap);
        $this->migrateImportedExistingRevisionBridge($idMap);
        $this->migrateImportedExistingRegistry($idMap);

        return $idMap;
    }

    /**
     * @param  array<int, int>  $idMap
     */
    private function migrateImportedExistingDepartments(array $idMap): void
    {
        if ($idMap === [] || ! Schema::hasTable('imported_existing_document_departments')) {
            return;
        }

        DB::table('imported_existing_document_departments')
            ->orderBy('imported_existing_document_id')
            ->get()
            ->each(function ($department) use ($idMap): void {
                $documentId = $idMap[(int) $department->imported_existing_document_id] ?? null;

                if ($documentId === null) {
                    return;
                }

                DB::table('document_departments')->updateOrInsert([
                    't_document_id' => $documentId,
                    'department_id' => $department->department_id,
                ]);
            });
    }

    /**
     * @param  array<int, int>  $idMap
     */
    private function migrateImportedExistingFiles(array $idMap): void
    {
        if ($idMap === [] || ! Schema::hasTable('imported_existing_document_files')) {
            return;
        }

        DB::table('imported_existing_document_files')
            ->orderBy('id')
            ->chunkById(100, function ($files) use ($idMap): void {
                foreach ($files as $file) {
                    $documentId = $idMap[(int) $file->imported_existing_document_id] ?? null;

                    if ($documentId === null) {
                        continue;
                    }

                    $typeFile = $file->type_file === 'attachment'
                        ? 'attachment'
                        : 'imported_document';

                    $documentNumber = $typeFile === 'imported_document'
                        ? DB::table('t_document')->where('id', $documentId)->value('nomor_dokumen')
                        : null;

                    DB::table('t_document_files')->insert([
                        't_document_id' => $documentId,
                        'type_file' => $typeFile,
                        'document_number' => $documentNumber,
                        'path_file' => $file->path_file,
                        'uploaded_by' => $file->uploaded_by,
                        'updated_at' => $file->updated_at ?? $file->created_at ?? now(),
                        'original_file_name' => $file->original_file_name,
                        'stored_file_name' => $file->stored_file_name,
                        'file_size' => $file->file_size,
                    ]);
                }
            });
    }

    /**
     * @param  array<int, int>  $idMap
     */
    private function migrateImportedExistingRevisionBridge(array $idMap): void
    {
        if ($idMap === [] || ! Schema::hasColumn('t_document', 'imported_existing_source_id')) {
            return;
        }

        foreach ($idMap as $oldImportedId => $newDocumentId) {
            DB::table('t_document')
                ->where('imported_existing_source_id', $oldImportedId)
                ->update([
                    'revised_from' => $newDocumentId,
                    'origin' => 'workflow',
                ]);
        }
    }

    /**
     * @param  array<int, int>  $idMap
     */
    private function migrateImportedExistingRegistry(array $idMap): void
    {
        if ($idMap === [] || ! Schema::hasTable('document_number_registry')) {
            return;
        }

        foreach ($idMap as $oldImportedId => $newDocumentId) {
            DB::table('document_number_registry')
                ->where('source_type', 'imported_existing_document')
                ->where('source_id', $oldImportedId)
                ->update([
                    'source_type' => 't_document',
                    'source_id' => $newDocumentId,
                ]);
        }
    }

    /**
     * @param  array<int, int>  $idMap
     */
    private function migrateImportedExistingRelationsToDocumentRelations(array $idMap): void
    {
        if ($idMap === [] || ! Schema::hasTable('imported_existing_document_relations')) {
            return;
        }

        DB::table('imported_existing_document_relations')
            ->orderBy('id')
            ->chunkById(100, function ($relations) use ($idMap): void {
                foreach ($relations as $relation) {
                    $sourceDocumentId = $idMap[(int) $relation->imported_existing_document_id] ?? null;
                    $targetDocumentId = $relation->related_document_id
                        ?? ($idMap[(int) ($relation->related_imported_existing_document_id ?? 0)] ?? null);

                    if ($sourceDocumentId === null || $targetDocumentId === null || (int) $sourceDocumentId === (int) $targetDocumentId) {
                        continue;
                    }

                    DB::table('document_relations')->updateOrInsert(
                        [
                            'source_document_id' => $sourceDocumentId,
                            'target_document_id' => $targetDocumentId,
                            'relation_type' => $relation->relation_type,
                        ],
                        [
                            'keterangan' => $relation->keterangan,
                            'created_by' => $relation->created_by,
                            'created_at' => $relation->created_at ?? now(),
                            'updated_at' => $relation->updated_at ?? $relation->created_at ?? now(),
                        ],
                    );
                }
            });
    }

    private function statusDocumentId(string $status): int
    {
        DB::table('m_status_document')->updateOrInsert(
            ['nama_status' => $status],
            ['nama_status' => $status],
        );

        return (int) DB::table('m_status_document')
            ->where('nama_status', $status)
            ->value('id');
    }

    private function importedRevisionValue(mixed $revision, bool $requiresCanonical): string
    {
        $value = trim((string) ($revision ?? ''));

        if (! $requiresCanonical) {
            return $value !== '' ? $value : '00.00';
        }

        if ($value === '') {
            return '00.00';
        }

        if (preg_match('/^(\d{1,2})\.(\d{1,2})$/', $value, $matches)) {
            return str_pad($matches[1], 2, '0', STR_PAD_LEFT)
                .'.'
                .str_pad($matches[2], 2, '0', STR_PAD_LEFT);
        }

        if (ctype_digit($value)) {
            $revisionNumber = max(0, (int) $value);

            return str_pad((string) intdiv($revisionNumber, 100), 2, '0', STR_PAD_LEFT)
                .'.'
                .str_pad((string) ($revisionNumber % 100), 2, '0', STR_PAD_LEFT);
        }

        return '00.00';
    }
};

