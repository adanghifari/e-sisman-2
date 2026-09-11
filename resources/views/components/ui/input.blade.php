@props([
    'label' => null,
    'name',
    'value' => null,
    'placeholder' => null,
    'type' => 'text',
])

<label class="block">
    @if ($label)
        <span class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-slate-500">{{ $label }}</span>
    @endif

    <input
        type="{{ $type }}"
        name="{{ $name }}"
        value="{{ $value }}"
        placeholder="{{ $placeholder }}"
        {{ $attributes->class(['h-10 w-full rounded-lg border border-slate-200 bg-white px-3 text-sm text-slate-700 outline-none transition placeholder:text-slate-400 focus:border-sky-400 focus:ring-2 focus:ring-sky-100']) }}
    >
</label>
