<?php

namespace App\Http\Controllers;

use App\Models\Approval;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class DigitalSignatureVerificationController extends Controller
{
    public function __invoke(Request $request, Approval $approval): View
    {
        abort_unless($request->hasValidSignature(), 403);

        $approval->load([
            'document.status',
            'document.documentLevel',
            'document.documentType',
            'document.businessProcess',
            'document.businessFunction',
            'document.departments',
            'status',
            'approver.department',
        ]);

        abort_if($approval->responded_at === null, 404);

        return view('digital-signatures.verify', [
            'approval' => $approval,
            'document' => $approval->document,
        ]);
    }
}
