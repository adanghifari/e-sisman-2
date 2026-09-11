<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BusinessProcess extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = ['kode', 'nama_proses_bisnis', 'is_active'];

    protected $table = 'm_proses_bisnis';

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
        return $this->hasMany(Document::class, 'm_proses_bisnis_id');
    }
}

