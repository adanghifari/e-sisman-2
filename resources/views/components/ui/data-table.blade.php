@props([
    'maxHeight' => null,
    'minWidth' => '760px',
])

<x-ui.scrollable-table :max-height="$maxHeight" :min-width="$minWidth" {{ $attributes }}>
    {{ $slot }}
</x-ui.scrollable-table>
