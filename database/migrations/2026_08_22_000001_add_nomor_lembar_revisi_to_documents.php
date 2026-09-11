<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('t_document', function (Blueprint $table): void {
            $table->string('nomor_lembar_revisi')->nullable()->after('nomor_dokumen');
        });

        $documents = DB::table('t_document')
            ->select([
                'id',
                'revised_from',
                'request_type',
                'm_document_level_id',
                'nomor_dokumen',
                'nomor_revisi',
            ])
            ->get();

        if ($documents->isEmpty()) {
            return;
        }

        $levelCodes = DB::table('m_document_levels')->pluck('kode', 'id');

        $documents
            ->filter(fn (object $document): bool => $this->shouldBackfillRevisionNumber($document))
            ->each(function (object $document) use ($documents, $levelCodes): void {
                $root = $this->rootDocument($document, $documents);
                $source = $documents->firstWhere('id', $document->revised_from);
                $logicalNumber = $root?->nomor_dokumen ?: $source?->nomor_dokumen ?: $document->nomor_dokumen;

                if (! filled($logicalNumber)) {
                    return;
                }

                DB::table('t_document')
                    ->where('id', $document->id)
                    ->update([
                        'nomor_dokumen' => $logicalNumber,
                        'nomor_lembar_revisi' => $this->revisionFormNumber(
                            (string) $logicalNumber,
                            (string) $levelCodes->get($root?->m_document_level_id ?? $source?->m_document_level_id ?? $document->m_document_level_id, ''),
                            (int) $document->nomor_revisi,
                        ),
                    ]);
            });
    }

    public function down(): void
    {
        if (
            Schema::hasColumn('t_document', 'nomor_lembar_revisi')
            && DB::table('t_document')->whereNotNull('nomor_lembar_revisi')->exists()
        ) {
            throw new RuntimeException('Rollback dibatalkan: nomor_lembar_revisi berisi data hasil backfill dan tidak bisa dipulihkan secara aman ke format lama.');
        }

        Schema::table('t_document', function (Blueprint $table): void {
            $table->dropColumn('nomor_lembar_revisi');
        });
    }

    private function shouldBackfillRevisionNumber(object $document): bool
    {
        if ($document->revised_from === null) {
            return false;
        }

        if ($document->request_type === 'revision') {
            return true;
        }

        return $document->request_type === null && (int) $document->nomor_revisi > 0;
    }

    private function rootDocument(object $document, Collection $documents): ?object
    {
        $current = $document;

        while ($current->revised_from !== null) {
            $parent = $documents->firstWhere('id', $current->revised_from);

            if ($parent === null) {
                break;
            }

            $current = $parent;
        }

        return $current;
    }

    private function revisionFormNumber(string $documentNumber, string $levelKey, int $revision): string
    {
        $prefix = match ($levelKey) {
            'level-1' => 'FMSM',
            'level-2' => 'FMPS',
            'level-3' => 'FMIK',
            default => 'FM',
        };
        $segments = collect(explode('-', $documentNumber))
            ->filter()
            ->values();

        if ($segments->isNotEmpty()) {
            $segments->shift();
        }

        return collect([$prefix])
            ->merge($segments)
            ->push(str_pad((string) $revision, 2, '0', STR_PAD_LEFT))
            ->filter()
            ->implode('-');
    }
};
