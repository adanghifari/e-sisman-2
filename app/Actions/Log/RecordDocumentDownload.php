<?php

namespace App\Actions\Log;

use App\Models\Document;
use App\Models\DocumentDownloadLog;
use App\Models\DocumentFile;
use Illuminate\Http\Request;

class RecordDocumentDownload
{
    /**
     * @param  array{name?: string|null, number?: string|null, revision?: int|null, context?: string|null}  $snapshot
     */
    public function handle(Request $request, Document $document, ?DocumentFile $file = null, array $snapshot = []): DocumentDownloadLog
    {
        return DocumentDownloadLog::create([
            't_document_id' => $document->id,
            't_document_file_id' => $file?->id,
            'document_name_snapshot' => $snapshot['name'] ?? $document->nama_dokumen,
            'document_number_snapshot' => $snapshot['number'] ?? $document->nomor_dokumen,
            'document_revision_snapshot' => $snapshot['revision'] ?? $document->nomor_revisi,
            'download_context' => $snapshot['context'] ?? null,
            'user_id' => $request->user()?->id,
            'downloaded_at' => now(),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);
    }
}
