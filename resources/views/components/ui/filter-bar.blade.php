@props([
    'action' => null,
    'method' => 'GET',
])

<form
    method="{{ $method }}"
    action="{{ $action }}"
    {{ $attributes->class(['rounded-lg border border-slate-200 bg-white p-4 shadow-sm']) }}
>
    @isset($tabs)
        <div class="mb-4 flex flex-wrap gap-2 border-b border-slate-100 pb-4">
            {{ $tabs }}
        </div>
    @endisset

    <div class="grid gap-3 md:grid-cols-[minmax(220px,1fr)_repeat(4,minmax(150px,180px))_auto]">
        {{ $slot }}
    </div>
</form>
