@props([
    'title',
    'description' => null,
])

<section {{ $attributes->class(['rounded-lg border border-slate-200 bg-white p-6 shadow-sm']) }}>
    <h2 class="font-semibold text-slate-950">{{ $title }}</h2>

    @if ($description)
        <p class="mt-2 text-sm text-slate-500">{{ $description }}</p>
    @endif

    @if ($slot->isNotEmpty())
        <div class="mt-4">
            {{ $slot }}
        </div>
    @endif
</section>
