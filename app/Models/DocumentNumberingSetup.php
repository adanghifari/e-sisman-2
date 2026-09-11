<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentNumberingSetup extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
    'scope_identifier',
    'existing_start_number',
    'existing_end_number',
    'v2_start_number',
    'configured_by',
    'configured_at',
];

    protected $table = 'document_numbering_setups';

    public const UPDATED_AT = null;
    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
            'existing_start_number' => 'integer',
            'existing_end_number' => 'integer',
            'v2_start_number' => 'integer',
            'configured_at' => 'datetime',
        ];


    public function configurator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'configured_by');
    }
}

