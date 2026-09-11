<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

class Document extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
    'origin',
    'm_status_document_id',
    'm_document_level_id',
    'm_document_types_id',
    'm_proses_bisnis_id',
    'm_proses_fungsi_id',
    'user_id',
    'official_preparer_id',
    'official_preparer_name_snapshot',
    'official_preparer_position_snapshot',
    'official_preparer_department_snapshot',
    'revised_from',
    'resubmitted_from',
    'request_type',
    'nama_dokumen',
    'nomor_dokumen',
    'nomor_lembar_revisi',
    'nomor_revisi',
    'catatan_revisi',
    'catatan',
    'created_at',
    'tanggal_terbit',
    'submitted_at',
    'approved_at',
    'obsolete_at',
    'rejected_at',
    'cancelled_at',
];

    public const ORIGIN_WORKFLOW = 'workflow';

    public const ORIGIN_IMPORTED_CURRENT = 'imported_current';

    public const ORIGIN_IMPORTED_LEGACY = 'imported_legacy';

    protected $table = 't_document';

    public $timestamps = false;
    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
            'created_at' => 'datetime',
            'tanggal_terbit' => 'date',
            'submitted_at' => 'datetime',
            'approved_at' => 'datetime',
            'obsolete_at' => 'datetime',
            'rejected_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];


    public function status(): BelongsTo
    {
        return $this->belongsTo(StatusDocument::class, 'm_status_document_id');
    }

    public function documentLevel(): BelongsTo
    {
        return $this->belongsTo(DocumentLevel::class, 'm_document_level_id');
    }

    public function documentType(): BelongsTo
    {
        return $this->belongsTo(DocumentType::class, 'm_document_types_id');
    }

    public function businessProcess(): BelongsTo
    {
        return $this->belongsTo(BusinessProcess::class, 'm_proses_bisnis_id');
    }

    public function businessFunction(): BelongsTo
    {
        return $this->belongsTo(BusinessFunction::class, 'm_proses_fungsi_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function officialPreparer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'official_preparer_id');
    }

    public function snapshotOfficialPreparer(): void
    {
        if ($this->official_preparer_id === null) {
            return;
        }

        if (
            $this->official_preparer_name_snapshot !== null
            && $this->official_preparer_position_snapshot !== null
            && $this->official_preparer_department_snapshot !== null
        ) {
            return;
        }

        $this->loadMissing('officialPreparer.department');

        $this->forceFill([
            'official_preparer_name_snapshot' => $this->official_preparer_name_snapshot ?: $this->officialPreparer?->name,
            'official_preparer_position_snapshot' => $this->official_preparer_position_snapshot ?: $this->officialPreparer?->jabatan,
            'official_preparer_department_snapshot' => $this->official_preparer_department_snapshot ?: $this->officialPreparer?->department?->nama_department,
        ])->save();
    }

    public function revisedFrom(): BelongsTo
    {
        return $this->belongsTo(self::class, 'revised_from');
    }

    public function resubmittedFrom(): BelongsTo
    {
        return $this->belongsTo(self::class, 'resubmitted_from');
    }

    public function resubmissions(): HasMany
    {
        return $this->hasMany(self::class, 'resubmitted_from');
    }

    public function revisions(): HasMany
    {
        return $this->hasMany(self::class, 'revised_from');
    }

    public function outgoingRelations(): HasMany
    {
        return $this->hasMany(DocumentRelation::class, 'source_document_id');
    }

    public function incomingRelations(): HasMany
    {
        return $this->hasMany(DocumentRelation::class, 'target_document_id');
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function getObsoleteRuleTypeAttribute(): string
    {
        return $this->origin === self::ORIGIN_IMPORTED_LEGACY ? 'legacy_rule' : 'current_rule';
    }

    public function getTanggalObsoleteAttribute(): ?CarbonInterface
    {
        return $this->obsolete_at;
    }

    public function getDocumentStateAttribute(): string
    {
        return $this->status?->nama_status === StatusDocument::APPROVED ? 'master' : 'obsolete';
    }

    public function isMaster(): bool
    {
        return $this->status?->nama_status === StatusDocument::APPROVED;
    }

    public function isObsolete(): bool
    {
        return $this->status?->nama_status === StatusDocument::OBSOLETE;
    }

    public function revisionRootId(): int
    {
        $document = $this;

        while ($document->revised_from !== null) {
            $document = self::query()
                ->select(['id', 'revised_from'])
                ->findOrFail($document->revised_from);
        }

        return $document->id;
    }

    public function revisionFamily(): Collection
    {
        $documents = self::query()
            ->select(['id', 'revised_from'])
            ->get();
        $rootId = $this->revisionRootId();
        $familyIds = collect([$rootId]);
        $previousCount = 0;

        while ($familyIds->count() !== $previousCount) {
            $previousCount = $familyIds->count();
            $childIds = $documents
                ->whereIn('revised_from', $familyIds)
                ->pluck('id');
            $familyIds = $familyIds->merge($childIds)->unique()->values();
        }

        return self::query()
            ->whereIn('id', $familyIds)
            ->get();
    }

    public function latestApprovedRevisionNumber(): int
    {
        $eligibleStatusIds = StatusDocument::query()
            ->whereIn('nama_status', [StatusDocument::APPROVED, StatusDocument::OBSOLETE])
            ->pluck('id');

        $family = $this->revisionFamily();

        if ($eligibleStatusIds->isEmpty()) {
            return (int) $family->max(fn (self $doc) => $doc->numeric_revision);
        }

        return (int) $family
            ->filter(fn (self $document): bool => $eligibleStatusIds->contains($document->m_status_document_id))
            ->filter(fn (self $document): bool => $document->request_type !== 'obsolete')
            ->max(fn (self $doc) => $doc->numeric_revision);
    }

    public function obsoleteRevisions(): HasMany
    {
        return $this->hasMany(self::class, 'revised_from')
            ->whereHas('status', fn ($query) => $query->where('nama_status', StatusDocument::OBSOLETE));
    }

    public function procedureReferenceRelation(): ?DocumentRelation
    {
        return $this->outgoingRelations()
            ->with('targetDocument')
            ->where('relation_type', DocumentRelation::REFERENCES)
            ->first();
    }

    public function procedureReferenceValue(): ?string
    {
        return $this->procedureReferenceRelation()?->target_document_id !== null
            ? (string) $this->procedureReferenceRelation()->target_document_id
            : null;
    }

    public function supersededByRelation(): ?DocumentRelation
    {
        return $this->outgoingRelations()
            ->with('targetDocument')
            ->where('relation_type', DocumentRelation::SUPERSEDED_BY)
            ->first();
    }

    public function getFormattedRevisionAttribute(): string
    {
        return self::formatRevisionNumber($this->nomor_revisi);
    }

    public function getNumericRevisionAttribute(): int
    {
        return self::normalizeRevisionNumber($this->nomor_revisi);
    }

    public static function formatRevisionNumber(int|string|null $revision): string
    {
        if ($revision === null || $revision === '') {
            return '00.00';
        }

        if (is_string($revision) && preg_match('/^\d{2}\.\d{2}$/', $revision)) {
            return $revision;
        }

        if (is_numeric($revision)) {
            $num = max(0, (int) $revision);

            return str_pad((string) intdiv($num, 100), 2, '0', STR_PAD_LEFT)
                .'.'
                .str_pad((string) ($num % 100), 2, '0', STR_PAD_LEFT);
        }

        return (string) $revision;
    }

    public static function normalizeRevisionNumber(int|string|null $revision): int
    {
        if (! filled($revision)) {
            return 0;
        }

        if (is_int($revision)) {
            return $revision;
        }

        if (is_numeric($revision) && ! str_contains((string) $revision, '.')) {
            return (int) $revision;
        }

        $parts = explode('.', (string) $revision, 2);
        $major = (int) preg_replace('/\D+/', '', $parts[0] ?? '0');
        $minor = (int) preg_replace('/\D+/', '', $parts[1] ?? '0');

        return ($major * 100) + $minor;
    }

    public function departments(): BelongsToMany
    {
        return $this->belongsToMany(
            Department::class,
            'document_departments',
            't_document_id',
            'department_id',
        );
    }

    public function approvals(): HasMany
    {
        return $this->hasMany(Approval::class, 't_document_id');
    }

    public function files(): HasMany
    {
        // Newer file records win when legacy/concurrent data contains duplicates.
        return $this->hasMany(DocumentFile::class, 't_document_id')->orderByDesc('id');
    }

    public function availableRevisionSourceAttachments(): Collection
    {
        $eligibleStatusIds = StatusDocument::query()
            ->whereIn('nama_status', [StatusDocument::APPROVED, StatusDocument::OBSOLETE])
            ->pluck('id');

        $family = $this->revisionFamily()
            ->filter(fn (self $document): bool => $document->numeric_revision <= $this->numeric_revision)
            ->filter(fn (self $document): bool => $document->is($this) || $eligibleStatusIds->contains($document->m_status_document_id))
            ->sortBy([
                ['nomor_revisi', 'asc'],
                ['id', 'asc'],
            ])
            ->values();

        if ($family->isEmpty()) {
            return collect();
        }

        $revisionByDocumentId = $family
            ->mapWithKeys(fn (self $document): array => [$document->id => $document->numeric_revision]);

        $attachments = DocumentFile::query()
            ->whereIn('t_document_id', $family->pluck('id'))
            ->where('type_file', 'attachment')
            ->orderBy('id')
            ->get();

        $attachmentsById = $attachments->keyBy('id');
        $rootFor = function (DocumentFile $file) use ($attachmentsById): int {
            $current = $file;

            while ($current->source_file_id !== null && $attachmentsById->has($current->source_file_id)) {
                $current = $attachmentsById->get($current->source_file_id);
            }

            return (int) ($current->source_file_id ?? $current->id);
        };

        return $attachments
            ->sortBy(fn (DocumentFile $file): string => sprintf(
                '%010d-%010d',
                $revisionByDocumentId->get($file->t_document_id, 0),
                $file->id,
            ))
            ->reduce(function (Collection $carry, DocumentFile $file) use ($rootFor): Collection {
                $carry->put($rootFor($file), $file);

                return $carry;
            }, collect())
            ->values()
            ->sortBy(fn (DocumentFile $file): string => $file->attachmentSortKey())
            ->values();
    }

    public function finalArtifacts(): HasMany
    {
        return $this->hasMany(DocumentFinalArtifact::class, 't_document_id');
    }
}

