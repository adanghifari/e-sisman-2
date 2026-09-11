<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentRelation extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
    'source_document_id',
    'target_document_id',
    'relation_type',
    'keterangan',
    'created_by',
];

    public const REFERENCES = 'references';

    public const SUPERSEDED_BY = 'superseded_by';

    public const RELATION_TYPES = [
        self::REFERENCES,
        self::SUPERSEDED_BY,
    ];

    public function sourceDocument(): BelongsTo
    {
        return $this->belongsTo(Document::class, 'source_document_id');
    }

    public function targetDocument(): BelongsTo
    {
        return $this->belongsTo(Document::class, 'target_document_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function source(): ?Document
    {
        return $this->sourceDocument;
    }

    public function target(): ?Document
    {
        return $this->targetDocument;
    }

    public static function targetDocumentIdForReference(?string $reference): ?int
    {
        if (! filled($reference)) {
            return null;
        }

        $reference = (string) $reference;

        if (ctype_digit($reference)) {
            return (int) $reference;
        }

        $parts = explode('-', $reference, 2);

        if (count($parts) === 2 && ctype_digit($parts[1])) {
            return (int) $parts[1];
        }

        return null;
    }

    public static function referenceValue(?int $documentId, ?int $importedDocumentId = null): ?string
    {
        return $documentId !== null ? (string) $documentId : null;
    }

    public static function targetReferenceValue(self $relation): ?string
    {
        return $relation->target_document_id !== null ? (string) $relation->target_document_id : null;
    }

    public static function syncDocumentSourceReference(Document $document, ?string $reference, ?int $createdBy = null): ?self
    {
        $targetId = self::targetDocumentIdForReference($reference);

        if ($targetId === null) {
            $document->outgoingRelations()
                ->where('relation_type', self::REFERENCES)
                ->delete();

            return null;
        }

        return $document->outgoingRelations()->updateOrCreate(
            ['relation_type' => self::REFERENCES],
            [
                'target_document_id' => $targetId,
                'keterangan' => 'Dokumen acuan prosedur.',
                'created_by' => $createdBy,
            ],
        );
    }

    public static function copyReferenceToDocumentSource(Document $document, Document $source, ?int $createdBy = null): ?self
    {
        $relation = $source->outgoingRelations()
            ->where('relation_type', self::REFERENCES)
            ->first();

        if ($relation === null || $relation->target_document_id === null) {
            $document->outgoingRelations()
                ->where('relation_type', self::REFERENCES)
                ->delete();

            return null;
        }

        return $document->outgoingRelations()->updateOrCreate(
            ['relation_type' => self::REFERENCES],
            [
                'target_document_id' => $relation->target_document_id,
                'keterangan' => 'Disalin dari relasi acuan dokumen sumber.',
                'created_by' => $createdBy,
            ],
        );
    }

    public static function supersedeDocument(Document $source, Document $target, ?int $createdBy = null, ?string $note = null): self
    {
        return self::query()->updateOrCreate(
            [
                'source_document_id' => $source->id,
                'relation_type' => self::SUPERSEDED_BY,
            ],
            [
                'target_document_id' => $target->id,
                'keterangan' => $note ?: 'Digantikan oleh revisi dokumen.',
                'created_by' => $createdBy,
            ],
        );
    }

    public static function supersedeDocumentSourceWithDocument(Document $source, Document $target, ?int $createdBy = null, ?string $note = null): self
    {
        return self::supersedeDocument($source, $target, $createdBy, $note);
    }

    public static function targetDocumentNumber(?string $reference): ?string
    {
        $targetId = self::targetDocumentIdForReference($reference);

        if ($targetId === null) {
            return null;
        }

        return Document::query()
            ->whereKey($targetId)
            ->value('nomor_dokumen');
    }
}

