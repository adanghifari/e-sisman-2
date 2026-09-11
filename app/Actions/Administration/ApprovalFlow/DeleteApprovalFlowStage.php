<?php

namespace App\Actions\Administration\ApprovalFlow;

use App\Models\ApprovalFlowStage;
use Illuminate\Support\Facades\DB;

class DeleteApprovalFlowStage
{
    public function handle(ApprovalFlowStage $stage): void
    {
        DB::transaction(function () use ($stage): void {
            $approvalFlow = $stage->approvalFlow;

            $stage->delete();

            $approvalFlow->stages()
                ->get()
                ->each(function (ApprovalFlowStage $stage, int $index): void {
                    $stage->update(['stage_order' => $index + 1]);
                });
        });
    }
}
