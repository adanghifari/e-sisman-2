<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentFinalArtifact extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
    't_document_id',
    'source_document_file_id',
    'artifact_type',
    'generation_number',
    'generation_status',
    'path_file',
    'generated_file_name',
    'checksum_sha256',
    'file_size',
    'generated_by',
    'generated_at',
    'generation_error',
];

    public const TYPE_FINAL_DOCUMENT = 'final_document';

    public const TYPE_APPROVAL_PREVIEW = 'approval_preview';

    public const TYPE_APPROVAL_SHEET = 'approval_sheet';

    public const STATUS_PENDING = 'pending';

    public const STATUS_PROCESSING = 'processing';

    public const STATUS_GENERATED = 'generated';

    public const STATUS_FAILED = 'failed';
    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
            'generation_number' => 'integer',
            'file_size' => 'integer',
            'generated_at' => 'datetime',
        ];


    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class, 't_document_id');
    }

    public function sourceFile(): BelongsTo
    {
        return $this->belongsTo(DocumentFile::class, 'source_document_file_id');
    }

    public function generatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'generated_by');
    }
}

