<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DocumentLevel extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = ['kode', 'nama_level', 'nama_dokumen', 'prefix', 'description', 'is_active', 'sort_order'];

    protected $table = 'm_document_levels';

    public $timestamps = false;
    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];


    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    public function approvalFlows(): HasMany
    {
        return $this->hasMany(ApprovalFlow::class, 'm_document_level_id');
    }
}

