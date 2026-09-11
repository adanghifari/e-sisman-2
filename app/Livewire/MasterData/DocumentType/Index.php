<?php

namespace App\Livewire\MasterData\DocumentType;

use App\Actions\MasterData\DocumentType\CreateDocumentType;
use App\Actions\MasterData\DocumentType\DeleteDocumentType;
use App\Actions\MasterData\DocumentType\ToggleDocumentTypeStatus;
use App\Actions\MasterData\DocumentType\UpdateDocumentType;
use App\Livewire\MasterData\Concerns\HandlesMasterDataCrudState;
use App\Models\DocumentType;
use Illuminate\Contracts\View\View;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Validation\ValidationException;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use HandlesMasterDataCrudState;
    use WithPagination;

    public string $nama_types = '';

    public function edit(int $id): void
    {
        $this->authorizePermission('update');

        $documentType = DocumentType::findOrFail($id);

        $this->showForm = true;
        $this->editingId = $documentType->id;
        $this->nama_types = $documentType->nama_types;
        $this->is_active = $documentType->is_active;
    }

    /**
     * @throws ValidationException
     */
    public function save(
        CreateDocumentType $createDocumentType,
        UpdateDocumentType $updateDocumentType,
    ): void {
        $this->authorizePermission($this->editingId ? 'update' : 'create');

        $data = [
            'nama_types' => $this->nama_types,
            'is_active' => $this->is_active,
        ];

        if ($this->editingId) {
            $documentType = DocumentType::findOrFail($this->editingId);

            $updateDocumentType->handle($documentType, $data);
        } else {
            $createDocumentType->handle($data);
        }

        $this->resetForm();
        $this->resetPage();
    }

    public function toggleStatus(
        int $id,
        ToggleDocumentTypeStatus $toggleDocumentTypeStatus,
    ): void {
        $this->authorizePermission('update');

        $documentType = DocumentType::findOrFail($id);

        $toggleDocumentTypeStatus->handle($documentType);
    }

    /**
     * @throws ValidationException
     */
    public function delete(DeleteDocumentType $deleteDocumentType): void
    {
        $this->authorizePermission('delete');

        if ($this->deletingId === null) {
            return;
        }

        $documentType = DocumentType::findOrFail($this->deletingId);

        $deleteDocumentType->handle($documentType);

        $this->cancelDelete();
        $this->resetPage();
    }

    public function getDocumentTypesProperty(): LengthAwarePaginator
    {
        return DocumentType::query()
            ->when($this->search !== '', function ($query): void {
                $query->where('nama_types', 'like', '%'.$this->search.'%');
            })
            ->when($this->status !== '', function ($query): void {
                $query->where('is_active', $this->status === 'active');
            })
            ->orderBy('nama_types')
            ->paginate($this->perPage);
    }

    public function render(): View
    {
        return view('livewire.master-data.document-types.index', [
            'documentTypes' => $this->documentTypes,
            'statusOptions' => [
                '' => 'Semua Status',
                'active' => 'Active',
                'inactive' => 'Inactive',
            ],
        ])->layout('components.layouts.app', ['title' => 'Jenis Dokumen']);
    }

    protected function masterDataModelClass(): string
    {
        return DocumentType::class;
    }

    protected function permissionPrefix(): string
    {
        return 'master-data.document-types';
    }

    protected function resetForm(): void
    {
        $this->resetMasterDataForm([
            'nama_types',
        ]);
    }
}
