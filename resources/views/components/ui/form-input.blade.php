@props([
    'label',
    'name',
    'id' => null,
    'type' => 'text',
    'placeholder' => null,
    'errorName' => null,
])

@php
    $id ??= $name;
@endphp

<div>
    <label for="{{ $id }}" class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-slate-500">
        {{ $label }}
    </label>

    <input
        id="{{ $id }}"
        type="{{ $type }}"
        name="{{ $name }}"
        placeholder="{{ $placeholder }}"
        {{ $attributes->class(['h-10 w-full rounded-lg border border-slate-200 bg-white px-3 text-sm font-semibold text-slate-700 outline-none transition placeholder:text-slate-400 focus:border-sky-400 focus:ring-2 focus:ring-sky-100']) }}
    >

    @error($errorName ?? $name)
        <p class="mt-1 text-xs font-medium text-red-600">{{ $message }}</p>
    @enderror
</div>
