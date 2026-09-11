<?php

namespace App\Livewire\Administration\ApprovalFlow;

use App\Actions\Administration\ApprovalFlow\CreateApprovalFlowStage;
use App\Actions\Administration\ApprovalFlow\DeleteApprovalFlowStage;
use App\Actions\Administration\ApprovalFlow\EnsureApprovalFlow;
use App\Actions\Administration\ApprovalFlow\UpdateApprovalFlowStage;
use App\Models\ApprovalFlow;
use App\Models\ApprovalFlowStage;
use App\Models\DocumentLevel;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

class Index extends Component
{
    public ?int $selectedDocumentLevelId = null;

    public ?int $approvalFlowId = null;

    public ?int $editingStageId = null;

    public ?int $deletingStageId = null;

    public string $nama_tahap = '';

    public bool $showStageForm = false;

    public bool $showDeleteModal = false;

    public function mount(): void
    {
        $this->selectedDocumentLevelId = $this->documentLevelsQuery()
            ->value('id');

        if ($this->selectedDocumentLevelId !== null) {
            $this->loadApprovalFlow();
        }
    }

    public function selectDocumentLevel(int $documentLevelId): void
    {
        abort_unless($this->documentLevelsQuery()->whereKey($documentLevelId)->exists(), 404);

        $this->selectedDocumentLevelId = $documentLevelId;
        $this->resetStageForm();
        $this->cancelDelete();
        $this->loadApprovalFlow();
    }

    public function createStage(): void
    {
        $this->authorizePermission('approval-flows.create');
        $this->abortIfSelectedLevelInheritsApprovalFlow();

        $this->resetStageForm();
        $this->showStageForm = true;
    }

    public function editStage(int $stageId): void
    {
        $this->authorizePermission('approval-flows.update');
        $this->abortIfSelectedLevelInheritsApprovalFlow();

        $stage = ApprovalFlowStage::query()
            ->whereHas('approvalFlow', function ($query): void {
                $query->where('id', $this->approvalFlowId);
            })
            ->findOrFail($stageId);

        $this->editingStageId = $stage->id;
        $this->nama_tahap = $stage->nama_tahap;
        $this->showStageForm = true;
    }

    /**
     * @throws ValidationException
     */
    public function saveStage(
        CreateApprovalFlowStage $createApprovalFlowStage,
        UpdateApprovalFlowStage $updateApprovalFlowStage,
    ): void {
        $this->authorizePermission(
            $this->editingStageId !== null
                ? 'approval-flows.update'
                : 'approval-flows.create',
        );
        $this->abortIfSelectedLevelInheritsApprovalFlow();

        $approvalFlow = $this->approvalFlow();

        $data = [
            'nama_tahap' => $this->nama_tahap,
        ];

        if ($this->editingStageId !== null) {
            $stage = $approvalFlow->stages()->findOrFail($this->editingStageId);

            $updateApprovalFlowStage->handle($stage, $data);
        } else {
            $createApprovalFlowStage->handle($approvalFlow, $data);
        }

        $this->resetStageForm();
    }

    public function confirmDeleteStage(int $stageId): void
    {
        $this->authorizePermission('approval-flows.delete');
        $this->abortIfSelectedLevelInheritsApprovalFlow();

        $this->deletingStageId = $stageId;
        $this->showDeleteModal = true;
    }

    public function cancelDelete(): void
    {
        $this->deletingStageId = null;
        $this->showDeleteModal = false;
    }

    public function deleteStage(DeleteApprovalFlowStage $deleteApprovalFlowStage): void
    {
        $this->authorizePermission('approval-flows.delete');
        $this->abortIfSelectedLevelInheritsApprovalFlow();

        if ($this->deletingStageId === null) {
            return;
        }

        $stage = $this->approvalFlow()
            ->stages()
            ->findOrFail($this->deletingStageId);

        $deleteApprovalFlowStage->handle($stage);

        $this->cancelDelete();
    }

    public function cancelStageForm(): void
    {
        $this->resetStageForm();
    }

    public function getDocumentLevelsProperty(): Collection
    {
        return $this->documentLevelsQuery()
            ->get();
    }

    public function getSelectedDocumentLevelProperty(): ?DocumentLevel
    {
        if ($this->selectedDocumentLevelId === null) {
            return null;
        }

        return $this->documentLevels->firstWhere('id', $this->selectedDocumentLevelId);
    }

    public function getApprovalStagesProperty(): Collection
    {
        if ($this->approvalFlowId === null || $this->selectedLevelInheritsApprovalFlow()) {
            return new Collection;
        }

        return ApprovalFlowStage::query()
            ->where('m_approval_flow_id', $this->approvalFlowId)
            ->orderBy('stage_order')
            ->get();
    }

    public function render(): View
    {
        return view('livewire.administration.approval-flows.index', [
            'documentLevels' => $this->documentLevels,
            'selectedDocumentLevel' => $this->selectedDocumentLevel,
            'approvalStages' => $this->approvalStages,
            'canCreate' => ! $this->selectedLevelInheritsApprovalFlow() && $this->canManage('approval-flows.create'),
            'canUpdate' => ! $this->selectedLevelInheritsApprovalFlow() && $this->canManage('approval-flows.update'),
            'canDelete' => ! $this->selectedLevelInheritsApprovalFlow() && $this->canManage('approval-flows.delete'),
            'selectedLevelInheritsApprovalFlow' => $this->selectedLevelInheritsApprovalFlow(),
        ])->layout('components.layouts.app', ['title' => 'Approval Flow']);
    }

    protected function loadApprovalFlow(): void
    {
        if ($this->selectedLevelInheritsApprovalFlow()) {
            $this->approvalFlowId = null;

            return;
        }

        $this->approvalFlowId = $this->approvalFlow()->id;
    }

    protected function approvalFlow(): ApprovalFlow
    {
        $this->abortIfSelectedLevelInheritsApprovalFlow();

        return app(EnsureApprovalFlow::class)->handle([
            'm_document_level_id' => $this->selectedDocumentLevelId,
        ]);
    }

    protected function resetStageForm(): void
    {
        $this->reset([
            'editingStageId',
            'nama_tahap',
            'showStageForm',
        ]);
    }

    private function canManage(string $permissionCode): bool
    {
        $user = auth()->user();

        if ($user === null) {
            return false;
        }

        return $user->isAdmin() || $user->hasExplicitPermission($permissionCode);
    }

    private function authorizePermission(string $permissionCode): void
    {
        abort_unless($this->canManage($permissionCode), 403);
    }

    private function selectedLevelInheritsApprovalFlow(): bool
    {
        return $this->selectedDocumentLevel?->kode === 'level-4';
    }

    private function documentLevelsQuery()
    {
        return DocumentLevel::query()
            ->active()
            ->where('kode', '!=', 'level-4')
            ->orderBy('sort_order')
            ->orderBy('nama_level');
    }

    private function abortIfSelectedLevelInheritsApprovalFlow(): void
    {
        abort_if($this->selectedLevelInheritsApprovalFlow(), 403);
    }
}
