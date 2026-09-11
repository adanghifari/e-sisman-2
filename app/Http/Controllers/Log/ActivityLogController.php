<?php

namespace App\Http\Controllers\Log;

use App\Http\Controllers\Controller;
use App\Queries\Log\DocumentDownloadActivityQuery;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class ActivityLogController extends Controller
{
    public function __invoke(Request $request, DocumentDownloadActivityQuery $activityQuery): View
    {
        $filters = [
            'document_name' => trim((string) $request->query('document_name', '')),
            'document_number' => trim((string) $request->query('document_number', '')),
            'downloaded_by' => trim((string) $request->query('downloaded_by', '')),
        ];

        return view('log.activity-log.index', [
            'activities' => $activityQuery->rows($filters),
            'filters' => $filters,
            'totalActivities' => $activityQuery->total(),
        ]);
    }
}
