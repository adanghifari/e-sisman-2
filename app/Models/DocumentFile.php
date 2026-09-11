<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class DocumentFile extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
    't_document_id',
    't_document_files_id',
    'type_file',
    'document_number',
    'attachment_title',
    'attachment_order',
    'path_file',
    'uploaded_by',
    'updated_at',
    'original_file_name',
    'stored_file_name',
    'source_file_id',
    'file_size',
];

    public const TYPE_IMPORTED_DOCUMENT = 'imported_document';
    public const TYPE_FILLED_TEMPLATE = 'filled_template';
    public const TYPE_REVISION_CONTENT = 'revision_content';
    public const TYPE_REVISION_CONTENT_WORD = 'revision_content_word';
    public const TYPE_REVISION_FORM = 'revision_form';
    public const TYPE_REVISION_FORM_WORD = 'revision_form_word';
    public const TYPE_ATTACHMENT = 'attachment';
    public const IMPORTED_DOCUMENT = self::TYPE_IMPORTED_DOCUMENT;
    public const ATTACHMENT = self::TYPE_ATTACHMENT;

    protected $table = 't_document_files';

    public $timestamps = false;
    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
            'updated_at' => 'datetime',
            'file_size' => 'integer',
            'attachment_order' => 'integer',
        ];


    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class, 't_document_id');
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function sourceFile(): BelongsTo
    {
        return $this->belongsTo(self::class, 'source_file_id');
    }

    public function attachmentSortKey(): string
    {
        $suffix = null;

        if (filled($this->document_number)) {
            $lastSegment = Str::afterLast($this->document_number, '-');
            $suffix = ctype_digit($lastSegment) ? (int) $lastSegment : null;
        }

        return sprintf(
            '%010d-%010d-%010d',
            $suffix ?? PHP_INT_MAX,
            $this->attachment_order ?? PHP_INT_MAX,
            $this->id,
        );
    }
}

