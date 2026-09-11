<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ApprovalFlow extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = ['m_document_level_id', 'nama_flow'];

    protected $table = 'm_approval_flows';

    public function documentLevel(): BelongsTo
    {
        return $this->belongsTo(DocumentLevel::class, 'm_document_level_id');
    }

    public function stages(): HasMany
    {
        return $this->hasMany(ApprovalFlowStage::class, 'm_approval_flow_id')
            ->orderBy('stage_order');
    }
}

