@props([
    'title',
    'description' => null,
    'message',
    'confirmAction',
    'cancelAction',
    'confirmLabel' => 'Hapus',
    'cancelLabel' => 'Batal',
    'errorKey' => null,
])

<x-ui.modal :title="$title" :description="$description" :close-action="$cancelAction" max-width="md">
    <div class="space-y-4 px-6 py-5">
        <p class="text-sm text-slate-700">{{ $message }}</p>

        @if ($errorKey)
            @error($errorKey)
                <div class="rounded-lg border border-red-100 bg-red-50 px-4 py-3 text-sm font-medium text-red-700">
                    {{ $message }}
                </div>
            @enderror
        @endif
    </div>

    <div class="flex justify-end gap-2 border-t border-slate-100 px-6 py-4">
        <x-ui.action-button type="button" variant="secondary" wire:click="{{ $cancelAction }}">
            {{ $cancelLabel }}
        </x-ui.action-button>

        <button type="button" wire:click="{{ $confirmAction }}" class="inline-flex h-10 items-center justify-center rounded-lg bg-red-600 px-4 text-sm font-semibold text-white shadow-sm transition hover:bg-red-700">
            {{ $confirmLabel }}
        </button>
    </div>
</x-ui.modal>
