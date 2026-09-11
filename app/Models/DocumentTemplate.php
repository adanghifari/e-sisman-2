<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DocumentTemplate extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
    'document_level',
    'version_number',
    'title',
    'notes',
    'uploaded_by',
    'is_active',
    'active_template_key',
    'activated_at',
];

    public const MAX_FILE_SIZE_KB = 10 * 1024;

    public const MAX_FILES = 10;
    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
            'version_number' => 'integer',
            'is_active' => 'boolean',
            'activated_at' => 'datetime',
        ];


    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    public function scopeForLevel(Builder $query, string $documentLevel): void
    {
        $query->where('document_level', $documentLevel);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function files(): HasMany
    {
        return $this->hasMany(DocumentTemplateFile::class);
    }
}

