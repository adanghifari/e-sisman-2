@props([
    'permissionBundles',
    'permissionIds' => [],
])

<div
    x-data="accessGroupPermissionTree(@js($permissionIds), @js($permissionBundles->map(fn ($bundle, $bundleKey) => [
        'key' => $bundleKey,
        'permissionIds' => $bundle['permission_ids'],
        'readPermissionIds' => $bundle['read_permission_ids'],
        'actions' => collect($bundle['actions'])->map(fn ($actionGroup, $action) => [
            'key' => $action,
            'permissionIds' => $actionGroup['permission_ids'],
    ])->values()->all(),
    ])->values()->all()), $wire)"
>
    <div class="mb-4">
        <h3 class="text-base font-bold text-slate-900">Akses Menu</h3>
        <p class="mt-1 text-sm font-medium text-slate-500">Checklist fitur, action CRUD, atau permission spesifik.</p>
    </div>

    <div class="space-y-4">
        @foreach ($permissionBundles as $bundleKey => $bundle)
            <details class="group rounded-lg border border-slate-200 bg-white open:border-sky-200">
                <summary class="flex cursor-pointer list-none items-start justify-between gap-4 border-b border-slate-100 px-4 py-3">
                    <label class="flex min-w-0 items-start gap-3" onclick="event.stopPropagation()">
                        <input
                            type="checkbox"
                            data-permission-group
                            data-permission-ids='@json($bundle['permission_ids'])'
                            x-bind:checked="allSelected(@js($bundle['permission_ids']))"
                            x-on:change="toggleBundle('{{ $bundleKey }}')"
                            class="mt-1 size-5 rounded border-slate-300 text-sky-600 focus:ring-sky-500"
                        >
                        <span class="min-w-0">
                            <span class="block text-sm font-bold text-slate-900">{{ $bundle['label'] }}</span>
                            <span class="mt-1 flex flex-wrap items-center gap-2 text-xs font-medium text-slate-500">
                                <span>{{ $bundle['module'] }}</span>
                                <span>{{ count($bundle['permission_ids']) }} akses</span>
                                <span
                                    x-show="partiallySelected(@js($bundle['permission_ids']))"
                                    class="rounded-md bg-sky-50 px-2 py-0.5 font-semibold text-sky-700"
                                >Sebagian</span>
                            </span>
                        </span>
                    </label>

                    <x-flux.icon name="chevron-down" class="mt-1 size-4 shrink-0 text-slate-400 transition group-open:rotate-180" />
                </summary>

                <div class="space-y-3 px-4 py-4">
                    <div class="space-y-3 border-l-2 border-slate-100 pl-5">
                        @foreach ($bundle['actions'] as $action => $actionGroup)
                            <details class="group/action rounded-md bg-slate-50">
                                <summary class="flex cursor-pointer list-none items-start justify-between gap-4 px-2 py-1.5 hover:bg-slate-100">
                                    <label class="flex min-w-0 items-start gap-3" onclick="event.stopPropagation()">
                                        <input
                                            type="checkbox"
                                            data-permission-group
                                            data-permission-ids='@json($actionGroup['permission_ids'])'
                                            x-bind:checked="allSelected(@js($actionGroup['permission_ids']))"
                                            x-on:change="toggleActionGroup('{{ $bundleKey }}', '{{ $action }}')"
                                            class="mt-1 size-5 rounded border-slate-300 text-sky-600 focus:ring-sky-500"
                                        >
                                        <span class="min-w-0">
                                            <span class="flex flex-wrap items-center gap-2 text-sm font-semibold text-slate-800">
                                                <span>{{ $actionGroup['label'] }}</span>
                                                <span
                                                    x-show="partiallySelected(@js($actionGroup['permission_ids']))"
                                                    class="rounded-md bg-sky-50 px-2 py-0.5 text-xs font-semibold text-sky-700"
                                                >Sebagian</span>
                                            </span>
                                            <span class="block text-xs text-slate-500">{{ count($actionGroup['permission_ids']) }} permission</span>
                                        </span>
                                    </label>

                                    <x-flux.icon name="chevron-down" class="mt-1 size-4 shrink-0 text-slate-400 transition group-open/action:rotate-180" />
                                </summary>

                                <div class="space-y-2 border-l-2 border-slate-100 pb-2 pl-8 pr-2">
                                    @foreach ($actionGroup['permissions'] as $permission)
                                        <label class="flex items-start gap-3 rounded-md px-2 py-1.5 hover:bg-slate-50">
                                            <input
                                                type="checkbox"
                                                data-permission-id="{{ $permission->id }}"
                                                x-bind:checked="has({{ $permission->id }})"
                                                x-on:change="togglePermission({{ $permission->id }})"
                                                class="mt-1 size-5 rounded border-slate-300 text-sky-600 focus:ring-sky-500"
                                            >
                                            <span class="min-w-0">
                                                <span class="block text-sm font-medium text-slate-700">{{ $permission->name }}</span>
                                                <span class="block break-words text-xs text-slate-500">{{ $permission->code }}</span>
                                            </span>
                                        </label>
                                    @endforeach
                                </div>
                            </details>
                        @endforeach
                    </div>
                </div>
            </details>
        @endforeach
    </div>
</div>
