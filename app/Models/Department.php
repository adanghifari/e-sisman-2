<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Department extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = ['nama_department', 'kode_department', 'is_active'];

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

    public function users(): HasMany
    {
        return $this->hasMany(User::class, 'm_department_id');
    }

    public function documents(): BelongsToMany
    {
        return $this->belongsToMany(
            Document::class,
            'document_departments',
            'department_id',
            't_document_id',
        );
    }
}

