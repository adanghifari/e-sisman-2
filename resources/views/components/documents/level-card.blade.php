@props([
    'title',
    'description',
    'level' => null,
    'href' => null,
    'actionLabel' => 'Ajukan Dokumen',
    'templateHref' => null,
    'templateLabel' => 'Download Template',
])

<section {{ $attributes->class(['group overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm transition hover:border-sky-200 hover:shadow-md']) }}>
    <div class="grid gap-5 border-l-4 border-sky-500 px-5 py-6 md:grid-cols-[88px_minmax(0,1fr)_auto] md:items-center md:px-7 md:py-7">
        <div class="flex items-start pt-1 md:justify-center md:pt-0">
            <div class="flex size-16 items-center justify-center rounded-lg bg-sky-50 text-center text-sm font-bold uppercase leading-tight text-sky-700 ring-1 ring-sky-100">
                {{ $level }}
            </div>
        </div>

        <div class="min-w-0">
            <h2 class="text-lg font-bold text-slate-950">
                {{ $title }}
            </h2>
            <p class="mt-2 max-w-3xl text-base leading-7 text-slate-600">
                {{ $description }}
            </p>
        </div>

        <div class="flex items-center justify-center md:justify-end">
            <div class="flex w-full flex-col items-center justify-center gap-2 md:w-auto">
                <x-ui.action-button :href="$href" class="w-full min-w-40 md:w-auto">
                    {{ $actionLabel }}
                </x-ui.action-button>
                @if ($templateHref)
                    <a
                        href="{{ $templateHref }}"
                        class="inline-flex items-center justify-center gap-1.5 text-center text-xs font-semibold text-sky-600 transition hover:text-sky-700 hover:underline"
                    >
                        <x-flux.icon name="arrow-down-tray" class="size-3.5" />
                        {{ $templateLabel }}
                    </a>
                @endif
            </div>
        </div>
    </div>
</section>
