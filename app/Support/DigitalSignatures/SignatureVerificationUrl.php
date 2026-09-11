<?php

namespace App\Support\DigitalSignatures;

use App\Models\Approval;
use Illuminate\Support\Facades\URL;

class SignatureVerificationUrl
{
    public function forApproval(Approval|int $approval): string
    {
        $approvalId = $approval instanceof Approval ? $approval->id : $approval;

        return URL::signedRoute('digital-signatures.verify', [
            'approval' => $approvalId,
        ]);
    }
}
