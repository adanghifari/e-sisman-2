<?php

namespace App\Actions\Administration\ApprovalFlow;

use App\Models\ApprovalFlowStage;
use Illuminate\Support\Facades\Validator;

class UpdateApprovalFlowStage
{
    public function handle(ApprovalFlowStage $stage, array $data): ApprovalFlowStage
    {
        $validated = Validator::make($data, [
            'nama_tahap' => [
                'required',
                'string',
                'max:255',
            ],
        ])->validate();

        $stage->update([
            'nama_tahap' => trim($validated['nama_tahap']),
        ]);

        return $stage->refresh();
    }
}
