<div class="space-y-6">
    <x-ui.page-header
        title="Approval Flow"
        description="Atur nama tahap approval untuk setiap level dokumen."
    />

    <div class="grid gap-5 xl:grid-cols-[360px_minmax(0,1fr)]">
        <x-ui.panel title="Level Dokumen" description="Pilih level dokumen yang ingin disetting." class="h-fit xl:sticky xl:top-8">
            <div class="mt-4 space-y-3">
                @forelse ($documentLevels as $documentLevel)
                    <button
                        type="button"
                        wire:click="selectDocumentLevel({{ $documentLevel->id }})"
                        @class([
                            'group w-full rounded-lg border px-5 py-4 text-left transition hover:border-sky-200 hover:bg-sky-50',
                            'border-sky-300 bg-sky-50 shadow-sm' => $selectedDocumentLevelId === $documentLevel->id,
                            'border-slate-200 bg-white' => $selectedDocumentLevelId !== $documentLevel->id,
                        ])
                    >
                        <span @class([
                            'block font-semibold',
                            'text-sky-800' => $selectedDocumentLevelId === $documentLevel->id,
                            'text-slate-900' => $selectedDocumentLevelId !== $documentLevel->id,
                        ])>
                            {{ $documentLevel->nama_level }}
                        </span>
                        <span @class([
                            'mt-1 block text-sm leading-6',
                            'text-sky-700' => $selectedDocumentLevelId === $documentLevel->id,
                            'text-slate-500' => $selectedDocumentLevelId !== $documentLevel->id,
                        ])>
                            {{ $documentLevel->nama_dokumen }}
                        </span>
                    </button>
                @empty
                    <x-ui.empty-state
                        title="Belum Ada Level Dokumen"
                        description="Tambahkan jenis dokumen aktif terlebih dahulu."
                    />
                @endforelse
            </div>
        </x-ui.panel>

        <div class="space-y-5">
            @if (! $selectedDocumentLevel)
                <x-ui.empty-state
                    title="Pilih Level Dokumen"
                    description="Flow approval akan muncul setelah level dokumen dipilih."
                />
            @else
                <x-ui.panel :padded="false">
                    <div class="flex flex-col gap-4 border-b border-slate-200 px-5 py-5 md:flex-row md:items-start md:justify-between">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-wide text-sky-700">Flow Aktif</p>
                            <h2 class="mt-1 text-xl font-bold text-slate-950">{{ $selectedDocumentLevel->nama_level }}</h2>
                        </div>

                        @if ($canCreate)
                            <x-ui.action-button type="button" wire:click="createStage">
                                <x-flux.icon name="plus" class="mr-2 size-4" />
                                Tambah Tahap
                            </x-ui.action-button>
                        @endif
                    </div>

                    <div class="space-y-4 p-5">
                        @if ($selectedLevelInheritsApprovalFlow)
                            <x-ui.empty-state
                                title="Mengikuti Flow Dokumen Induk"
                                description="Revisi Manual, Prosedur, dan Instruksi Kerja memakai tahap approval dari level dokumen asalnya."
                            />
                        @else
                            @forelse ($approvalStages as $stage)
                            <section class="rounded-lg border border-slate-200 bg-slate-50 px-4 py-4">
                                <div class="grid gap-3 lg:grid-cols-[56px_120px_minmax(0,1fr)_88px] lg:items-center">
                                    <div class="flex size-10 items-center justify-center self-center rounded-lg bg-sky-100 text-sm font-bold text-sky-700">
                                        {{ $stage->stage_order }}
                                    </div>

                                    <h3 class="self-center font-semibold leading-none text-slate-950">
                                        Tahap {{ $stage->stage_order }}
                                    </h3>

                                    <div>
                                        <p class="mb-1.5 text-xs font-semibold uppercase tracking-wide text-slate-500">Nama Tahap</p>
                                        <p class="text-sm font-semibold text-slate-800">{{ $stage->nama_tahap }}</p>
                                    </div>

                                    <div class="flex items-center justify-end gap-2">
                                        @if ($canUpdate)
                                            <x-ui.icon-button
                                                icon="pencil"
                                                label="Edit tahap approval"
                                                size="sm"
                                                wire:click="editStage({{ $stage->id }})"
                                            />
                                        @endif

                                        @if ($canDelete)
                                            <x-ui.icon-button
                                                icon="trash"
                                                label="Hapus tahap approval"
                                                size="sm"
                                                wire:click="confirmDeleteStage({{ $stage->id }})"
                                            />
                                        @endif
                                    </div>
                                </div>
                            </section>
                        @empty
                            <x-ui.empty-state
                                title="Belum Ada Tahap"
                                description="Tambahkan tahap approval untuk level dokumen ini."
                            >
                                @if ($canCreate)
                                    <x-ui.action-button type="button" wire:click="createStage">
                                        <x-flux.icon name="plus" class="mr-2 size-4" />
                                        Tambah Tahap
                                    </x-ui.action-button>
                                @endif
                            </x-ui.empty-state>
                            @endforelse
                        @endif
                    </div>
                </x-ui.panel>
            @endif
        </div>
    </div>

    @if ($showStageForm)
        <x-ui.modal
            :title="$editingStageId ? 'Edit Tahap Approval' : 'Tambah Tahap Approval'"
            description="Lengkapi nama tahap approval untuk level dokumen ini."
            close-action="cancelStageForm"
        >
            <form wire:submit.prevent="saveStage" class="space-y-5 px-6 py-5">
                <x-ui.form-input
                    label="Nama Tahap"
                    name="nama_tahap"
                    wire:model="nama_tahap"
                    placeholder="Contoh: Disusun Oleh"
                />

                <div class="flex justify-end gap-2 border-t border-slate-100 pt-5">
                    <x-ui.action-button type="button" variant="secondary" wire:click="cancelStageForm">
                        Batal
                    </x-ui.action-button>

                    <x-ui.action-button type="submit">
                        Simpan
                    </x-ui.action-button>
                </div>
            </form>
        </x-ui.modal>
    @endif

    @if ($showDeleteModal)
        <x-ui.confirm-modal
            title="Hapus Tahap Approval"
            description="Tahap yang dihapus akan mengubah urutan tahap setelahnya."
            message="Yakin ingin menghapus tahap approval ini?"
            confirm-action="deleteStage"
            cancel-action="cancelDelete"
        />
    @endif
</div>
