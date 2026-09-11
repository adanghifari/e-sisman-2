<?php

use App\Models\StatusDocument;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $approvedStatusId = StatusDocument::query()
            ->where('nama_status', StatusDocument::APPROVED)
            ->value('id');
        $obsoleteStatusId = StatusDocument::query()
            ->where('nama_status', StatusDocument::OBSOLETE)
            ->value('id');

        if ($approvedStatusId === null || $obsoleteStatusId === null) {
            return;
        }

        $documents = DB::table('t_document')
            ->select(['id', 'revised_from', 'nomor_revisi', 'm_status_document_id', 'approved_at'])
            ->get();

        $documents
            ->groupBy(fn (object $document): int => $this->rootId($document, $documents))
            ->each(function (Collection $family) use ($approvedStatusId, $obsoleteStatusId): void {
                $approvedFamily = $family
                    ->where('m_status_document_id', $approvedStatusId)
                    ->sortByDesc(fn (object $document): string => sprintf(
                        '%010d-%s-%010d',
                        (int) $document->nomor_revisi,
                        $document->approved_at ?? '',
                        (int) $document->id,
                    ))
                    ->values();

                if ($approvedFamily->count() < 2) {
                    return;
                }

                $latestApprovedId = $approvedFamily->first()->id;

                DB::table('t_document')
                    ->whereIn('id', $approvedFamily->pluck('id')->reject(fn (int $id): bool => $id === $latestApprovedId))
                    ->update(['m_status_document_id' => $obsoleteStatusId]);
            });
    }

    public function down(): void
    {
        //
    }

    private function rootId(object $document, Collection $documents): int
    {
        $current = $document;

        while ($current->revised_from !== null) {
            $parent = $documents->firstWhere('id', $current->revised_from);

            if ($parent === null) {
                break;
            }

            $current = $parent;
        }

        return (int) $current->id;
    }
};
