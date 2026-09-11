<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentTemplateFile extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
    'document_template_id',
    'file_order',
    'disk',
    'path_file',
    'original_file_name',
    'stored_file_name',
    'mime_type',
    'file_size',
];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
            'file_order' => 'integer',
            'file_size' => 'integer',
        ];


    public function documentTemplate(): BelongsTo
    {
        return $this->belongsTo(DocumentTemplate::class);
    }
}

