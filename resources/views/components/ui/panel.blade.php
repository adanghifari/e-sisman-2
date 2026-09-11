@props([
    'title' => null,
    'description' => null,
    'padded' => true,
])

<section {{ $attributes->class([
    'rounded-lg border border-slate-200 bg-white shadow-sm',
    'p-5' => $padded,
    'overflow-hidden' => ! $padded,
]) }}>
    @if ($title || $description || isset($actions))
        <div @class([
            'flex items-start justify-between gap-3' => $padded,
            'flex items-center justify-between gap-4 border-b border-slate-200 px-5 py-4' => ! $padded,
        ])>
            <div>
                @if ($title)
                    <h2 class="font-semibold text-slate-950">{{ $title }}</h2>
                @endif

                @if ($description)
                    <p class="mt-1 text-sm text-slate-500">{{ $description }}</p>
                @endif
            </div>

            @isset($actions)
                {{ $actions }}
            @endisset
        </div>
    @endif

    {{ $slot }}
</section>
