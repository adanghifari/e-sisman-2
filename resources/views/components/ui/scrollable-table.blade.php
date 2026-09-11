@props([
    'maxHeight' => '430px',
    'minWidth' => '760px',
    'horizontal' => true,
])

@php
    $scrollClass = $horizontal ? 'overflow-x-auto' : 'overflow-x-hidden';
    $tableStyle = filled($minWidth) ? "min-width: {$minWidth};" : null;
@endphp

<x-ui.scroll-area :max-height="$maxHeight" :class="$scrollClass">
    <table {{ $attributes->class(['ui-data-table w-full text-left text-sm'])->merge(['style' => $tableStyle]) }}>
        {{ $slot }}
    </table>
</x-ui.scroll-area>
