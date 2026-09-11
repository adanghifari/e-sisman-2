<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class AuditDocumentFileNumbers extends Command
{
    protected $signature = 'documents:file-numbers:audit {--json : Output machine-readable JSON}';

    protected $description = 'Report document file numbering rollout gaps without changing data.';

    public function handle(): int
    {
        $summary = [
            'total_files' => DB::table('t_document_files')->count(),
            'missing_document_number' => DB::table('t_document_files')->whereNull('document_number')->count(),
            'missing_parent_number' => DB::table('t_document_files')
                ->join('t_document', 't_document_files.t_document_id', '=', 't_document.id')
                ->whereNull('t_document.nomor_dokumen')
                ->count(),
            'main_files_missing_number' => DB::table('t_document_files')
                ->join('t_document', 't_document_files.t_document_id', '=', 't_document.id')
                ->whereIn('t_document_files.type_file', ['filled_template', 'revision_content', 'imported_document'])
                ->whereNotNull('t_document.nomor_dokumen')
                ->whereNull('t_document_files.document_number')
                ->count(),
            'revision_forms_missing_number' => DB::table('t_document_files')
                ->join('t_document', 't_document_files.t_document_id', '=', 't_document.id')
                ->where('t_document_files.type_file', 'revision_form')
                ->whereNotNull('t_document.nomor_dokumen')
                ->whereNull('t_document_files.document_number')
                ->count(),
            'attachments_missing_number' => DB::table('t_document_files')
                ->join('t_document', 't_document_files.t_document_id', '=', 't_document.id')
                ->where('t_document_files.type_file', 'attachment')
                ->whereNotNull('t_document.nomor_dokumen')
                ->whereNull('t_document_files.document_number')
                ->count(),
        ];

        if ($this->option('json')) {
            $this->line(json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }

        $this->table(
            ['Metric', 'Count'],
            collect($summary)
                ->map(fn (int $count, string $metric): array => [$metric, $count])
                ->values()
                ->all(),
        );

        if ($summary['attachments_missing_number'] > 0) {
            $this->warn('Some historical attachments are still unnumbered. Review manually before backfilling.');
        }

        return self::SUCCESS;
    }
}
