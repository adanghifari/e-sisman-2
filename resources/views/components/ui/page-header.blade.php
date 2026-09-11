@props([
    'eyebrow' => null,
    'title',
    'description' => null,
])

<div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
    <div>
        @if ($eyebrow)
            <p class="text-sm font-semibold uppercase text-sky-700">{{ $eyebrow }}</p>
        @endif

        <h1 class="mt-1 text-2xl font-bold text-slate-950 md:text-3xl">{{ $title }}</h1>

        @if ($description)
            <p class="mt-2 text-sm text-slate-500">{{ $description }}</p>
        @endif
    </div>

    @if ($slot->isNotEmpty())
        <div class="shrink-0">
            {{ $slot }}
        </div>
    @endif
</div>
