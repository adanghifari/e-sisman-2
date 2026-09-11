<?php

namespace App\Support\DocumentFiles;

use App\Models\Document;
use App\Models\DocumentFile;
use App\Models\StatusDocument;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class DocumentFileNumbering
{
    public const REVISION_FORM_SUFFIX = 1;

    public const FIRST_ATTACHMENT_SUFFIX = 2;

    /**
     * @var array<int, string>
     */
    private array $reservedSuffixes = [
        self::REVISION_FORM_SUFFIX => 'revision_form',
    ];

    public function numberFor(Document $document, string $type, ?int $attachmentOrder = null): ?string
    {
        if (! filled($document->nomor_dokumen)) {
            return null;
        }

        if ($this->isMainDocumentType($type)) {
            return $document->nomor_dokumen;
        }

        if ($type === 'revision_form') {
            return $this->revisionFormNumber($document);
        }

        if ($type === 'attachment') {
            return $this->nextAttachmentNumber($document);
        }

        return null;
    }

    public function mainDocumentNumber(Document $document): ?string
    {
        return filled($document->nomor_dokumen) ? $document->nomor_dokumen : null;
    }

    public function revisionFormNumber(Document $document): ?string
    {
        if (! filled($document->nomor_dokumen)) {
            return null;
        }

        return $this->fileFamilyPrefix($document).'-'.str_pad((string) self::REVISION_FORM_SUFFIX, 2, '0', STR_PAD_LEFT);
    }

    public function nextAttachmentNumber(Document $document): ?string
    {
        if (! filled($document->nomor_dokumen)) {
            return null;
        }

        $this->lockFamilyForUpdate($document);

        $usedSuffixes = $this->usedFileSuffixes($document);
        $suffix = self::FIRST_ATTACHMENT_SUFFIX;

        while (isset($this->reservedSuffixes[$suffix]) || $usedSuffixes->contains($suffix)) {
            $suffix++;
        }

        return $this->fileFamilyPrefix($document).'-'.str_pad((string) $suffix, 2, '0', STR_PAD_LEFT);
    }

    public function attachmentNumberForPosition(Document $document, int $position): ?string
    {
        if (! filled($document->nomor_dokumen)) {
            return null;
        }

        $suffix = self::FIRST_ATTACHMENT_SUFFIX + max(0, $position - 1);

        return $this->fileFamilyPrefix($document).'-'.str_pad((string) $suffix, 2, '0', STR_PAD_LEFT);
    }

    /**
     * @param  array<int, string|null>  $reservedDocumentNumbers
     */
    public function compactActiveAttachmentNumbers(Document $document, array $reservedDocumentNumbers = []): void
    {
        $attachments = $document->files()
            ->where('type_file', 'attachment')
            ->orderBy('id')
            ->get();
        $reservedSuffixes = $attachments
            ->filter(fn (DocumentFile $file): bool => $file->source_file_id !== null)
            ->pluck('document_number')
            ->merge($reservedDocumentNumbers)
            ->map(fn (?string $number): ?int => $number ? $this->suffixFromDocumentNumber($number) : null)
            ->filter(fn (?int $suffix): bool => $suffix !== null)
            ->unique()
            ->values();
        $nextSuffix = $reservedSuffixes->isNotEmpty()
            ? max(self::FIRST_ATTACHMENT_SUFFIX, ((int) $reservedSuffixes->max()) + 1)
            : self::FIRST_ATTACHMENT_SUFFIX;

        $attachments
            ->filter(fn (DocumentFile $file): bool => $file->source_file_id === null)
            ->sortBy(fn (DocumentFile $file): string => sprintf(
                '%010d-%010d',
                $file->attachment_order ?? PHP_INT_MAX,
                $file->id,
            ))
            ->values()
            ->each(function (DocumentFile $file) use ($document, $reservedSuffixes, &$nextSuffix): void {
                $file->forceFill([
                    'document_number' => $this->attachmentNumberForSuffix($document, $nextSuffix),
                    'updated_at' => now(),
                ])->save();

                $nextSuffix++;
            });
    }

    public function assignMissingNumbers(Document $document): void
    {
        $document->loadMissing('files');

        if (! filled($document->nomor_dokumen)) {
            return;
        }

        $document->files
            ->whereIn('type_file', ['filled_template', 'revision_content', 'imported_document'])
            ->where('document_number', null)
            ->each(fn (DocumentFile $file) => $file->forceFill([
                'document_number' => $this->mainDocumentNumber($document),
            ])->save());

        $document->files
            ->where('type_file', 'revision_form')
            ->where('document_number', null)
            ->each(fn (DocumentFile $file) => $file->forceFill([
                'document_number' => $this->revisionFormNumber($document),
            ])->save());

        $document->files
            ->where('type_file', 'attachment')
            ->where('document_number', null)
            ->sortBy(fn (DocumentFile $file): string => sprintf(
                '%010d-%010d',
                $file->attachment_order ?? PHP_INT_MAX,
                $file->id,
            ))
            ->each(fn (DocumentFile $file) => $file->forceFill([
                'document_number' => $this->nextAttachmentNumber($document),
            ])->save());

        $document->unsetRelation('files');
    }

    public function isMainDocumentType(string $type): bool
    {
        return in_array($type, ['filled_template', 'revision_content', 'imported_document'], true);
    }

    public function lockFamilyForUpdate(Document $document): void
    {
        if (! filled($document->nomor_dokumen)) {
            return;
        }

        $documentIds = Document::query()
            ->where('nomor_dokumen', $document->nomor_dokumen)
            ->orderBy('id')
            ->lockForUpdate()
            ->pluck('id');

        if ($documentIds->isEmpty()) {
            return;
        }

        DB::table('t_document_files')
            ->whereIn('t_document_id', $documentIds)
            ->orderBy('id')
            ->lockForUpdate()
            ->pluck('id');
    }

    private function fileFamilyPrefix(Document $document): string
    {
        $document->loadMissing('documentLevel', 'revisedFrom.documentLevel');
        $levelSource = $document;

        while ($levelSource instanceof Document && $levelSource->documentLevel?->kode === 'level-4') {
            if ($levelSource->revisedFrom !== null) {
                $levelSource = $levelSource->revisedFrom;
                $levelSource->loadMissing('documentLevel', 'revisedFrom.documentLevel');
                continue;
            }

            break;
        }

        $level = $levelSource->documentLevel;

        $prefix = match ($level?->kode) {
            'level-1' => 'FMSM',
            'level-2' => 'FMPS',
            'level-3' => 'FMIK',
            default => 'FM',
        };

        $segments = collect(explode('-', (string) $document->nomor_dokumen))
            ->filter()
            ->values();

        if ($segments->isNotEmpty()) {
            $segments->shift();
        }

        return collect([$prefix])
            ->merge($segments)
            ->filter()
            ->implode('-');
    }

    private function attachmentNumberForSuffix(Document $document, int $suffix): ?string
    {
        if (! filled($document->nomor_dokumen)) {
            return null;
        }

        return $this->fileFamilyPrefix($document).'-'.str_pad((string) $suffix, 2, '0', STR_PAD_LEFT);
    }

    /**
     * @return Collection<int, int>
     */
    private function usedFileSuffixes(Document $document): Collection
    {
        $familyPrefix = $this->fileFamilyPrefix($document).'-';

        return Document::query()
            ->where('nomor_dokumen', $document->nomor_dokumen)
            ->where(function ($query) use ($document): void {
                $query
                    ->whereKey($document->id)
                    ->orWhereHas('status', fn ($query) => $query->whereIn('nama_status', [
                        StatusDocument::APPROVED,
                        StatusDocument::OBSOLETE,
                    ]));
            })
            ->pluck('id')
            ->pipe(fn (Collection $documentIds): Collection => $documentIds->isEmpty()
                ? collect()
                : DB::table('t_document_files')
                    ->whereIn('t_document_id', $documentIds)
                    ->whereNotNull('document_number')
                    ->pluck('document_number'))
            ->filter(fn (string $number): bool => str_starts_with($number, $familyPrefix))
            ->map(fn (string $number): ?int => $this->suffixFromDocumentNumber($number))
            ->filter(fn (?int $suffix): bool => $suffix !== null)
            ->unique()
            ->values();
    }

    private function suffixFromDocumentNumber(string $documentNumber): ?int
    {
        $segments = collect(explode('-', $documentNumber))
            ->filter()
            ->values();

        if ($segments->count() < 2) {
            return null;
        }

        $suffix = $segments->last();

        return ctype_digit((string) $suffix) ? (int) $suffix : null;
    }
}
