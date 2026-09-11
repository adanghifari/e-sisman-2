@props([
    'title',
    'icon' => null,
])

<section {{ $attributes->class(['overflow-visible rounded-lg border border-slate-200 bg-white shadow-sm']) }}>
    <div class="border-b border-slate-200 px-6 py-5">
        <div class="flex items-center justify-between gap-4">
            <div class="flex items-center gap-3">
                @if ($icon)
                    <span class="grid size-10 shrink-0 place-items-center rounded-lg bg-sky-50 text-sky-700 ring-1 ring-sky-100">
                        <x-flux.icon :name="$icon" class="size-5" />
                    </span>
                @endif

                <h2 class="text-lg font-bold text-slate-900">{{ $title }}</h2>
            </div>

            @isset($actions)
                <div class="shrink-0">
                    {{ $actions }}
                </div>
            @endisset
        </div>
    </div>

    {{ $slot }}
</section>
