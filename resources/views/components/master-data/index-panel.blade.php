@props([
    'title',
    'description',
    'searchLabel',
    'searchPlaceholder',
    'statusOptions',
    'status',
    'createAction' => 'create',
    'canCreate' => true,
])

<x-ui.panel :title="$title" :description="$description" :padded="false">
    @if ($canCreate)
        <x-slot name="actions">
            <x-ui.action-button type="button" wire:click="{{ $createAction }}" class="gap-2">
                <x-flux.icon name="plus" class="size-4" />
                Tambah Data
            </x-ui.action-button>
        </x-slot>
    @endif

    <div class="grid gap-4 border-b border-slate-200 p-5 md:grid-cols-[minmax(260px,1fr)_minmax(180px,260px)]">
        <x-ui.input
            :label="$searchLabel"
            name="search"
            wire:model.debounce.300ms="search"
            :placeholder="$searchPlaceholder"
        />

        <x-ui.select
            label="Status"
            name="status"
            wire:model="status"
            :value="$status"
            :options="$statusOptions"
        />
    </div>

    {{ $slot }}
</x-ui.panel>
