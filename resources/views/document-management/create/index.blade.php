<x-layouts.app :title="__('Tambah Dokumen')">
    @php
        $documentLevels = collect(config('document-levels'))
            ->except('level-4')
            ->all();
    @endphp

    <div class="space-y-20">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <x-ui.page-header title="Tambah Dokumen" />

            <a href="{{ route('documents.create.drafts') }}" class="inline-flex h-11 items-center justify-center gap-2 rounded-lg border border-slate-200 bg-white px-4 text-sm font-semibold text-slate-700 shadow-sm transition hover:border-sky-200 hover:bg-sky-50 hover:text-sky-700">
                <x-flux.icon name="document-text" class="size-5" />
                Draft Saya
                @if (($draftCount ?? 0) > 0)
                    <span class="rounded-full bg-sky-100 px-2 py-0.5 text-xs font-bold text-sky-700">{{ $draftCount }}</span>
                @endif
            </a>
        </div>

        <div class="mx-auto grid w-full max-w-4xl gap-5">
            @foreach ($documentLevels as $levelKey => $level)
                <x-documents.level-card
                    :level="$level['badge']"
                    :title="$level['name']"
                    :description="$level['create_description']"
                    :href="route('documents.create.level', $levelKey)"
                    :template-href="route('document-templates.index', ['level' => $levelKey])"
                />
            @endforeach
        </div>
    </div>
</x-layouts.app>
