<?php

namespace App\Http\Controllers\Log;

use App\Http\Controllers\Controller;
use App\Queries\Log\DocumentDownloadActivityQuery;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ActivityLogExportController extends Controller
{
    public function __invoke(Request $request, DocumentDownloadActivityQuery $activityQuery): StreamedResponse
    {
        $filters = [
            'document_name' => trim((string) $request->query('document_name', '')),
            'document_number' => trim((string) $request->query('document_number', '')),
            'downloaded_by' => trim((string) $request->query('downloaded_by', '')),
        ];

        $activities = $activityQuery->rows($filters);
        $filename = 'catatan-aktivitas-'.now()->format('Ymd-His').'.csv';

        return response()->streamDownload(function () use ($activities): void {
            $output = fopen('php://output', 'w');

            fwrite($output, "\xEF\xBB\xBF");
            fputcsv($output, [
                'Nama Dokumen',
                'Nomor Dokumen',
                'Revisi',
                'Diunduh Oleh',
                'Waktu Unduh',
                'Diunduh Ke',
            ]);

            foreach ($activities as $activity) {
                fputcsv($output, [
                    $activity['name'],
                    $activity['number'],
                    $activity['revision'],
                    $activity['downloaded_by'],
                    $activity['downloaded_at'],
                    $activity['count'],
                ]);
            }

            fclose($output);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}
