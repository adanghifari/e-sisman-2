<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Approval extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
    't_document_id',
    'm_approval_status_id',
    'user_id',
    'role_id',
    'assigned_by',
    'assigned_at',
    'responded_at',
    'stages',
    'm_approval_flow_stage_id',
    'catatan',
    'stage_name_snapshot',
    'stage_order_snapshot',
    'approver_name_snapshot',
    'approver_position_snapshot',
    'approver_department_snapshot',
];

    private const OFFICIAL_PREPARER_DISPLAY_STAGES = [
        'TTD Penyusun Resmi',
        'Disusun Oleh',
    ];

    protected $table = 't_approval';

    public $timestamps = false;
    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
            'assigned_at' => 'datetime',
            'responded_at' => 'datetime',
            'stage_order_snapshot' => 'integer',
        ];


    public function fillResponseSnapshot(?int $stageOrder = null): self
    {
        $approver = $this->approver()
            ->with('department')
            ->first();

        return $this->fill([
            'stage_name_snapshot' => $this->stages,
            'stage_order_snapshot' => $stageOrder,
            'approver_name_snapshot' => $approver?->name,
            'approver_position_snapshot' => $approver?->jabatan,
            'approver_department_snapshot' => $approver?->department?->nama_department,
        ]);
    }

    public function scopeForApprovalFlowStage(Builder $query, ApprovalFlowStage $stage): Builder
    {
        $stageLabel = $stage->display_label ?: 'Approval';

        return $query->where(function (Builder $query) use ($stage, $stageLabel): void {
            $query
                ->where('m_approval_flow_stage_id', $stage->id)
                ->orWhere(function (Builder $query) use ($stageLabel): void {
                    $query
                        ->whereNull('m_approval_flow_stage_id')
                        ->where('stages', $stageLabel);
                });
        });
    }

    public function shouldHideAsOfficialPreparerDisplay(Document $document): bool
    {
        if ($document->official_preparer_id === null || $this->user_id !== $document->official_preparer_id) {
            return false;
        }

        $stageNames = [
            trim((string) $this->stages),
            trim((string) $this->stage_name_snapshot),
        ];

        return collect($stageNames)
            ->filter()
            ->contains(fn (string $stage): bool => in_array($stage, self::OFFICIAL_PREPARER_DISPLAY_STAGES, true));
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class, 't_document_id');
    }

    public function status(): BelongsTo
    {
        return $this->belongsTo(ApprovalStatus::class, 'm_approval_status_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class, 'role_id');
    }

    public function approvalFlowStage(): BelongsTo
    {
        return $this->belongsTo(ApprovalFlowStage::class, 'm_approval_flow_stage_id');
    }

    public function assignedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }
}

