<?php

namespace App\Actions\Administration\ApprovalFlow;

use App\Models\ApprovalFlow;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class ReorderApprovalFlowStages
{
    public function handle(ApprovalFlow $approvalFlow, array $stageIds): void
    {
        $validated = Validator::make(['stage_ids' => $stageIds], [
            'stage_ids' => [
                'required',
                'array',
            ],
            'stage_ids.*' => [
                'integer',
                Rule::exists('m_approval_flow_stages', 'id')
                    ->where('m_approval_flow_id', $approvalFlow->id),
            ],
        ])->validate();

        DB::transaction(function () use ($validated): void {
            collect($validated['stage_ids'])
                ->values()
                ->each(function (int $stageId, int $index): void {
                    DB::table('m_approval_flow_stages')
                        ->where('id', $stageId)
                        ->update(['stage_order' => $index + 1]);
                });
        });
    }
}
