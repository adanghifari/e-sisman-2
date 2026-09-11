<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApprovalFlowStage extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = ['m_approval_flow_id', 'stage_order', 'nama_tahap'];

    protected $table = 'm_approval_flow_stages';
    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
            'stage_order' => 'integer',
        ];


    public function approvalFlow(): BelongsTo
    {
        return $this->belongsTo(ApprovalFlow::class, 'm_approval_flow_id');
    }

    public function getDisplayLabelAttribute(): string
    {
        return trim($this->nama_tahap);
    }
}

