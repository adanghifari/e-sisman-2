<?php

namespace App\Actions\Administration\ApprovalFlow;

use App\Models\ApprovalFlow;
use App\Models\ApprovalFlowStage;
use Illuminate\Support\Facades\Validator;

class CreateApprovalFlowStage
{
    public function handle(ApprovalFlow $approvalFlow, array $data): ApprovalFlowStage
    {
        $validated = Validator::make($data, [
            'nama_tahap' => [
                'required',
                'string',
                'max:255',
            ],
        ])->validate();

        return $approvalFlow->stages()->create([
            'stage_order' => $approvalFlow->stages()->count() + 1,
            'nama_tahap' => trim($validated['nama_tahap']),
        ]);
    }
}
