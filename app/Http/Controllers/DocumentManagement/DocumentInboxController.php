<?php

namespace App\Http\Controllers\DocumentManagement;

use App\Http\Controllers\Controller;
use App\Models\Approval;
use App\Models\ApprovalStatus;
use App\Models\Document;
use App\Models\DocumentType;
use App\Models\StatusDocument;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class DocumentInboxController extends Controller
{
    private const BATCH_SIZE = 15;

    /**
     * @var array<int, Document|null>
     */
    private array $rootDocumentCache = [];

    public function __invoke(Request $request): View|JsonResponse
    {
        $filters = $this->filters($request);
        $requestedTab = (string) $request->query('tab', '');
        $activeTab = in_array($requestedTab, ['needs-process', 'processed-history'], true) ? $requestedTab : 'needs-process';
        $activePage = $this->activePage($request, $activeTab);

        if ($request->boolean('load_more')) {
            $activeTotal = match ($activeTab) {
                'processed-history' => $this->myProcessedHistoryQuery($request, $filters)->count(),
                default => $this->myTasksQuery($request, $filters)->count(),
            };
            $activeBatch = $this->batchFor($request, $activeTab, $filters, $activePage, $activeTotal);

            return response()->json([
                'rows' => view($this->rowPartialFor($activeTab), [
                    'documents' => $activeBatch['rows'],
                ])->render(),
                'next_page' => $activeBatch['has_more'] ? $activePage + 1 : null,
                'has_more' => $activeBatch['has_more'],
                'displayed_count' => min($activePage * self::BATCH_SIZE, $activeBatch['total']),
                'total' => $activeBatch['total'],
            ]);
        }

        $taskCount = $this->myTasksQuery($request, $filters)->count();
        $processedHistoryCount = $this->myProcessedHistoryQuery($request, $filters)->count();

        $tabs = [
            'needs-process' => [
                'label' => 'Perlu Saya Proses',
                'count' => $taskCount,
            ],
            'processed-history' => [
                'label' => 'Riwayat yang Saya Proses',
                'count' => $processedHistoryCount,
            ],
        ];

        $activeResultCount = match ($activeTab) {
            'processed-history' => $processedHistoryCount,
            default => $taskCount,
        };
        $activeBatch = $this->batchFor($request, $activeTab, $filters, $activePage, $activeResultCount);

        return view('document-management.inbox', [
            'tabs' => $tabs,
            'activeTab' => $activeTab,
            'filters' => $filters,
            'typeOptions' => $this->typeOptions(),
            'statusOptions' => $this->statusOptions(),
            'stageOptions' => $this->stageOptions($request, $activeTab),
            'sortOptions' => [
                'newest' => 'Terbaru',
                'oldest' => 'Terlama',
                'name_asc' => 'Nama A-Z',
                'name_desc' => 'Nama Z-A',
            ],
            'filteredMyTasks' => $activeTab === 'needs-process' ? $activeBatch['rows'] : collect(),
            'filteredMyProcessedHistory' => $activeTab === 'processed-history' ? $activeBatch['rows'] : collect(),
            'activeResultCount' => $activeResultCount,
            'loadedResultCount' => $activeBatch['rows']->count(),
            'hasMoreResults' => $activeBatch['has_more'],
            'nextPage' => $activeBatch['has_more'] ? $activePage + 1 : null,
        ]);
    }

    public function needsProcessCount(Request|User|null $userOrRequest = null): int
    {
        if ($userOrRequest instanceof Request) {
            $request = $userOrRequest;
            $user = $request->user();
        } elseif ($userOrRequest instanceof User) {
            $user = $userOrRequest;
            $request = request();
            if ($request->user()?->id !== $user->id) {
                $request = (clone $request)->setUserResolver(fn () => $user);
            }
        } else {
            $user = auth()->user();
            $request = request();
        }

        if (! $user) {
            return 0;
        }

        return once(function () use ($request): int {
            $filters = [
                'search' => '',
                'type' => '',
                'status' => '',
                'stage' => '',
                'sort' => 'newest',
            ];

            return $this->myTasksQuery($request, $filters)->count();
        });
    }

    /**
     * @return array{needs_process: int, processed_history: int}
     */
    public function dashboardCounts(Request $request): array
    {
        $filters = [
            'search' => '',
            'type' => '',
            'status' => '',
            'stage' => '',
            'sort' => 'newest',
        ];

        return [
            'needs_process' => $this->needsProcessCount($request),
            'processed_history' => $this->myProcessedHistoryQuery($request, $filters)->count(),
        ];
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function dashboardNeedsProcessRows(Request $request, int $limit = 3): Collection
    {
        $filters = [
            'search' => '',
            'type' => '',
            'status' => '',
            'stage' => '',
            'sort' => 'newest',
        ];

        return $this->myTasksQuery($request, $filters)
            ->limit($limit)
            ->get()
            ->map(fn (Document $document): array => $this->approvalRow(
                $document,
                $this->taskApproval($document, $request->user()),
                $request->user()->isAdmin() || $request->user()->canAssignDocument($document),
                $request->user(),
            ))
            ->values();
    }

    /**
     * @return array{search: string, type: string, status: string, stage: string, sort: string}
     */
    private function filters(Request $request): array
    {
        return [
            'search' => trim((string) $request->query('search', '')),
            'type' => (string) $request->query('type', ''),
            'status' => (string) $request->query('status', ''),
            'stage' => (string) $request->query('stage', ''),
            'sort' => (string) $request->query('sort', 'newest'),
        ];
    }

    private function activePage(Request $request, string $activeTab): int
    {
        $pageKey = $activeTab === 'processed-history' ? 'history_page' : 'needs_page';

        return max(1, (int) $request->query($pageKey, 1));
    }

    /**
     * @return array{rows: Collection<int, array<string, mixed>>, total: int, has_more: bool}
     */
    private function batchFor(Request $request, string $activeTab, array $filters, int $page, int $total): array
    {
        $query = match ($activeTab) {
            'processed-history' => $this->myProcessedHistoryQuery($request, $filters),
            default => $this->myTasksQuery($request, $filters),
        };
        $documents = $query
            ->forPage($page, self::BATCH_SIZE)
            ->get();
        $rows = match ($activeTab) {
            'processed-history' => $documents->map(fn (Document $document): array => $this->processedHistoryRow(
                $document,
                $this->processedHistoryApproval($document, $request->user()),
                $request->user(),
            )),
            default => $documents->map(fn (Document $document): array => $this->approvalRow(
                $document,
                $this->taskApproval($document, $request->user()),
                $request->user()->isAdmin() || $request->user()->canAssignDocument($document),
                $request->user(),
            )),
        };

        return [
            'rows' => $rows->values(),
            'total' => $total,
            'has_more' => $page * self::BATCH_SIZE < $total,
        ];
    }

    private function rowPartialFor(string $activeTab): string
    {
        return match ($activeTab) {
            'processed-history' => 'document-management.partials.inbox-processed-history-rows',
            default => 'document-management.partials.inbox-needs-process-rows',
        };
    }

    private function myTasksQuery(Request $request, array $filters): Builder
    {
        $approvalScope = $this->approvalScope($request, processed: false);
        $assignedMonitorApprovalScope = $this->assignedMonitorApprovalScope($request);
        $assignedMonitorDocumentScope = $this->assignedMonitorDocumentScope($request);
        $assignableDocumentScope = $this->assignableDocumentScope($request);

        $query = Document::query()
            ->select('t_document.*')
            ->withExists([
                'approvals as has_flow_approvals' => function ($query): void {
                    $query->where('stages', '!=', 'TTD Penyusun Resmi');
                },
            ])
            ->with($this->taskEagerLoads($approvalScope, $assignedMonitorApprovalScope));

        $relevantDocumentIds = Approval::query()
            ->select('t_document_id')
            ->where($approvalScope);
        $relevantDocumentIds->union(
            Document::query()
                ->select('id')
                ->where($assignedMonitorDocumentScope),
        );

        if ($assignableDocumentScope !== null) {
            $relevantDocumentIds->union(
                Document::query()
                    ->select('id')
                    ->where($assignableDocumentScope),
            );
        }

        $query->whereIn('id', $relevantDocumentIds);

        $this->applyTaskFilters($query, $request, $filters, $approvalScope, $assignedMonitorApprovalScope);
        $this->applyOrdering($query, $request, $filters, history: false);

        return $query;
    }

    private function myProcessedHistoryQuery(Request $request, array $filters): Builder
    {
        $approvalScope = $this->approvalScope($request, processed: true, includeAllForDeveloper: false);
        $assignedApprovalScope = $this->assignedApprovalScope($request);
        $assignedMonitorDocumentScope = $this->assignedMonitorDocumentScope($request);
        $user = $request->user();

        $relevantDocumentIds = Approval::query()
            ->select('t_document_id')
            ->where($approvalScope);
        $relevantDocumentIds->union(
            Approval::query()
                ->select('t_document_id')
                ->where($assignedApprovalScope),
        );

        $relevantDocumentIds->union(
            Document::query()
                ->select('id')
                ->where(function ($query) use ($user): void {
                    $query
                        ->where('user_id', $user->id)
                        ->orWhere('official_preparer_id', $user->id);
                }),
        );

        $query = Document::query()
            ->select('t_document.*')
            ->with($this->historyEagerLoads($approvalScope, $assignedApprovalScope))
            ->whereIn('id', $relevantDocumentIds)
            ->whereNot($assignedMonitorDocumentScope);

        $this->applyHistoryFilters($query, $request, $filters, $approvalScope, $assignedApprovalScope);
        $this->applyOrdering($query, $request, $filters, history: true);

        return $query;
    }

    /**
     * @return array<string, mixed>
     */
    private function taskEagerLoads(callable $approvalScope, callable $assignedMonitorApprovalScope): array
    {
        return [
            'documentLevel',
            'documentType',
            'creator',
            'status',
            'departments',
            'revisedFrom.documentLevel',
            'revisedFrom.documentType',
            'approvals' => function ($query) use ($approvalScope, $assignedMonitorApprovalScope): void {
                $query->where(function ($query) use ($approvalScope, $assignedMonitorApprovalScope): void {
                    $query
                        ->where($approvalScope)
                        ->orWhere($assignedMonitorApprovalScope);
                });
                $query->with(['status', 'approver'])->orderByDesc('assigned_at');
            },
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function historyEagerLoads(callable $approvalScope, callable $assignedApprovalScope): array
    {
        return [
            'documentLevel',
            'documentType',
            'creator',
            'status',
            'departments',
            'revisedFrom.documentLevel',
            'revisedFrom.documentType',
            'approvals' => function ($query) use ($approvalScope, $assignedApprovalScope): void {
                $query->where(function ($query) use ($approvalScope, $assignedApprovalScope): void {
                    $query->where($approvalScope)
                        ->orWhere($assignedApprovalScope);
                });
                $query->with(['status', 'approver'])->orderByDesc('responded_at');
            },
        ];
    }

    private function applyTaskFilters(
        Builder $query,
        Request $request,
        array $filters,
        callable $approvalScope,
        callable $assignedMonitorApprovalScope,
    ): void {
        $this->applyCommonFilters($query, $filters);

        if ($filters['stage'] !== '') {
            $query->where(function ($query) use ($filters, $approvalScope, $assignedMonitorApprovalScope): void {
                if ($filters['stage'] === 'Belum assign approver') {
                    $query->whereDoesntHave('approvals', fn ($query) => $query->where('stages', '!=', 'TTD Penyusun Resmi'));

                    return;
                }

                $query->whereHas('approvals', function ($query) use ($filters, $approvalScope, $assignedMonitorApprovalScope): void {
                    $query
                        ->where('stages', $filters['stage'])
                        ->where(function ($query) use ($approvalScope, $assignedMonitorApprovalScope): void {
                            $query->where($approvalScope)
                                ->orWhere($assignedMonitorApprovalScope);
                        });
                });
            });
        }

        if ($filters['search'] !== '') {
            $this->applySearch($query, $request, $filters['search'], $approvalScope, $assignedMonitorApprovalScope);
        }
    }

    private function applyHistoryFilters(
        Builder $query,
        Request $request,
        array $filters,
        callable $approvalScope,
        callable $assignedApprovalScope,
    ): void {
        $this->applyCommonFilters($query, $filters);

        if ($filters['stage'] !== '') {
            $this->applyHistoryStageFilter($query, $request, $filters['stage'], $approvalScope, $assignedApprovalScope);
        }

        if ($filters['search'] !== '') {
            $this->applySearch($query, $request, $filters['search'], $approvalScope, $assignedApprovalScope);
        }
    }

    private function applyHistoryStageFilter(
        Builder $query,
        Request $request,
        string $stage,
        callable $approvalScope,
        callable $assignedApprovalScope,
    ): void {
        match ($stage) {
            'Assign Approver' => $query->whereHas('approvals', $this->assignedApprovalScope($request)),
            'Pengajuan Revisi' => $query
                ->where('user_id', $request->user()->id)
                ->whereNotNull('revised_from'),
            'Pengajuan Dokumen' => $query
                ->where('user_id', $request->user()->id)
                ->whereNull('revised_from'),
            'TTD Penyusun Resmi' => $query
                ->where('official_preparer_id', $request->user()->id)
                ->where('user_id', '!=', $request->user()->id),
            default => $query->whereHas('approvals', function ($query) use ($stage, $approvalScope, $assignedApprovalScope): void {
                $query
                    ->where('stages', $stage)
                    ->where(function ($query) use ($approvalScope, $assignedApprovalScope): void {
                        $query->where($approvalScope)
                            ->orWhere($assignedApprovalScope);
                    });
            }),
        };
    }

    private function applyCommonFilters(Builder $query, array $filters): void
    {
        if ($filters['type'] !== '') {
            $type = $filters['type'] === 'Instruksi Kerja' ? 'IK' : $filters['type'];
            $query->whereHas('documentType', fn ($query) => $query->where('nama_types', $type));
        }

        if ($filters['status'] !== '') {
            $query->where(function ($query) use ($filters): void {
                $query->whereHas('status', fn ($query) => $query->where('nama_status', $filters['status']));
                $query->orWhereHas('approvals.status', fn ($query) => $query->where('nama_status', $filters['status']));
            });
        }
    }

    private function applySearch(
        Builder $query,
        Request $request,
        string $search,
        callable $primaryApprovalScope,
        callable $secondaryApprovalScope,
    ): void {
        $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], strtolower($search)).'%';

        $query->where(function ($query) use ($request, $like, $primaryApprovalScope, $secondaryApprovalScope): void {
            $query
                ->whereRaw('lower(`nama_dokumen`) like ?', [$like])
                ->orWhereRaw('lower(coalesce(`nomor_dokumen`, "")) like ?', [$like])
                ->orWhereRaw('lower(coalesce(`nomor_lembar_revisi`, "")) like ?', [$like])
                ->orWhereRaw('lower(coalesce(`request_type`, "")) like ?', [$like])
                ->orWhereHas('documentType', fn ($query) => $query->whereRaw('lower(`nama_types`) like ?', [$like]))
                ->orWhereHas('creator', fn ($query) => $query->whereRaw('lower(`name`) like ?', [$like]))
                ->orWhereHas('departments', function ($query) use ($like): void {
                    $query
                        ->whereRaw('lower(coalesce(`kode_department`, "")) like ?', [$like])
                        ->orWhereRaw('lower(`nama_department`) like ?', [$like]);
                })
                ->orWhereHas('status', fn ($query) => $query->whereRaw('lower(`nama_status`) like ?', [$like]))
                ->orWhereHas('approvals', function ($query) use ($like, $primaryApprovalScope, $secondaryApprovalScope): void {
                    $query
                        ->where(function ($query) use ($primaryApprovalScope, $secondaryApprovalScope): void {
                            $query->where($primaryApprovalScope)
                                ->orWhere($secondaryApprovalScope);
                        })
                        ->where(function ($query) use ($like): void {
                            $query
                                ->whereRaw('lower(coalesce(`stages`, "")) like ?', [$like])
                                ->orWhereHas('approver', fn ($query) => $query->whereRaw('lower(`name`) like ?', [$like]))
                                ->orWhereHas('status', fn ($query) => $query->whereRaw('lower(`nama_status`) like ?', [$like]));
                        });
                });

            if ($request->user()->isAdmin() || $request->user()->hasExplicitPermission('documents.approval.assign')) {
                $query->orWhereRaw('? like ?', ['perlu verifikasi admin kd', $like]);
                $query->orWhereRaw('? like ?', ['belum assign approver', $like]);
            }
        });
    }

    private function applyOrdering(Builder $query, Request $request, array $filters, bool $history): void
    {
        if (in_array($filters['sort'], ['name_asc', 'name_desc'], true)) {
            $query
                ->orderBy('nama_dokumen', $filters['sort'] === 'name_asc' ? 'asc' : 'desc')
                ->orderBy('id', $filters['sort'] === 'name_asc' ? 'asc' : 'desc');

            return;
        }

        $direction = $filters['sort'] === 'oldest' ? 'asc' : 'desc';
        $sortExpression = $history
            ? $this->historySortExpression($request)
            : $this->taskSortExpression($request);

        $query
            ->orderByRaw($sortExpression.' '.$direction)
            ->orderBy('id', $direction);
    }

    private function taskSortExpression(Request $request): string
    {
        $userId = (int) $request->user()->id;
        $userClause = $request->user()->isDeveloper() ? '1 = 1' : "user_id = {$userId}";

        return "coalesce((
            select max(assigned_at)
            from t_approval
            where t_approval.t_document_id = t_document.id
                and stages != 'TTD Penyusun Resmi'
                and responded_at is null
                and (
                    ({$userClause})
                    or assigned_by = {$userId}
                )
        ), submitted_at, created_at)";
    }

    private function historySortExpression(Request $request): string
    {
        $userId = (int) $request->user()->id;

        return "coalesce((
            select max(coalesce(responded_at, assigned_at))
            from t_approval
            where t_approval.t_document_id = t_document.id
                and stages != 'TTD Penyusun Resmi'
                and (
                    (user_id = {$userId} and responded_at is not null)
                    or assigned_by = {$userId}
                )
        ), submitted_at, created_at)";
    }

    private function typeOptions(): array
    {
        return ['' => 'Semua Jenis'] + DocumentType::query()
            ->orderBy('nama_types')
            ->pluck('nama_types')
            ->mapWithKeys(fn (string $value): array => [$this->typeOptionLabel($value) => $this->typeOptionLabel($value)])
            ->all();
    }

    private function statusOptions(): array
    {
        $documentStatuses = StatusDocument::query()->pluck('nama_status');
        $approvalStatuses = ApprovalStatus::query()->pluck('nama_status');

        return ['' => 'Semua Status'] + $documentStatuses
            ->merge($approvalStatuses)
            ->unique()
            ->sort()
            ->mapWithKeys(fn (string $value): array => [$value => $value])
            ->all();
    }

    private function stageOptions(Request $request, string $activeTab): array
    {
        $query = Approval::query()
            ->whereNotNull('stages')
            ->where('stages', '!=', 'TTD Penyusun Resmi')
            ->where(function ($query): void {
                $query
                    ->where('stages', '!=', 'Disusun Oleh')
                    ->orWhereDoesntHave('document', function ($query): void {
                        $query->whereColumn('t_document.official_preparer_id', 't_approval.user_id');
                    });
            });

        if ($activeTab === 'processed-history') {
            $query->where(function ($query) use ($request): void {
                $query
                    ->where(function ($query) use ($request): void {
                        $query
                            ->where('user_id', $request->user()->id)
                            ->whereNotNull('responded_at');
                    })
                    ->orWhere('assigned_by', $request->user()->id);
            });
        } else {
            $query->where(function ($query) use ($request): void {
                $query
                    ->where(function ($query) use ($request): void {
                        $query
                            ->when(! $request->user()->isDeveloper(), fn ($query) => $query->where('user_id', $request->user()->id))
                            ->whereNull('responded_at');
                    })
                    ->orWhere(function ($query) use ($request): void {
                        $query
                            ->where('assigned_by', $request->user()->id)
                            ->whereNull('responded_at');
                    });
            });
        }

        $stages = $query
            ->distinct()
            ->orderBy('stages')
            ->pluck('stages');

        if ($activeTab === 'needs-process') {
            $stages = $stages->push('Belum assign approver');
        } else {
            $stages = $stages->merge(['Pengajuan Dokumen', 'Pengajuan Revisi', 'Assign Approver']);
        }

        return ['' => 'Semua Tahap'] + $stages
            ->filter()
            ->unique()
            ->sort()
            ->mapWithKeys(fn (string $value): array => [$value => $value])
            ->all();
    }

    private function typeOptionLabel(string $type): string
    {
        return $type === 'IK' ? 'Instruksi Kerja' : $type;
    }

    private function processedHistoryApproval(Document $document, User $user): ?Approval
    {
        $respondedApproval = $document->approvals->first(
            fn (Approval $approval): bool => $approval->user_id === $user->id
                && $approval->responded_at !== null
                && ! $approval->shouldHideAsOfficialPreparerDisplay($document),
        );

        if ($respondedApproval !== null) {
            return $respondedApproval;
        }

        $assignedApproval = $document->approvals->first(
            fn (Approval $approval): bool => $approval->assigned_by === $user->id
                && ! $approval->shouldHideAsOfficialPreparerDisplay($document),
        );

        if ($assignedApproval !== null) {
            return $assignedApproval;
        }

        if ($document->user_id === $user->id) {
            return null;
        }

        return $document->approvals
            ->reject(fn (Approval $approval): bool => $approval->shouldHideAsOfficialPreparerDisplay($document))
            ->first();
    }

    private function approvalScope(Request $request, bool $processed, bool $includeAllForDeveloper = true): callable
    {
        return function ($query) use ($request, $processed, $includeAllForDeveloper) {
            $query
                ->when(
                    ! $includeAllForDeveloper || ! $request->user()->isDeveloper(),
                    fn ($query) => $query->where('user_id', $request->user()->id),
                )
                ->when(
                    $processed,
                    fn ($query) => $query->whereNotNull('responded_at'),
                    fn ($query) => $query
                        ->whereNull('responded_at')
                        ->whereHas('status', fn ($query) => $query->where('kode_status', ApprovalStatus::PENDING)),
                );
        };
    }

    private function assignedMonitorApprovalScope(Request $request): callable
    {
        return function ($query) use ($request): void {
            $query
                ->where('assigned_by', $request->user()->id)
                ->where('stages', '!=', 'TTD Penyusun Resmi')
                ->whereNull('responded_at')
                ->whereHas('status', fn ($query) => $query->whereIn('kode_status', [
                    ApprovalStatus::PENDING,
                    ApprovalStatus::WAITING,
                ]));
        };
    }

    private function assignedMonitorDocumentScope(Request $request): callable
    {
        $assignedMonitorApprovalScope = $this->assignedMonitorApprovalScope($request);

        return function ($query) use ($assignedMonitorApprovalScope): void {
            $query
                ->whereDoesntHave('status', fn ($query) => $query->whereIn('nama_status', [
                    StatusDocument::APPROVED,
                    StatusDocument::OBSOLETE,
                    StatusDocument::REJECTED,
                    StatusDocument::CANCELLED,
                ]))
                ->whereHas('approvals', $assignedMonitorApprovalScope);
        };
    }

    private function assignedApprovalScope(Request $request): callable
    {
        return function ($query) use ($request): void {
            $query
                ->where('assigned_by', $request->user()->id)
                ->where('stages', '!=', 'TTD Penyusun Resmi');
        };
    }

    private function assignableDocumentScope(Request $request): ?callable
    {
        $user = $request->user();

        if ($user->isDeveloper() || $user->isAdmin() || $user->hasExplicitPermission('documents.approval.assign')) {
            return function ($query): void {
                $query->whereHas('status', fn ($query) => $query->where('nama_status', StatusDocument::PROPOSED));
            };
        }

        return null;
    }

    private function approvalRow(Document $document, ?Approval $approval, bool $canAssign = false, ?User $user = null): array
    {
        $assignedAt = $this->asDateTime($approval?->assigned_at);
        $respondedAt = $this->asDateTime($approval?->responded_at);
        $submittedAt = $this->asDateTime($document->submitted_at ?? $document->created_at);
        $isPendingRevisionOwnerTask = $user !== null && $this->isPendingRevisionOwnerTask($document, $user) && $approval === null && ! $canAssign;
        $isAssignedMonitorTask = $user !== null && $approval !== null && $this->isAssignedMonitorApproval($approval, $user);
        $statusCode = $approval?->status?->kode_status
            ?? $approval?->status?->nama_status
            ?? $document->status?->nama_status
            ?? '-';

        return [
            'id' => $document->id,
            'detail_url' => route('documents.approval.show', $document),
            'action_url' => route('documents.approval.show', $document),
            'action_label' => 'Detail',
            'number' => $this->documentDisplayNumber($document),
            'number_badge_label' => $document->request_type === 'obsolete' ? 'Pengajuan Obsolete' : null,
            'number_badge_tone' => $document->request_type === 'obsolete' ? 'red' : null,
            'name' => $document->nama_dokumen ?? '-',
            'type' => $this->documentTypeLabel($document),
            'stage' => match (true) {
                $isPendingRevisionOwnerTask => 'Pengajuan Revisi',
                default => $approval?->stages ?: ($canAssign ? 'Belum assign approver' : 'Approval'),
            },
            'waiting_for' => $approval?->approver?->name ?? ($canAssign || $isPendingRevisionOwnerTask ? 'Admin Kontrol Dokumen' : '-'),
            'owner' => $document->creator?->name ?? '-',
            'department' => $document->departments
                ->map(fn ($department) => $department->kode_department ?: $department->nama_department)
                ->filter()
                ->implode(', ') ?: '-',
            'date' => $assignedAt?->translatedFormat('d M Y H:i:s') ?? $submittedAt?->translatedFormat('d M Y H:i:s') ?? '-',
            'date_sort' => $assignedAt?->timestamp ?? $submittedAt?->timestamp ?? 0,
            'submitted_at' => $submittedAt?->translatedFormat('d M Y') ?? '-',
            'submitted_at_sort' => $submittedAt?->timestamp ?? 0,
            'updated_at' => $respondedAt?->translatedFormat('d M Y H:i') ?? '-',
            'updated_at_sort' => $respondedAt?->timestamp ?? 0,
            'status' => $approval?->status?->nama_status ?? $document->status?->nama_status ?? $statusCode,
            'tone' => $this->approvalTone($statusCode),
            'action' => match (true) {
                $isAssignedMonitorTask => $this->waitingActionLabel($approval),
                $approval !== null => $this->waitingActionLabel($approval),
                $isPendingRevisionOwnerTask => 'Lihat',
                default => 'Perlu Verifikasi Admin KD',
            },
        ];
    }

    private function isPendingRevisionOwnerTask(Document $document, User $user): bool
    {
        return $document->revised_from !== null
            && in_array($document->status?->nama_status, [
                StatusDocument::DRAFT,
                StatusDocument::PROPOSED,
            ], true)
            && in_array($user->id, [$document->user_id, $document->official_preparer_id], true);
    }

    private function taskApproval(Document $document, User $user): ?Approval
    {
        $monitorApproval = $document->approvals
            ->filter(fn (Approval $approval): bool => $this->isAssignedMonitorApproval($approval, $user))
            ->sortBy(fn (Approval $approval): int => $approval->status?->kode_status === ApprovalStatus::PENDING ? 0 : 1)
            ->first();

        if ($monitorApproval !== null) {
            return $monitorApproval;
        }

        return $document->approvals->first();
    }

    private function isAssignedMonitorApproval(Approval $approval, User $user): bool
    {
        return $approval->assigned_by === $user->id
            && $approval->user_id !== $user->id
            && $approval->stages !== 'TTD Penyusun Resmi'
            && $approval->responded_at === null
            && in_array($approval->status?->kode_status, [
                ApprovalStatus::PENDING,
                ApprovalStatus::WAITING,
            ], true);
    }

    private function waitingActionLabel(Approval $approval): string
    {
        $stage = trim($approval->stages ?: 'approver');

        if (preg_match('/\boleh\s+(.+)$/i', $stage, $matches) === 1) {
            $stage = trim($matches[1]);
        }

        if (preg_match('/^approval\s+(.+)$/i', $stage, $matches) === 1) {
            $stage = trim($matches[1]);
        }

        return 'Menunggu '.$stage;
    }

    private function processedHistoryRow(Document $document, ?Approval $approval, User $user): array
    {
        if ($approval !== null) {
            if ($approval->assigned_by === $user->id && $approval->user_id !== $user->id) {
                return $this->assignedHistoryRow($document, $approval);
            }

            return $this->approvalRow($document, $approval, $user->isDeveloper());
        }

        $row = $this->approvalRow($document, null);
        $submittedAt = $this->asDateTime($document->submitted_at ?? $document->created_at);

        $row['stage'] = match (true) {
            $document->user_id === $user->id && $document->revised_from !== null => 'Pengajuan Revisi',
            $document->user_id === $user->id => 'Pengajuan Dokumen',
            $document->official_preparer_id === $user->id && $document->revised_from !== null => 'Pengajuan Revisi',
            $document->official_preparer_id === $user->id => 'Pengajuan Dokumen',
            default => 'Pengajuan Dokumen',
        };
        $row['waiting_for'] = '-';
        $row['updated_at'] = $submittedAt?->translatedFormat('d M Y H:i') ?? '-';
        $row['updated_at_sort'] = $submittedAt?->timestamp ?? 0;
        $row['action'] = 'Lihat';

        if ($this->isContinuableDraft($document, $user)) {
            $row['action_url'] = route('documents.create.drafts.edit', $document);
            $row['action_label'] = 'Lanjutkan';
        }

        return $row;
    }

    private function assignedHistoryRow(Document $document, Approval $approval): array
    {
        $row = $this->approvalRow($document, null);
        $assignedAt = $this->asDateTime($approval->assigned_at);

        $row['stage'] = 'Assign Approver';
        $row['waiting_for'] = $approval->approver?->name ?? '-';
        $row['updated_at'] = $assignedAt?->translatedFormat('d M Y H:i') ?? '-';
        $row['updated_at_sort'] = $assignedAt?->timestamp ?? 0;
        $row['action'] = 'Lihat';

        return $row;
    }

    private function isContinuableDraft(Document $document, User $user): bool
    {
        return $document->user_id === $user->id
            && $document->status?->nama_status === StatusDocument::DRAFT;
    }

    private function asDateTime(mixed $value): ?Carbon
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($value instanceof Carbon) {
            return $value;
        }

        if ($value instanceof \DateTimeInterface) {
            return Carbon::instance($value);
        }

        return Carbon::parse($value);
    }

    private function documentTypeLabel(Document $document): string
    {
        if ($document->request_type === 'obsolete') {
            return $this->documentTypeName($this->rootDocument($document) ?: $document->revisedFrom ?: $document);
        }

        return $this->documentTypeName($document);
    }

    private function documentTypeName(Document $document): string
    {
        return match ($document->documentType?->nama_types) {
            'IK' => 'Instruksi Kerja',
            default => $document->documentType?->nama_types ?? '-',
        };
    }

    private function documentDisplayNumber(Document $document): string
    {
        if ($document->request_type === 'obsolete') {
            return $this->rootDocumentNumber($document)
                ?? $document->nomor_dokumen
                ?? '-';
        }

        return $this->revisionRequestNumber($document)
            ?? $document->nomor_dokumen
            ?? '-';
    }

    private function revisionRequestNumber(Document $document): ?string
    {
        if ($document->request_type !== 'revision' || $document->revised_from === null) {
            return null;
        }

        if (filled($document->nomor_lembar_revisi)) {
            return $document->nomor_lembar_revisi;
        }

        $source = $document->revisedFrom;

        if ($source === null) {
            return null;
        }

        $prefix = match ($source->documentLevel?->kode) {
            'level-1' => 'FMSM',
            'level-2' => 'FMPS',
            'level-3' => 'FMIK',
            default => 'FM',
        };
        $segments = collect(explode('-', (string) $source->nomor_dokumen))
            ->filter()
            ->values();

        if ($segments->isNotEmpty()) {
            $segments->shift();
        }

        return collect([$prefix])
            ->merge($segments)
            ->push(str_pad((string) $document->nomor_revisi, 2, '0', STR_PAD_LEFT))
            ->filter()
            ->implode('-');
    }

    private function rootDocumentNumber(Document $document): ?string
    {
        return $this->rootDocument($document)?->nomor_dokumen;
    }

    private function rootDocument(Document $document): ?Document
    {
        if ($document->revised_from === null) {
            return null;
        }

        if (array_key_exists($document->id, $this->rootDocumentCache)) {
            return $this->rootDocumentCache[$document->id];
        }

        return $this->rootDocumentCache[$document->id] = Document::query()
            ->select(['id', 'm_document_types_id', 'nomor_dokumen', 'revised_from'])
            ->with('documentType')
            ->find($document->revisionRootId());
    }

    private function approvalTone(string $statusCode): string
    {
        return match ($statusCode) {
            ApprovalStatus::APPROVED => 'emerald',
            ApprovalStatus::REJECTED, ApprovalStatus::TERMINATED => 'rose',
            default => 'sky',
        };
    }
}
