@props([
    'label',
    'name',
    'value' => null,
    'errorName' => null,
])

@php
    $inputId = $attributes->get('id') ?? $name;
    $errorName ??= $name;
@endphp

<div class="block">
    <label for="{{ $inputId }}" class="mb-2 block text-base font-medium text-slate-500">{{ $label }}</label>

    <div class="relative">
        <input
            id="{{ $inputId }}"
            type="date"
            name="{{ $name }}"
            value="{{ $value }}"
            {{ $attributes->except('id')->class(['h-14 w-full rounded-lg border border-slate-300 bg-white px-4 pr-12 text-base font-semibold text-slate-700 outline-none transition focus:border-sky-400 focus:ring-2 focus:ring-sky-100']) }}
            data-date-input
        >

        <button
            type="button"
            class="absolute right-4 top-1/2 grid size-6 -translate-y-1/2 place-items-center text-slate-500 transition hover:text-sky-600 focus:outline-none focus:ring-2 focus:ring-sky-200"
            data-date-picker-trigger
            aria-label="Pilih {{ $label }}"
        >
            <x-flux.icon name="calendar" class="size-6" />
        </button>
    </div>

    @error($errorName)
        <span class="mt-2 block text-sm font-semibold text-red-500">{{ $message }}</span>
    @enderror
</div>

@once
    <script>
        (() => {
            document.addEventListener('click', (event) => {
                const trigger = event.target.closest('[data-date-picker-trigger]');

                if (!trigger) {
                    return;
                }

                const input = trigger.parentElement?.querySelector('[data-date-input]');

                if (!input) {
                    return;
                }

                if (typeof input.showPicker === 'function') {
                    input.showPicker();
                    return;
                }

                input.focus();
            });
        })();
    </script>
@endonce
