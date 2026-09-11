@props([
    'label' => null,
    'name',
    'value' => null,
    'placeholder' => null,
    'rows' => 3,
])

<label class="block">
    @if ($label)
        <span class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-slate-500">{{ $label }}</span>
    @endif

    <textarea
        name="{{ $name }}"
        rows="{{ $rows }}"
        placeholder="{{ $placeholder }}"
        {{ $attributes->class(['w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700 outline-none transition placeholder:text-slate-400 focus:border-sky-400 focus:ring-2 focus:ring-sky-100']) }}
    >{{ $value }}</textarea>
</label>
