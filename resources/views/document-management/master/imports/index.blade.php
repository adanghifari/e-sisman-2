<x-layouts.app :title="__('Import Dokumen Master')">
    <div class="space-y-20">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <nav class="mb-2 flex items-center gap-2 text-sm font-medium text-slate-500" aria-label="Breadcrumb">
                    <a href="{{ route('dashboard') }}" class="transition hover:text-sky-700">Home</a>
                    <x-flux.icon name="chevron-right" class="size-4 text-slate-400" />
                    <a href="{{ route('documents.master') }}" class="transition hover:text-sky-700">Dokumen Master</a>
                    <x-flux.icon name="chevron-right" class="size-4 text-slate-400" />
                    <span class="text-slate-700">Import Dokumen Master</span>
                </nav>
                <x-ui.page-header title="Import Dokumen Master" description="Pilih level dokumen yang akan diimport ke dalam sistem." />
            </div>
        </div>

        <div class="mx-auto grid w-full max-w-4xl gap-5">
            @foreach ($documentLevels as $levelKey => $level)
                <x-documents.level-card
                    :level="$level['badge']"
                    :title="$level['name']"
                    :description="$level['create_description']"
                    :href="route('documents.master.imports.create.level', $levelKey)"
                    action-label="Import Level Ini"
                />
            @endforeach
        </div>
    </div>
</x-layouts.app>
