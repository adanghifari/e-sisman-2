<?php

namespace App\Livewire\MasterData\BusinessProcess;

use App\Actions\MasterData\BusinessProcess\CreateBusinessProcess;
use App\Actions\MasterData\BusinessProcess\DeleteBusinessProcess;
use App\Actions\MasterData\BusinessProcess\ToggleBusinessProcessStatus;
use App\Actions\MasterData\BusinessProcess\UpdateBusinessProcess;
use App\Livewire\MasterData\Concerns\HandlesMasterDataCrudState;
use App\Models\BusinessProcess;
use Illuminate\Contracts\View\View;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Validation\ValidationException;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use HandlesMasterDataCrudState;
    use WithPagination;

    public string $kode = '';

    public string $nama_proses_bisnis = '';

    public function edit(int $id): void
    {
        $this->authorizePermission('update');

        $businessProcess = BusinessProcess::findOrFail($id);

        $this->showForm = true;
        $this->editingId = $businessProcess->id;
        $this->kode = $businessProcess->kode;
        $this->nama_proses_bisnis = $businessProcess->nama_proses_bisnis;
        $this->is_active = $businessProcess->is_active;
    }

    /**
     * @throws ValidationException
     */
    public function save(
        CreateBusinessProcess $createBusinessProcess,
        UpdateBusinessProcess $updateBusinessProcess,
    ): void {
        $this->authorizePermission($this->editingId ? 'update' : 'create');

        $data = [
            'kode' => $this->kode,
            'nama_proses_bisnis' => $this->nama_proses_bisnis,
            'is_active' => $this->is_active,
        ];

        if ($this->editingId) {
            $businessProcess = BusinessProcess::findOrFail($this->editingId);

            $updateBusinessProcess->handle($businessProcess, $data);
        } else {
            $createBusinessProcess->handle($data);
        }

        $this->resetForm();
        $this->resetPage();
    }

    public function toggleStatus(
        int $id,
        ToggleBusinessProcessStatus $toggleBusinessProcessStatus,
    ): void {
        $this->authorizePermission('update');

        $businessProcess = BusinessProcess::findOrFail($id);

        $toggleBusinessProcessStatus->handle($businessProcess);
    }

    /**
     * @throws ValidationException
     */
    public function delete(DeleteBusinessProcess $deleteBusinessProcess): void
    {
        $this->authorizePermission('delete');

        if ($this->deletingId === null) {
            return;
        }

        $businessProcess = BusinessProcess::findOrFail($this->deletingId);

        $deleteBusinessProcess->handle($businessProcess);

        $this->cancelDelete();
        $this->resetPage();
    }

    public function getBusinessProcessesProperty(): LengthAwarePaginator
    {
        return BusinessProcess::query()
            ->when($this->search !== '', function ($query): void {
                $query->where(function ($query): void {
                    $query
                        ->where('kode', 'like', '%'.$this->search.'%')
                        ->orWhere('nama_proses_bisnis', 'like', '%'.$this->search.'%');
                });
            })
            ->when($this->status !== '', function ($query): void {
                $query->where('is_active', $this->status === 'active');
            })
            ->orderBy('nama_proses_bisnis')
            ->paginate($this->perPage);
    }

    public function render(): View
    {
        return view('livewire.master-data.business-processes.index', [
            'businessProcesses' => $this->businessProcesses,
            'statusOptions' => [
                '' => 'Semua Status',
                'active' => 'Active',
                'inactive' => 'Inactive',
            ],
        ])->layout('components.layouts.app', ['title' => 'Proses Bisnis']);
    }

    protected function masterDataModelClass(): string
    {
        return BusinessProcess::class;
    }

    protected function permissionPrefix(): string
    {
        return 'master-data.business-processes';
    }

    protected function resetForm(): void
    {
        $this->resetMasterDataForm([
            'kode',
            'nama_proses_bisnis',
        ]);
    }
}
