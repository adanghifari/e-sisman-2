@props([
    'label',
    'users',
    'selectedUser' => null,
])

@php
    $selectedSourceLabel = $selectedUser?->id === auth()->id()
        ? 'Tanpa perwakilan'
        : 'Diwakilkan';
@endphp

<section class="overflow-visible rounded-lg border border-slate-200 bg-white shadow-sm" data-official-preparer>
    <div class="flex items-center justify-between gap-4 border-b border-slate-200 px-6 py-5">
        <h2 class="text-lg font-bold text-slate-900">{{ $label }}</h2>

        <button
            type="button"
            class="inline-flex h-10 items-center justify-center rounded-lg border border-sky-200 bg-sky-50 px-4 text-sm font-semibold text-sky-700 shadow-sm transition hover:border-sky-300 hover:bg-sky-100 focus:outline-none focus:ring-2 focus:ring-sky-200"
            data-use-current-user
            data-user-id="{{ auth()->id() }}"
            data-user-name="{{ auth()->user()->name }}"
            data-user-email="{{ auth()->user()->email }}"
            data-user-title="{{ auth()->user()->jabatan }}"
            data-user-initials="{{ auth()->user()->initials() }}"
        >
            Saya Mengajukan tanpa Perwakilan
        </button>
    </div>

    <div class="space-y-5 px-6 py-6">
        <div class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-3">
            <span class="block text-[11px] font-semibold uppercase tracking-wide text-slate-500">Pengisi Form</span>
            <div class="mt-2 flex items-center gap-2.5">
                <span class="grid size-9 shrink-0 place-items-center rounded-full bg-sky-100 text-xs font-bold text-sky-700">
                    {{ auth()->user()->initials() }}
                </span>
                <span class="min-w-0">
                    <span class="block truncate text-sm font-bold leading-tight text-slate-900">{{ auth()->user()->name }}</span>
                    <span class="mt-0.5 block truncate text-xs font-medium leading-tight text-slate-500">
                        {{ auth()->user()->jabatan ?: auth()->user()->email }}
                    </span>
                </span>
                <span class="ml-auto rounded-full bg-white px-2.5 py-0.5 text-[11px] font-semibold text-slate-500 ring-1 ring-slate-200">
                    Tercatat di sistem
                </span>
            </div>
        </div>

        <div class="block">
            <span class="mb-2 block text-base font-medium text-slate-500">Pilih Penyusun Resmi</span>
            <x-ui.user-search-select
                name="official_preparer_id"
                :users="$users"
                :selected-user="$selectedUser"
                placeholder="Pilih penyusun pemilik proses"
                data-official-preparer-picker
                required
            />

            @error('official_preparer_id')
                <span class="mt-2 block text-sm font-semibold text-red-500">{{ $message }}</span>
            @enderror
        </div>

        <div @class([
            'rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-3',
            'hidden' => ! $selectedUser,
        ]) data-official-preparer-card>
            <span class="block text-[11px] font-semibold uppercase tracking-wide text-emerald-700">Penyusun Resmi</span>
            <div class="mt-2 flex items-center gap-2.5">
                <span class="grid size-9 shrink-0 place-items-center rounded-full bg-white text-xs font-bold text-emerald-700 ring-1 ring-emerald-200" data-official-preparer-initials>{{ $selectedUser?->initials() }}</span>
                <span class="min-w-0">
                    <span class="block truncate text-sm font-bold leading-tight text-slate-900" data-official-preparer-name>{{ $selectedUser?->name }}</span>
                    <span class="mt-0.5 block truncate text-xs font-medium leading-tight text-slate-500" data-official-preparer-meta>{{ $selectedUser ? ($selectedUser->jabatan ?: $selectedUser->email) : '' }}</span>
                </span>
                <span class="ml-auto rounded-full bg-white px-2.5 py-0.5 text-[11px] font-semibold text-emerald-700 ring-1 ring-emerald-200" data-official-preparer-source>{{ $selectedUser ? $selectedSourceLabel : '' }}</span>
            </div>
        </div>

        <div @class([
            'rounded-lg border border-dashed border-slate-200 bg-white px-3 py-3 text-xs leading-5 text-slate-500',
            'hidden' => $selectedUser,
        ]) data-official-preparer-empty>
            Gunakan tombol
            <span class="inline-flex items-center rounded border border-sky-200 bg-sky-50 px-1.5 py-0.5 font-semibold text-sky-700">Saya Mengajukan tanpa Perwakilan</span>
            jika pengisi form juga menjadi penyusun resmi.
        </div>
    </div>
</section>
