@props([
    'label' => null,
    'name',
    'value' => null,
    'options' => [],
])

<label class="block">
    @if ($label)
        <span class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-slate-500">{{ $label }}</span>
    @endif

    <select
        name="{{ $name }}"
        {{ $attributes->class(['h-10 w-full rounded-lg border border-slate-200 bg-white px-3 text-sm font-semibold text-slate-700 outline-none transition focus:border-sky-400 focus:ring-2 focus:ring-sky-100']) }}
    >
        @foreach ($options as $optionValue => $optionLabel)
            <option value="{{ $optionValue }}" @selected((string) $value === (string) $optionValue)>
                {{ $optionLabel }}
            </option>
        @endforeach
    </select>
</label>
