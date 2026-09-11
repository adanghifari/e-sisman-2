<?php

namespace App\Support;

use App\Models\Approval;
use App\Models\ApprovalStatus;
use App\Models\Document;
use Illuminate\Support\Collection;

class DocumentHistory
{
    public function forDocument(Document $document): Collection
    {
        $family = $this->transactionsFor($document);
        $family->load(['status', 'approvals.status']);

        $events = collect();

        $family->each(function (Document $familyDocument) use ($events): void {
            foreach ($this->baseEvents($familyDocument) as $event) {
                $events->push($event);
            }

            foreach ($this->approvalEvents($familyDocument) as $event) {
                $events->push($event);
            }
        });

        $this->manualObsoleteEvents($family)->each(fn (array $event) => $events->push($event));
        $this->automaticObsoleteEvents($family)->each(fn (array $event) => $events->push($event));

        return $events
            ->filter(fn (array $event): bool => $event['timestamp'] !== null)
            ->sortBy(fn (array $event): string => sprintf(
                '%010d-%03d-%010d-%010d-%s',
                $event['timestamp']->timestamp,
                $this->eventSortPriority($event),
                $event['document_id'],
                $event['source_id'] ?? 0,
                $event['label'],
            ))
            ->values();
    }

    /**
     * @return Collection<int, Document>
     */
    private function transactionsFor(Document $document): Collection
    {
        $transactions = $document->revisionFamily()->keyBy('id');
        $pendingParentIds = $transactions
            ->pluck('resubmitted_from')
            ->filter()
            ->unique()
            ->values();

        while ($pendingParentIds->isNotEmpty()) {
            $parentId = $pendingParentIds->shift();

            if ($transactions->has($parentId)) {
                continue;
            }

            $parent = Document::query()->find($parentId);

            if ($parent === null) {
                continue;
            }

            $transactions->put($parent->id, $parent);

            if ($parent->resubmitted_from !== null && ! $transactions->has($parent->resubmitted_from)) {
                $pendingParentIds->push($parent->resubmitted_from);
            }
        }

        return $transactions->values();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function baseEvents(Document $document): array
    {
        $isRevision = $document->request_type === 'revision';
        $isResubmission = $document->resubmitted_from !== null;

        return [
            $this->event(
                $document,
                $isResubmission ? 'Diajukan ulang' : 'Dibuat',
                $isResubmission
                    ? 'Pengajuan ulang dibuat dari transaksi #'.$document->resubmitted_from
                    : ($isRevision ? 'Revisi dibuat sebagai draft' : 'Dokumen dibuat'),
                $document->created_at,
                $isResubmission ? 'sky' : 'slate',
            ),
            $this->event($document, 'Diajukan', $isRevision ? 'Revisi diajukan' : 'Dokumen diajukan', $document->submitted_at, 'sky'),
            $this->event($document, 'Disetujui', $isRevision ? 'Revisi menjadi master' : 'Dokumen disetujui', $document->approved_at, 'emerald'),
            $this->event($document, 'Ditolak', 'Dokumen ditolak', $document->rejected_at, 'red'),
            $this->event($document, 'Dibatalkan', 'Dokumen dibatalkan', $document->cancelled_at, 'amber'),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function approvalEvents(Document $document): array
    {
        $events = [];

        $document->approvals
            ->reject(fn (Approval $approval): bool => $approval->shouldHideAsOfficialPreparerDisplay($document))
            ->groupBy(fn (Approval $approval): string => $approval->stages ?: 'Approval')
            ->each(function (Collection $stageApprovals, string $stage) use (&$events, $document): void {
                $assigned = $stageApprovals
                    ->filter(fn (Approval $approval): bool => $approval->assigned_at !== null)
                    ->sortBy(fn (Approval $approval): int => $approval->assigned_at->timestamp)
                    ->first();

                $stageHasStarted = $stageApprovals->contains(
                    fn (Approval $approval): bool => $approval->status?->kode_status !== ApprovalStatus::WAITING,
                );

                if ($assigned !== null && $stageHasStarted) {
                    $events[] = $this->event($document, 'Approval', 'Memasuki tahap approval '.$stage, $assigned->assigned_at, 'sky', $assigned->id);
                }
            });

        return $events;
    }

    private function manualObsoleteEvents(Collection $family): Collection
    {
        return $family
            ->filter(fn (Document $document): bool => $document->request_type === 'obsolete' && $document->approved_at !== null)
            ->map(function (Document $obsoleteRequest) use ($family): array {
                $source = $family->firstWhere('id', $obsoleteRequest->revised_from) ?? $obsoleteRequest;

                return $this->event(
                    $source,
                    'Obsolete',
                    'Dokumen diobsoletekan lewat pengajuan '.$this->documentNumber($obsoleteRequest),
                    $obsoleteRequest->approved_at,
                    'red',
                );
            })
            ->values();
    }

    private function automaticObsoleteEvents(Collection $family): Collection
    {
        $approvedRevisions = $family
            ->filter(fn (Document $document): bool => $document->request_type !== 'obsolete' && $document->approved_at !== null)
            ->sortBy(fn (Document $document): int => $document->numeric_revision)
            ->values();

        return $approvedRevisions
            ->filter(fn (Document $document): bool => $approvedRevisions
                ->contains(fn (Document $revision): bool => $revision->numeric_revision > $document->numeric_revision))
            ->map(function (Document $document) use ($approvedRevisions): array {
                $nextRevision = $approvedRevisions
                    ->first(fn (Document $revision): bool => $revision->numeric_revision > $document->numeric_revision);

                return $this->event(
                    $document,
                    'Obsolete',
                    'Otomatis obsolete saat revisi '.$nextRevision->formatted_revision.' menjadi master',
                    $nextRevision?->approved_at,
                    'red',
                );
            })
            ->values();
    }

    private function event(
        Document $document,
        string $label,
        string $description,
        mixed $timestamp,
        string $tone,
        ?int $sourceId = null,
        ?string $note = null,
    ): array {
        return [
            'document_id' => $document->id,
            'document_number' => $this->documentNumber($document),
            'revision' => $document->formatted_revision,
            'label' => $label,
            'description' => $description,
            'timestamp' => $timestamp,
            'tone' => $tone,
            'source_id' => $sourceId,
            'note' => $note,
        ];
    }

    private function documentNumber(Document $document): string
    {
        return $document->nomor_dokumen ?: '-';
    }

    /**
     * @param  array<string, mixed>  $event
     */
    private function eventSortPriority(array $event): int
    {
        $label = (string) ($event['label'] ?? '');
        $description = (string) ($event['description'] ?? '');

        if ($label === 'Dibuat' || $label === 'Diajukan ulang') {
            return 10;
        }

        if ($label === 'Diajukan') {
            return 20;
        }

        if ($label === 'Approval') {
            return 40;
        }

        if ($label === 'Disetujui') {
            return 90;
        }

        if ($label === 'Obsolete' && str_starts_with($description, 'Otomatis obsolete')) {
            return 100;
        }

        return 50;
    }
}
