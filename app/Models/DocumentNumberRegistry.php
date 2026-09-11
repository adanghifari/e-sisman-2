<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentNumberRegistry extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
    'document_number',
    'scope_identifier',
    'source_type',
    'source_id',
    'registered_by',
    'registered_at',
];

    protected $table = 'document_number_registry';

    public const UPDATED_AT = null;

    public const SOURCE_T_DOCUMENT = 't_document';

    public const SOURCE_IMPORTED_EXISTING = 'imported_existing_document';
    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
            'registered_at' => 'datetime',
        ];


    public function registrar(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registered_by');
    }
}

