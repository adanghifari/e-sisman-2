<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DocumentType extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = ['nama_types', 'is_active'];

    protected $table = 'm_document_types';

    public $timestamps = false;
    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
            'is_active' => 'boolean',
        ];


    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(Document::class, 'm_document_types_id');
    }
}

