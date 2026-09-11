<div class="rounded-lg border border-slate-200 bg-white p-4">
    <div class="flex items-start justify-between gap-4">
        <div>
            <h3 class="text-sm font-semibold text-slate-900">{{ __('Recovery codes') }}</h3>
            <p class="mt-1 text-sm text-slate-500">{{ __('Store these recovery codes in a secure password manager.') }}</p>
        </div>

        <x-ui.action-button type="button" variant="secondary" wire:click="regenerateRecoveryCodes">
            {{ __('Regenerate') }}
        </x-ui.action-button>
    </div>

    @error('recoveryCodes')
        <div class="mt-4 rounded-lg border border-red-100 bg-red-50 px-4 py-3 text-sm font-semibold text-red-700">
            {{ $message }}
        </div>
    @enderror

    @if ($recoveryCodes !== [])
        <div class="mt-4 grid gap-2 rounded-lg bg-slate-50 p-4 font-mono text-sm font-semibold text-slate-700 sm:grid-cols-2">
            @foreach ($recoveryCodes as $code)
                <span>{{ $code }}</span>
            @endforeach
        </div>
    @endif
</div>
