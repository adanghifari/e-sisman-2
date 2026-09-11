<div class="space-y-6">
    <x-ui.page-header
        title="Menu Akses"
        description="Katalog akses yang bisa diberikan ke group."
    />

    <x-ui.panel
        title="Bundle Menu Akses"
        description="Menampilkan {{ $permissionBundles->count() }} bundle dari {{ $totalPermissions }} akses terdaftar."
        :padded="false"
    >
        <div class="grid gap-4 border-b border-slate-200 p-5 md:grid-cols-[minmax(260px,1fr)_minmax(180px,260px)]">
            <x-ui.input
                label="Cari Akses"
                name="search"
                wire:model.debounce.300ms="search"
                placeholder="Cari kode, nama, atau route..."
            />

            <x-ui.select
                label="Module"
                name="module"
                wire:model="module"
                :value="$module"
                :options="$moduleOptions"
            />
        </div>

        <div class="max-h-[620px] overflow-y-auto p-5">
            <div class="space-y-3">
                @forelse ($permissionBundles as $bundle)
                    <details class="group rounded-lg border border-slate-200 bg-white open:border-sky-200 open:bg-sky-50/30">
                        <summary class="flex cursor-pointer list-none items-center justify-between gap-4 px-5 py-4">
                            <div class="min-w-0">
                                <div class="flex flex-wrap items-center gap-2">
                                    <h3 class="font-bold text-slate-900">Fitur {{ $bundle['feature'] }}</h3>
                                    <span class="rounded-md bg-slate-100 px-2 py-0.5 text-xs font-semibold text-slate-600">
                                        {{ $bundle['module'] }}
                                    </span>
                                    @if ($bundle['requires_read'])
                                        <span class="rounded-md bg-amber-50 px-2 py-0.5 text-xs font-semibold text-amber-700">
                                            Perlu Read untuk CUD
                                        </span>
                                    @endif
                                </div>
                                <p class="mt-1 break-words text-xs font-medium text-slate-500">{{ $bundle['feature_key'] }}</p>
                            </div>

                            <div class="flex shrink-0 items-center gap-3">
                                <span class="text-xs font-semibold text-slate-500">{{ $bundle['permissions']->count() }} akses</span>
                                <x-flux.icon name="chevron-down" class="size-5 text-slate-400 transition group-open:rotate-180" />
                            </div>
                        </summary>

                        <div class="space-y-3 border-t border-slate-200 px-5 py-4">
                            @foreach ($bundle['action_groups'] as $actionGroup)
                                <details class="group/action rounded-lg border border-slate-200 bg-white">
                                    <summary class="flex cursor-pointer list-none items-center justify-between gap-4 px-4 py-3">
                                        <div>
                                            <p class="font-semibold text-slate-800">Akses {{ $actionGroup['label'] }}</p>
                                            <p class="mt-1 text-xs text-slate-500">{{ $actionGroup['permissions']->count() }} permission</p>
                                        </div>
                                        <x-flux.icon name="chevron-down" class="size-4 text-slate-400 transition group-open/action:rotate-180" />
                                    </summary>

                                    <div class="border-t border-slate-100 px-4 py-3">
                                        <div class="overflow-hidden rounded-lg border border-slate-100">
                                            <table class="w-full min-w-[760px] text-left text-sm">
                                                <thead class="bg-slate-50 text-xs uppercase text-slate-500">
                                                    <tr>
                                                        <th class="px-4 py-2 font-semibold">Nama Akses</th>
                                                        <th class="px-4 py-2 font-semibold">Kode</th>
                                                        <th class="px-4 py-2 font-semibold">Route</th>
                                                    </tr>
                                                </thead>
                                                <tbody class="divide-y divide-slate-100">
                                                    @foreach ($actionGroup['permissions'] as $permission)
                                                        <tr>
                                                            <td class="px-4 py-3 font-semibold text-slate-800">{{ $permission->name }}</td>
                                                            <td class="break-words px-4 py-3 text-slate-600">{{ $permission->code }}</td>
                                                            <td class="px-4 py-3 text-slate-600">{{ $permission->route ?: '-' }}</td>
                                                        </tr>
                                                    @endforeach
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </details>
                            @endforeach
                        </div>
                    </details>
                @empty
                    <div class="rounded-lg border border-dashed border-slate-200 px-5 py-10 text-center text-sm text-slate-500">
                        Tidak ada akses yang cocok dengan filter.
                    </div>
                @endforelse
            </div>
        </div>
    </x-ui.panel>
</div>
