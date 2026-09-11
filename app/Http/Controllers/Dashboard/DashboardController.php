<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Http\Controllers\DocumentManagement\DocumentInboxController;
use App\Models\BusinessFunction;
use App\Models\BusinessProcess;
use App\Models\Department;
use App\Models\Document;
use App\Models\DocumentLevel;
use App\Models\StatusDocument;
use App\Queries\Log\DocumentDownloadActivityQuery;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class DashboardController extends Controller
{
    private const CHART_COLORS = [
        '#a855f7',
        '#22c55e',
        '#3b82f6',
        '#f59e0b',
        '#ec4899',
        '#14b8a6',
        '#ef4444',
        '#64748b',
    ];

    public function __invoke(
        Request $request,
        DocumentInboxController $documentInboxController,
        DocumentDownloadActivityQuery $activityQuery,
    ): View {
        $counts = $documentInboxController->dashboardCounts($request);

        return view('main.dashboard', [
            'summaryCards' => [
                [
                    'label' => 'Perlu Saya Proses',
                    'value' => $counts['needs_process'],
                    'hint' => 'Dokumen menunggu tindakan',
                    'tab' => 'needs-process',
                ],
                [
                    'label' => 'Riwayat yang Saya Proses',
                    'value' => $counts['processed_history'],
                    'hint' => 'Dokumen yang sudah diproses',
                    'tab' => 'processed-history',
                ],
            ],
            'needsProcessDocuments' => $documentInboxController->dashboardNeedsProcessRows($request, 3),
            'activities' => $activityQuery->dashboardRows(),
            'levelStatistics' => $this->levelStatistics(),
            'businessFunctionStatistics' => $this->businessFunctionStatistics(),
            'businessProcessTotals' => $this->businessProcessTotals(),
            'departmentTotals' => $this->departmentTotals(),
        ]);
    }

    private function levelStatistics(): array
    {
        $counts = $this->publishedDocumentBaseQuery()
            ->selectRaw('m_document_level_id as item_id, count(*) as total')
            ->groupBy('m_document_level_id')
            ->pluck('total', 'item_id');

        $revisionFormLevelId = DocumentLevel::query()
            ->where('kode', 'level-4')
            ->value('id');

        if ($revisionFormLevelId !== null) {
            $counts[$revisionFormLevelId] = (int) ($counts[$revisionFormLevelId] ?? 0)
                + $this->publishedDocumentBaseQuery()
                    ->whereNotNull('nomor_lembar_revisi')
                    ->count();
        }

        $items = DocumentLevel::query()
            ->active()
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->map(fn (DocumentLevel $level): array => [
                'label' => $this->levelLabel($level),
                'value' => (int) ($counts[$level->id] ?? 0),
            ]);

        return $this->chartDataset($items);
    }

    private function businessFunctionStatistics(): array
    {
        $counts = $this->masterDocumentBaseQuery()
            ->selectRaw('m_proses_fungsi_id as item_id, count(*) as total')
            ->groupBy('m_proses_fungsi_id')
            ->pluck('total', 'item_id');

        $items = BusinessFunction::query()
            ->active()
            ->orderBy('nama_proses_fungsi')
            ->orderBy('id')
            ->get()
            ->map(fn (BusinessFunction $businessFunction): array => [
                'label' => $businessFunction->nama_proses_fungsi,
                'value' => (int) ($counts[$businessFunction->id] ?? 0),
            ]);

        return $this->chartDataset($items);
    }

    private function businessProcessTotals(): array
    {
        $counts = $this->masterDocumentBaseQuery()
            ->selectRaw('m_proses_bisnis_id as item_id, count(*) as total')
            ->groupBy('m_proses_bisnis_id')
            ->pluck('total', 'item_id');

        return $this->totalDataset(
            BusinessProcess::query()
                ->active()
                ->orderBy('nama_proses_bisnis')
                ->orderBy('id')
                ->get()
                ->map(fn (BusinessProcess $businessProcess): array => [
                    'label' => $businessProcess->nama_proses_bisnis,
                    'value' => (int) ($counts[$businessProcess->id] ?? 0),
                ])
        );
    }

    private function departmentTotals(): array
    {
        $masterDocumentIds = $this->masterDocumentBaseQuery()->pluck('id');

        $counts = Document::query()
            ->whereIn('t_document.id', $masterDocumentIds)
            ->join('document_departments', 'document_departments.t_document_id', '=', 't_document.id')
            ->selectRaw('document_departments.department_id as item_id, count(distinct t_document.id) as total')
            ->groupBy('document_departments.department_id')
            ->pluck('total', 'item_id');

        return $this->totalDataset(
            Department::query()
                ->active()
                ->orderBy('nama_department')
                ->orderBy('id')
                ->get()
                ->map(fn (Department $department): array => [
                    'label' => $department->nama_department,
                    'value' => (int) ($counts[$department->id] ?? 0),
                ])
        );
    }

    private function masterDocumentBaseQuery()
    {
        return $this->publishedDocumentBaseQuery();
    }

    private function publishedDocumentBaseQuery()
    {
        $documentStatusIds = StatusDocument::query()
            ->whereIn('nama_status', [
                StatusDocument::APPROVED,
                StatusDocument::OBSOLETE,
            ])
            ->pluck('id');

        return Document::query()
            ->whereIn('m_status_document_id', $documentStatusIds)
            ->where(function ($query): void {
                $query
                    ->whereNull('request_type')
                    ->orWhere('request_type', '!=', 'obsolete');
            });
    }


    private function chartDataset(Collection $items): array
    {
        $coloredItems = $items
            ->values()
            ->map(fn (array $item, int $index): array => $item + [
                'color' => self::CHART_COLORS[$index % count(self::CHART_COLORS)],
            ]);
        $total = (int) $coloredItems->sum('value');
        $cursor = 0;
        $segments = [];

        foreach ($coloredItems as $index => $item) {
            if ($total <= 0 || $item['value'] <= 0) {
                continue;
            }

            $size = ($item['value'] / $total) * 100;
            $midpoint = $cursor + ($size / 2);
            $angle = deg2rad(($midpoint / 100) * 360 - 90);
            $segments[] = sprintf('%s %.4f%% %.4f%%', $item['color'], $cursor, $cursor + $size);
            $coloredItems[$index] = $item + [
                'percentage' => round($size, 1),
                'label_x' => 50 + (cos($angle) * 44),
                'label_y' => 50 + (sin($angle) * 44),
            ];
            $cursor += $size;
        }

        $coloredItems = $coloredItems->map(fn (array $item): array => $item + [
            'percentage' => 0,
            'label_x' => 50,
            'label_y' => 50,
        ]);

        return [
            'items' => $coloredItems,
            'total' => $total,
            'chart' => $segments === []
                ? 'conic-gradient(#e2e8f0 0% 100%)'
                : 'conic-gradient('.implode(', ', $segments).')',
        ];
    }

    private function totalDataset(Collection $items): array
    {
        $total = (int) $items->sum('value');

        return [
            'items' => $items
                ->values()
                ->map(fn (array $item): array => $item + [
                    'percentage' => $total > 0 ? round(($item['value'] / $total) * 100, 1) : 0,
                ]),
            'total' => $total,
        ];
    }

    private function levelLabel(DocumentLevel $level): string
    {
        $documentName = Str::after($level->nama_dokumen ?? '', ': ');

        return trim(($level->nama_level ?? '').' '.$documentName) ?: ($level->nama_dokumen ?? '-');
    }
}
