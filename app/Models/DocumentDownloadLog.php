<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $t_document_id
 * @property int|null $t_document_file_id
 * @property int|null $user_id
 * @property string|null $document_name_snapshot
 * @property string|null $document_number_snapshot
 * @property int|null $document_revision_snapshot
 * @property string|null $download_context
 * @property Carbon $downloaded_at
 * @property string|null $ip_address
 * @property string|null $user_agent
 */
class DocumentDownloadLog extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
    't_document_id',
    't_document_file_id',
    'document_name_snapshot',
    'document_number_snapshot',
    'document_revision_snapshot',
    'download_context',
    'user_id',
    'downloaded_at',
    'ip_address',
    'user_agent',
];

    protected $table = 't_document_download_logs';

    public $timestamps = false;
    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
            'downloaded_at' => 'datetime',
        ];


    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class, 't_document_id');
    }

    public function file(): BelongsTo
    {
        return $this->belongsTo(DocumentFile::class, 't_document_file_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

