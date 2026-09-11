<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('t_document', function (Blueprint $table): void {
            if (! Schema::hasColumn('t_document', 'obsolete_at')) {
                $table->timestamp('obsolete_at')->nullable()->after('approved_at')->index();
            }
        });

        $this->backfillObsoleteAt();
    }

    public function down(): void
    {
        Schema::table('t_document', function (Blueprint $table): void {
            if (Schema::hasColumn('t_document', 'obsolete_at')) {
                $table->dropIndex(['obsolete_at']);
                $table->dropColumn('obsolete_at');
            }
        });
    }

    private function backfillObsoleteAt(): void
    {
        $approvedStatusId = DB::table('m_status_document')
            ->where('nama_status', 'APPROVED')
            ->value('id');
        $obsoleteStatusId = DB::table('m_status_document')
            ->where('nama_status', 'OBSOLETE')
            ->value('id');

        if ($approvedStatusId === null || $obsoleteStatusId === null) {
            return;
        }

        /** @var Collection<int, object> $documents */
        $documents = DB::table('t_document')
            ->select([
                'id',
                'revised_from',
                'request_type',
                'nomor_revisi',
                'm_status_document_id',
                'tanggal_terbit',
                'approved_at',
                'obsolete_at',
            ])
            ->get();

        $documentsById = $documents->keyBy('id');
        $rootIdFor = function (object $document) use ($documentsById): int {
            $current = $document;

            while ($current->revised_from !== null && $documentsById->has($current->revised_from)) {
                $current = $documentsById->get($current->revised_from);
            }

            return (int) $current->id;
        };

        $publishedStatusIds = array_map('intval', [$approvedStatusId, $obsoleteStatusId]);
        $documentsByRoot = $documents->groupBy(fn (object $document): int => $rootIdFor($document));

        $documents
            ->filter(fn (object $document): bool => (int) $document->m_status_document_id === (int) $obsoleteStatusId
                && $document->obsolete_at === null
                && $document->request_type !== 'obsolete')
            ->each(function (object $document) use ($documentsByRoot, $publishedStatusIds, $rootIdFor): void {
                $nextRevision = $documentsByRoot
                    ->get($rootIdFor($document), collect())
                    ->filter(fn (object $candidate): bool => $candidate->request_type !== 'obsolete'
                        && in_array((int) $candidate->m_status_document_id, $publishedStatusIds, true)
                        && ($candidate->tanggal_terbit !== null || $candidate->approved_at !== null)
                        && (int) $candidate->nomor_revisi > (int) $document->nomor_revisi)
                    ->sortBy([
                        ['nomor_revisi', 'asc'],
                        ['id', 'asc'],
                    ])
                    ->first();

                if ($nextRevision === null) {
                    return;
                }

                DB::table('t_document')
                    ->where('id', $document->id)
                    ->update([
                        'obsolete_at' => $nextRevision->approved_at ?? $nextRevision->tanggal_terbit,
                    ]);
            });
    }
};
