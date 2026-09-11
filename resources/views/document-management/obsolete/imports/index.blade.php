<x-layouts.app :title="__('Import Dokumen Obsolete')">
    <div class="space-y-10">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <nav class="mb-2 flex items-center gap-2 text-sm font-medium text-slate-500" aria-label="Breadcrumb">
                    <a href="{{ route('dashboard') }}" class="transition hover:text-sky-700">Home</a>
                    <x-flux.icon name="chevron-right" class="size-4 text-slate-400" />
                    <a href="{{ route('documents.obsolete') }}" class="transition hover:text-sky-700">Dokumen Obsolete</a>
                    <x-flux.icon name="chevron-right" class="size-4 text-slate-400" />
                    <span class="text-slate-700">Import Dokumen Obsolete</span>
                </nav>
                <x-ui.page-header title="Import Dokumen Obsolete" description="Pilih ketentuan arsip obsolete yang akan diimport." />
            </div>
        </div>

        <div class="mx-auto grid w-full max-w-4xl gap-5">
            <details class="group relative" data-obsolete-level-picker>
                <summary class="list-none [&::-webkit-details-marker]:hidden">
                    <section class="cursor-pointer overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm transition hover:border-sky-200 hover:shadow-md">
                        <div class="grid gap-5 border-l-4 border-sky-500 px-5 py-6 md:grid-cols-[88px_minmax(0,1fr)_auto] md:items-center md:px-7 md:py-7">
                            <div class="flex items-start pt-1 md:justify-center md:pt-0">
                                <div class="flex size-16 items-center justify-center rounded-lg bg-sky-50 text-sky-500 ring-1 ring-sky-100">
                                    <x-flux.icon name="document-text" class="size-8" />
                                </div>
                            </div>

                            <div class="min-w-0">
                                <h2 class="text-lg font-bold text-slate-950">
                                    Ketentuan Saat Ini
                                </h2>
                                <p class="mt-2 max-w-3xl text-base leading-7 text-slate-600">
                                    Pilih level dokumen untuk melihat ketentuan yang berlaku saat ini.
                                </p>
                            </div>

                            <div class="flex items-center md:justify-end">
                                <span class="inline-flex h-10 w-full min-w-40 items-center justify-center gap-2 rounded-lg bg-sky-600 px-4 text-sm font-semibold text-white shadow-sm transition group-open:bg-sky-700 group-hover:bg-sky-700 md:w-auto">
                                    Pilih level
                                    <x-flux.icon name="chevron-down" class="size-4 transition group-open:rotate-180" />
                                </span>
                            </div>
                        </div>
                    </section>
                </summary>

                <div class="relative mt-5 w-full">
                    <div class="absolute -top-3 right-12 size-6 rotate-45 border-l border-t border-slate-200 bg-white md:right-16"></div>
                    <div class="relative overflow-hidden rounded-lg border border-slate-200 bg-white px-5 py-2 shadow-sm">
                        @foreach ($documentLevels as $levelKey => $level)
                            <a
                                href="{{ route('documents.obsolete.imports.create.level', $levelKey) }}"
                                class="grid gap-5 border-slate-200 py-5 transition hover:bg-sky-50/60 md:grid-cols-[88px_minmax(0,1fr)_auto] md:items-center md:px-1 {{ ! $loop->last ? 'border-b' : '' }}"
                            >
                                <div class="flex md:justify-center">
                                    <div class="flex size-16 items-center justify-center rounded-lg bg-sky-50 text-center text-sm font-bold uppercase leading-tight text-sky-700 ring-1 ring-sky-100">
                                        {{ $level['badge'] }}
                                    </div>
                                </div>

                                <div class="min-w-0">
                                    <h3 class="text-lg font-bold text-slate-950">
                                        {{ $level['name'] }}
                                    </h3>
                                    <p class="mt-2 max-w-4xl text-base leading-7 text-slate-600">
                                        {{ $level['create_description'] }}
                                    </p>
                                </div>

                                <div class="flex items-center md:justify-end">
                                    <span class="inline-flex h-10 w-full min-w-40 items-center justify-center rounded-lg bg-sky-600 px-4 text-sm font-semibold text-white shadow-sm transition group-hover:bg-sky-700 md:w-auto">
                                        Import Dokumen
                                    </span>
                                </div>
                            </a>
                        @endforeach
                    </div>
                </div>
            </details>

            <section class="group overflow-hidden rounded-lg border border-amber-200 bg-amber-50 shadow-sm transition hover:border-amber-300 hover:shadow-md">
                <div class="grid gap-5 border-l-4 border-amber-500 px-5 py-6 md:grid-cols-[88px_minmax(0,1fr)_auto] md:items-center md:px-7 md:py-7">
                    <div class="flex items-start pt-1 md:justify-center md:pt-0">
                        <div class="flex size-16 items-center justify-center rounded-lg bg-white text-amber-600 ring-1 ring-amber-100">
                            <x-flux.icon name="clock" class="size-8" />
                        </div>
                    </div>

                    <div class="min-w-0">
                        <h2 class="text-lg font-bold text-slate-950">
                            Ketentuan Dokumen Lama
                        </h2>
                        <p class="mt-2 max-w-3xl text-base leading-7 text-slate-600">
                            Dokumen ketentuan yang berlaku sebelumnya atau tidak lagi digunakan.
                        </p>
                    </div>

                    <div class="flex items-center md:justify-end">
                        <a
                            href="{{ route('documents.obsolete.imports.create.legacy') }}"
                            class="inline-flex h-10 w-full min-w-40 items-center justify-center rounded-lg bg-amber-600 px-4 text-sm font-semibold text-white shadow-sm transition hover:bg-amber-700 md:w-auto"
                        >
                            Import Dokumen
                        </a>
                    </div>
                </div>
            </section>
        </div>
    </div>
</x-layouts.app>
