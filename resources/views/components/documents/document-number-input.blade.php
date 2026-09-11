@props([
    'prefix',
    'segments' => [],
    'defaultValue' => null,
    'label' => 'Nomor Dokumen',
    'readonlySuffix' => false,
])

@php
    $columnCount = 1 + (count($segments) * 2) + 2;
    $gridTemplateColumns = collect(range(1, $columnCount))
        ->map(fn ($index) => $index % 2 === 0 ? 'auto' : 'minmax(0, 1fr)')
        ->implode(' ');
@endphp

<div>
    <span class="mb-2 block text-base font-medium text-slate-500">{{ $label }}</span>

    <div class="grid items-center gap-3" style="grid-template-columns: {{ $gridTemplateColumns }};">
        <input type="text" value="{{ $prefix }}" readonly class="h-14 w-full rounded-lg border border-slate-200 bg-slate-50 px-3 text-center text-base font-semibold text-slate-600">

        @foreach ($segments as $segment)
            <span class="text-lg font-semibold text-slate-500">-</span>
            <input
                type="text"
                value="{{ $segment['value'] ?? $segment }}"
                readonly
                @isset($segment['target'])
                    data-document-number-segment="{{ $segment['target'] }}"
                @endisset
                class="h-14 w-full rounded-lg border border-slate-200 bg-slate-50 px-3 text-center text-base font-semibold text-slate-600"
            >
        @endforeach

        <span class="text-lg font-semibold text-slate-500">-</span>
        <input
            type="text"
            name="nomor_dokumen_suffix"
            value="{{ $readonlySuffix ? $defaultValue : old('nomor_dokumen_suffix', $defaultValue) }}"
            @if (! $readonlySuffix && old('nomor_dokumen_suffix') !== null) data-user-edited="true" @endif
            @readonly($readonlySuffix)
            required
            @class([
                'h-14 w-full rounded-lg px-3 text-center text-base font-semibold outline-none transition',
                'border border-slate-200 bg-slate-50 text-slate-600' => $readonlySuffix,
                'border border-slate-300 bg-white text-slate-700 focus:border-sky-400 focus:ring-2 focus:ring-sky-100' => ! $readonlySuffix,
            ])
        >
    </div>

    @error('nomor_dokumen_suffix')
        <span class="mt-2 block text-sm font-semibold text-red-500">{{ $message }}</span>
    @enderror
    @error('nomor_dokumen')
        <span class="mt-2 block text-sm font-semibold text-red-500">{{ $message }}</span>
    @enderror
</div>
