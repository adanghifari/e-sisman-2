<?php

namespace App\Queries\Log;

use App\Models\Document;
use App\Models\DocumentDownloadLog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class DocumentDownloadActivityQuery
{
    /**
     * @param  array{document_name?: string, document_number?: string, downloaded_by?: string}  $filters
     */
    public function rows(array $filters = []): Collection
    {
        return $this->builder($filters)
            ->get()
            ->map(fn ($activity): array => $this->formatRow($activity));
    }

    public function total(): int
    {
        return DB::query()
            ->fromSub($this->builder()->toBase(), 'activity_logs')
            ->count();
    }

    public function dashboardRows(int $limit = 10): Collection
    {
        return $this->builder()
            ->limit($limit)
            ->get()
            ->map(function ($activity): array {
                $number = $activity->number ?: '-';
                $revision = $this->formatRevision((int) $activity->revision);

                return [
                    'downloaded_by' => $activity->downloaded_by ?: '-',
                    'number' => $number,
                    'display_number' => trim($number.' ke '.$revision),
                    'name' => $activity->name,
                    'is_obsolete' => $activity->download_context === 'obsolete',
                    'time' => Carbon::parse($activity->downloaded_at)->format('d/m/Y H:i'),
                ];
            });
    }

    /**
     * @param  array{document_name?: string, document_number?: string, downloaded_by?: string}  $filters
     */
    private function builder(array $filters = []): Builder
    {
        return DocumentDownloadLog::query()
            ->join('t_document', 't_document_download_logs.t_document_id', '=', 't_document.id')
            ->leftJoin('users', 't_document_download_logs.user_id', '=', 'users.id')
            ->select([
                't_document_download_logs.id as log_id',
                't_document.id as document_id',
                DB::raw('COALESCE(t_document_download_logs.document_name_snapshot, t_document.nama_dokumen) as name'),
                DB::raw('COALESCE(t_document_download_logs.document_number_snapshot, t_document.nomor_dokumen) as number'),
                DB::raw('COALESCE(t_document_download_logs.document_revision_snapshot, t_document.nomor_revisi) as revision'),
                DB::raw("COALESCE(users.name, '-') as downloaded_by"),
                't_document_download_logs.download_context',
                't_document_download_logs.downloaded_at',
                DB::raw('(
                    SELECT COUNT(*)
                    FROM t_document_download_logs AS previous_logs
                    WHERE previous_logs.t_document_id = t_document_download_logs.t_document_id
                    AND (
                        previous_logs.downloaded_at < t_document_download_logs.downloaded_at
                        OR (
                            previous_logs.downloaded_at = t_document_download_logs.downloaded_at
                            AND previous_logs.id <= t_document_download_logs.id
                        )
                    )
                ) as count'),
            ])
            ->when(($filters['document_name'] ?? '') !== '', function ($query) use ($filters): void {
                $query->whereRaw(
                    'COALESCE(t_document_download_logs.document_name_snapshot, t_document.nama_dokumen) like ?',
                    ['%'.$filters['document_name'].'%'],
                );
            })
            ->when(($filters['document_number'] ?? '') !== '', function ($query) use ($filters): void {
                $query->whereRaw(
                    'COALESCE(t_document_download_logs.document_number_snapshot, t_document.nomor_dokumen) like ?',
                    ['%'.$filters['document_number'].'%'],
                );
            })
            ->when(($filters['downloaded_by'] ?? '') !== '', function ($query) use ($filters): void {
                $query->where('users.name', 'like', '%'.$filters['downloaded_by'].'%');
            })
            ->orderByDesc('t_document_download_logs.downloaded_at')
            ->orderByDesc('t_document_download_logs.id');
    }

    private function formatRow(object $activity): array
    {
        return [
            'name' => $activity->name,
            'number' => $activity->number ?: '-',
            'revision' => $this->formatRevision((int) $activity->revision),
            'downloaded_by' => $activity->downloaded_by ?: '-',
            'downloaded_at' => Carbon::parse($activity->downloaded_at)->format('d/m/Y H:i'),
            'count' => (int) $activity->count,
        ];
    }

    private function formatRevision(int $revision): string
    {
        return Document::formatRevisionNumber($revision);
    }
}
