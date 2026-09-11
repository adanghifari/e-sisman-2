<?php

namespace App\Support;

use App\Models\Approval;
use App\Models\ApprovalStatus;
use App\Models\Document;
use Illuminate\Support\Collection;

class DocumentRejectionHistory
{
    /**
     * @return Collection<int, array{
     *     document_id: int,
     *     attempt: Document,
     *     approval: Approval,
     *     approver_name: string,
     *     stage: string,
     *     catatan: ?string,
     *     responded_at: mixed
     * }>
     */
    public function forDocument(Document $document): Collection
    {
        $attempts = collect();
        $seenDocumentIds = collect([$document->id]);
        $previousId = $document->resubmitted_from;

        while ($previousId !== null && ! $seenDocumentIds->contains($previousId)) {
            $attempt = Document::query()
                ->with(['approvals.status', 'approvals.approver'])
                ->find($previousId);

            if ($attempt === null) {
                break;
            }

            $attempts->push($attempt);
            $seenDocumentIds->push($attempt->id);
            $previousId = $attempt->resubmitted_from;
        }

        return $attempts
            ->reverse()
            ->values()
            ->flatMap(function (Document $attempt): Collection {
                return $attempt->approvals
                    ->filter(fn (Approval $approval): bool => $approval->status?->kode_status === ApprovalStatus::REJECTED)
                    ->sortBy(fn (Approval $approval): string => sprintf(
                        '%010d-%010d',
                        $approval->responded_at?->timestamp ?? 0,
                        $approval->id,
                    ))
                    ->map(fn (Approval $approval): array => [
                        'document_id' => $attempt->id,
                        'attempt' => $attempt,
                        'approval' => $approval,
                        'approver_name' => $approval->approver?->name ?? '-',
                        'stage' => $approval->stages ?: 'Approval',
                        'catatan' => $approval->catatan,
                        'responded_at' => $approval->responded_at,
                    ]);
            })
            ->values();
    }
}
