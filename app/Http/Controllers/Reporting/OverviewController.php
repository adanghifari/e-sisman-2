<?php

namespace App\Http\Controllers\Reporting;

use App\Http\Controllers\Controller;
use App\Models\BusinessFunction;
use App\Models\Department;
use App\Models\Document;
use App\Models\DocumentLevel;
use App\Models\DocumentRelation;
use App\Models\StatusDocument;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\StreamedResponse;

class OverviewController extends Controller
{
    private const CHART_COLORS = [
        '#0284c7',
        '#22c55e',
        '#8b5cf6',
        '#f59e0b',
        '#ec4899',
        '#14b8a6',
        '#ef4444',
        '#64748b',
    ];

    public function __invoke(Request $request): View
    {
        $filters = $this->filters($request);
        $manualLevelId = $this->documentLevelId('level-1');
        $procedureLevelId = $this->documentLevelId('level-2');
        $instructionLevelId = $this->documentLevelId('level-3');

        $procedures = $this->procedureQuery($filters, $procedureLevelId, $instructionLevelId)
            ->with(['businessFunction', 'departments'])
            ->orderBy('nomor_dokumen')
            ->orderBy('nama_dokumen')
            ->paginate(10)
            ->withQueryString();
        $overviewLevelIds = [$manualLevelId, $procedureLevelId, $instructionLevelId];

        return view('reporting.index', [
            'overviewFilters' => $filters,
            'overviewRows' => $this->overviewRows($procedures, $filters, $instructionLevelId),
            'overviewSummary' => $this->summary($manualLevelId, $procedureLevelId, $instructionLevelId),
            'trendStatistics' => $this->trendStatistics($overviewLevelIds, $filters['year']),
            'businessFunctionStatistics' => $this->businessFunctionStatistics($overviewLevelIds),
            'yearOptions' => $this->yearOptions($overviewLevelIds),
            'departmentOptions' => ['' => 'Semua Department'] + Department::query()
                ->active()
                ->orderBy('nama_department')
                ->pluck('nama_department', 'id')
                ->all(),
            'businessFunctionOptions' => ['' => 'Semua Proses / Fungsi'] + BusinessFunction::query()
                ->active()
                ->orderBy('nama_proses_fungsi')
                ->pluck('nama_proses_fungsi', 'id')
                ->all(),
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $filters = $this->filters($request);
        $procedureLevelId = $this->documentLevelId('level-2');
        $instructionLevelId = $this->documentLevelId('level-3');
        $procedures = $this->procedureQuery($filters, $procedureLevelId, $instructionLevelId)
            ->with(['businessFunction', 'departments', 'status'])
            ->orderBy('nomor_dokumen')
            ->orderBy('nama_dokumen')
            ->get();
        $rows = $this->exportRows($procedures, $filters, $instructionLevelId);
        $filename = 'overview-dokumen-'.now()->format('Ymd-His').'.csv';

        return response()->streamDownload(function () use ($rows): void {
            $output = fopen('php://output', 'w');

            fwrite($output, "\xEF\xBB\xBF");
            fputcsv($output, [
                'No',
                'Kategori',
                'Nama Dokumen',
                'Nomor Dokumen',
                'Revisi',
                'Induk Prosedur',
                'Department',
                'Proses/Fungsi',
                'Tanggal Terbit',
                'Status',
            ]);

            foreach ($rows as $row) {
                fputcsv($output, $row);
            }

            fclose($output);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * @return array{procedure: string, instruction: string, department_id: string, business_function_id: string, year: int}
     */
    private function filters(Request $request): array
    {
        $year = (int) $request->query('year', now()->year);

        return [
            'procedure' => trim((string) $request->query('procedure', '')),
            'instruction' => trim((string) $request->query('instruction', '')),
            'department_id' => trim((string) $request->query('department_id', '')),
            'business_function_id' => trim((string) $request->query('business_function_id', '')),
            'year' => $year > 0 ? $year : now()->year,
        ];
    }

    /**
     * @param  array{procedure: string, instruction: string, department_id: string, business_function_id: string, year: int}  $filters
     */
    private function procedureQuery(array $filters, ?int $procedureLevelId, ?int $instructionLevelId): Builder
    {
        return Document::query()
            ->where('m_document_level_id', $procedureLevelId)
            ->where($this->publishedDocumentScope())
            ->when($filters['procedure'] !== '', function (Builder $query) use ($filters): void {
                $query->where(function (Builder $query) use ($filters): void {
                    $query
                        ->where('nama_dokumen', 'like', '%'.$filters['procedure'].'%')
                        ->orWhere('nomor_dokumen', 'like', '%'.$filters['procedure'].'%');
                });
            })
            ->when($filters['department_id'] !== '', function (Builder $query) use ($filters): void {
                $query->whereHas('departments', fn (Builder $query) => $query->whereKey($filters['department_id']));
            })
            ->when($filters['business_function_id'] !== '', function (Builder $query) use ($filters): void {
                $query->where('m_proses_fungsi_id', $filters['business_function_id']);
            })
            ->when($filters['instruction'] !== '', function (Builder $query) use ($filters, $instructionLevelId): void {
                $query->whereExists(function ($query) use ($filters, $instructionLevelId): void {
                    $query
                        ->selectRaw('1')
                        ->from('t_document as instructions')
                        ->join('document_relations', 'document_relations.source_document_id', '=', 'instructions.id')
                        ->whereColumn('document_relations.target_document_id', 't_document.id')
                        ->where('document_relations.relation_type', DocumentRelation::REFERENCES)
                        ->where('instructions.m_document_level_id', $instructionLevelId)
                        ->whereIn('instructions.m_status_document_id', $this->publishedStatusIds())
                        ->where(function ($query): void {
                            $query
                                ->whereNull('instructions.request_type')
                                ->orWhere('instructions.request_type', '!=', 'obsolete');
                        })
                        ->where('instructions.nama_dokumen', 'like', '%'.$filters['instruction'].'%');
                });
            });
    }

    /**
     * @param  array{procedure: string, instruction: string, department_id: string, business_function_id: string, year: int}  $filters
     */
    private function overviewRows(LengthAwarePaginator $procedures, array $filters, ?int $instructionLevelId): LengthAwarePaginator
    {
        $procedureIds = $procedures->getCollection()->pluck('id');
        $instructions = Document::query()
            ->with(['businessFunction', 'departments'])
            ->join('document_relations', 'document_relations.source_document_id', '=', 't_document.id')
            ->whereIn('document_relations.target_document_id', $procedureIds)
            ->where('document_relations.relation_type', DocumentRelation::REFERENCES)
            ->where('m_document_level_id', $instructionLevelId)
            ->where($this->publishedDocumentScope())
            ->when($filters['instruction'] !== '', fn (Builder $query) => $query->where('nama_dokumen', 'like', '%'.$filters['instruction'].'%'))
            ->select('t_document.*', 'document_relations.target_document_id as procedure_reference_id')
            ->orderBy('nomor_dokumen')
            ->orderBy('nama_dokumen')
            ->get()
            ->sortByDesc(fn (Document $document): string => sprintf(
                '%010d-%010d',
                $document->numeric_revision,
                $document->id,
            ))
            ->groupBy('procedure_reference_id');

        $procedures->setCollection(
            $procedures->getCollection()->map(fn (Document $procedure): array => $this->formatProcedureRow(
                $procedure,
                $instructions->get($procedure->id, collect()),
            )),
        );

        return $procedures;
    }

    private function exportRows(Collection $procedures, array $filters, ?int $instructionLevelId): Collection
    {
        $procedureIds = $procedures->pluck('id');
        $instructions = Document::query()
            ->with(['businessFunction', 'departments', 'status'])
            ->join('document_relations', 'document_relations.source_document_id', '=', 't_document.id')
            ->whereIn('document_relations.target_document_id', $procedureIds)
            ->where('document_relations.relation_type', DocumentRelation::REFERENCES)
            ->where('m_document_level_id', $instructionLevelId)
            ->where($this->publishedDocumentScope())
            ->when($filters['instruction'] !== '', fn (Builder $query) => $query->where('nama_dokumen', 'like', '%'.$filters['instruction'].'%'))
            ->select('t_document.*', 'document_relations.target_document_id as procedure_reference_id')
            ->orderBy('nomor_dokumen')
            ->orderBy('nama_dokumen')
            ->get()
            ->sortByDesc(fn (Document $document): string => sprintf(
                '%010d-%010d',
                $document->numeric_revision,
                $document->id,
            ))
            ->groupBy('procedure_reference_id');
        $counter = 1;

        return $procedures->flatMap(function (Document $procedure) use ($instructions, &$counter): array {
            $rows = [
                $this->formatExportRow($counter++, 'Prosedur', $procedure),
            ];

            foreach ($instructions->get($procedure->id, collect()) as $instruction) {
                $rows[] = $this->formatExportRow(
                    $counter++,
                    'Instruksi Kerja',
                    $instruction,
                    trim(($procedure->nomor_dokumen ?: '-').' - '.$procedure->nama_dokumen),
                );
            }

            return $rows;
        })->values();
    }

    private function summary(?int $manualLevelId, ?int $procedureLevelId, ?int $instructionLevelId): array
    {
        $documents = $this->publishedDocuments([$manualLevelId, $procedureLevelId, $instructionLevelId]);

        return [
            [
                'label' => 'Total Manual',
                'value' => $documents->where('m_document_level_id', $manualLevelId)->count(),
                'hint' => 'Dokumen Level I',
                'tone' => 'sky',
            ],
            [
                'label' => 'Total Prosedur',
                'value' => $documents->where('m_document_level_id', $procedureLevelId)->count(),
                'hint' => 'Dokumen Level II',
                'tone' => 'emerald',
            ],
            [
                'label' => 'Total Instruksi Kerja',
                'value' => $documents->where('m_document_level_id', $instructionLevelId)->count(),
                'hint' => 'Dokumen Level III',
                'tone' => 'violet',
            ],
        ];
    }

    private function trendStatistics(array $levelIds, int $year): array
    {
        $months = collect(range(1, 12))
            ->map(fn (int $month) => now()->setYear($year)->setMonth($month)->startOfMonth());
        $documents = $this->publishedDocuments($levelIds);
        $items = $months->map(function ($month) use ($documents): array {
            $value = $documents
                ->filter(function (Document $document) use ($month): bool {
                    $date = $document->tanggal_terbit ?? $document->approved_at;

                    return $date !== null && $date->isSameMonth($month);
                })
                ->count();

            return [
                'label' => $month->translatedFormat('M'),
                'value' => $value,
            ];
        });
        $max = max(1, (int) $items->max('value'));
        $points = $items
            ->values()
            ->map(function (array $item, int $index) use ($items, $max): array {
                $x = $items->count() <= 1 ? 490 : 24 + (($index / ($items->count() - 1)) * 932);
                $y = 230 - (($item['value'] / $max) * 170);

                return $item + [
                    'x' => round($x, 2),
                    'y' => round($y, 2),
                ];
            });

        return [
            'items' => $points,
            'total' => $items->sum('value'),
            'path' => $points->map(fn (array $item, int $index): string => ($index === 0 ? 'M ' : 'L ').$item['x'].' '.$item['y'])->join(' '),
            'smooth_path' => $this->smoothPath($points),
            'area_path' => $this->areaPath($points),
            'year' => $year,
        ];
    }

    private function businessFunctionStatistics(array $levelIds): array
    {
        $counts = $this->publishedDocuments($levelIds)
            ->groupBy('m_proses_fungsi_id')
            ->map(fn (Collection $documents): int => $documents->count());
        $items = BusinessFunction::query()
            ->active()
            ->orderBy('nama_proses_fungsi')
            ->get()
            ->map(fn (BusinessFunction $businessFunction): array => [
                'label' => $businessFunction->nama_proses_fungsi,
                'value' => (int) ($counts[$businessFunction->id] ?? 0),
            ])
            ->filter(fn (array $item): bool => $item['value'] > 0)
            ->values();
        $total = (int) $items->sum('value');
        $cursor = 0;
        $segments = [];

        $items = $items->map(function (array $item, int $index) use ($total, &$cursor, &$segments): array {
            $color = self::CHART_COLORS[$index % count(self::CHART_COLORS)];

            if ($item['value'] > 0 && $total > 0) {
                $size = ($item['value'] / $total) * 100;
                $segments[] = sprintf('%s %.4f%% %.4f%%', $color, $cursor, $cursor + $size);
                $cursor += $size;
            }

            return $item + [
                'color' => $color,
                'percentage' => $total > 0 ? round(($item['value'] / $total) * 100, 1) : 0,
            ];
        });

        return [
            'items' => $items,
            'total' => $total,
            'chart' => $segments === []
                ? 'conic-gradient(#e2e8f0 0% 100%)'
                : 'conic-gradient('.implode(', ', $segments).')',
        ];
    }

    private function smoothPath(Collection $points): string
    {
        if ($points->isEmpty()) {
            return '';
        }

        $path = 'M '.$points[0]['x'].' '.$points[0]['y'];

        for ($index = 1; $index < $points->count(); $index++) {
            $previous = $points[$index - 1];
            $current = $points[$index];
            $controlX = round(($previous['x'] + $current['x']) / 2, 2);

            $path .= ' C '.$controlX.' '.$previous['y'].', '.$controlX.' '.$current['y'].', '.$current['x'].' '.$current['y'];
        }

        return $path;
    }

    private function areaPath(Collection $points): string
    {
        if ($points->isEmpty()) {
            return '';
        }

        return $this->smoothPath($points).' L '.$points->last()['x'].' 230 L '.$points->first()['x'].' 230 Z';
    }

    private function yearOptions(array $levelIds): array
    {
        $years = $this->publishedDocuments($levelIds)
            ->map(fn (Document $document) => $document->tanggal_terbit ?? $document->approved_at)
            ->filter()
            ->map(fn ($date): int => (int) $date->format('Y'))
            ->push(now()->year)
            ->unique()
            ->sortDesc()
            ->values();

        return $years
            ->mapWithKeys(fn (int $year): array => [$year => (string) $year])
            ->all();
    }

    private function formatProcedureRow(Document $procedure, Collection $instructions): array
    {
        return [
            'id' => $procedure->id,
            'procedure' => $procedure->nama_dokumen,
            'number' => $procedure->nomor_dokumen,
            'revision' => $procedure->formatted_revision,
            'departments' => $procedure->departments->pluck('nama_department')->values(),
            'business_function' => $procedure->businessFunction?->nama_proses_fungsi ?? '-',
            'published_at' => $procedure->tanggal_terbit?->format('d M Y') ?? '-',
            'instructions' => $instructions
                ->values()
                ->map(fn (Document $instruction): array => [
                    'id' => $instruction->id,
                    'name' => $instruction->nama_dokumen,
                    'number' => $instruction->nomor_dokumen,
                    'revision' => $instruction->formatted_revision,
                    'departments' => $instruction->departments->pluck('nama_department')->values(),
                    'business_function' => $instruction->businessFunction?->nama_proses_fungsi ?? '-',
                    'published_at' => $instruction->tanggal_terbit?->format('d M Y') ?? '-',
                ]),
        ];
    }

    private function formatExportRow(int $number, string $category, Document $document, string $parentProcedure = '-'): array
    {
        return [
            $number,
            $category,
            $document->nama_dokumen,
            $document->nomor_dokumen ?: '-',
            $document->formatted_revision,
            $parentProcedure,
            $document->departments->pluck('nama_department')->join(', ') ?: '-',
            $document->businessFunction?->nama_proses_fungsi ?? '-',
            $document->tanggal_terbit?->format('d/m/Y') ?? '-',
            $document->status?->nama_status ?? '-',
        ];
    }

    private function publishedDocumentScope(): callable
    {
        $statusIds = $this->publishedStatusIds();

        return fn (Builder $query) => $query
            ->whereIn('m_status_document_id', $statusIds)
            ->where(function (Builder $query): void {
                $query
                    ->whereNull('request_type')
                    ->orWhere('request_type', '!=', 'obsolete');
            });
    }

    private function publishedDocuments(array $levelIds): Collection
    {
        return Document::query()
            ->with(['businessFunction'])
            ->whereIn('m_document_level_id', collect($levelIds)->filter()->values())
            ->where($this->publishedDocumentScope())
            ->get();
    }

    private function publishedStatusIds(): Collection
    {
        return StatusDocument::query()
            ->whereIn('nama_status', [
                StatusDocument::APPROVED,
                StatusDocument::OBSOLETE,
            ])
            ->pluck('id');
    }

    private function documentLevelId(string $level): ?int
    {
        return DocumentLevel::query()
            ->where('kode', $level)
            ->value('id');
    }
}
