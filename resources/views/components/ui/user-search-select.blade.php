@props([
    'name',
    'users',
    'placeholder' => 'Pilih user',
    'selectedUser' => null,
])

@php
    $rootAttributes = $attributes->whereDoesntStartWith('wire:')->except('required');
    $wireAttributes = $attributes->whereStartsWith('wire:');
@endphp

<div
    {{ $rootAttributes->class(['relative']) }}
    data-user-search-select
>
    <input
        type="hidden"
        name="{{ $name }}"
        value="{{ $selectedUser?->id }}"
        data-user-search-value
        {{ $attributes->has('required') ? 'required' : '' }}
        {{ $wireAttributes }}
    >

    <button
        type="button"
        class="flex h-12 w-full items-center gap-3 rounded-lg border border-slate-300 bg-white px-3 text-left text-sm font-medium text-slate-600 outline-none transition hover:border-sky-300 focus:border-sky-400 focus:ring-2 focus:ring-sky-100"
        data-user-search-trigger
        aria-expanded="false"
    >
        <span @class([
            'grid size-8 shrink-0 place-items-center rounded-full text-xs font-bold ring-1',
            'bg-sky-50 text-sky-700 ring-sky-100' => $selectedUser,
            'bg-slate-100 text-slate-500 ring-slate-200' => ! $selectedUser,
        ]) data-user-search-initials>
            {{ $selectedUser?->initials() ?? '?' }}
        </span>
        <span class="min-w-0 flex-1">
            <span class="block truncate font-semibold" data-user-search-name>{{ $selectedUser?->name ?? $placeholder }}</span>
            <span @class(['truncate text-xs text-slate-500', 'hidden' => ! $selectedUser]) data-user-search-meta>
                {{ $selectedUser ? ($selectedUser->jabatan ?: $selectedUser->email) : '' }}
            </span>
        </span>
        <x-flux.icon name="chevron-down" class="size-5 shrink-0 text-slate-400" />
    </button>

    <div class="absolute left-0 right-0 z-50 mt-2 hidden overflow-hidden rounded-lg border border-slate-200 bg-white shadow-lg" data-user-search-panel>
        <div class="border-b border-slate-100 p-2">
            <input
                type="search"
                placeholder="Cari nama, jabatan, atau email"
                class="h-10 w-full rounded-lg border border-slate-200 bg-slate-50 px-3 text-sm font-medium text-slate-600 outline-none transition placeholder:text-slate-400 focus:border-sky-300 focus:bg-white focus:ring-2 focus:ring-sky-100"
                data-user-search-input
            >
        </div>

        <div class="max-h-80 overflow-y-auto py-1 app-scrollbar" data-user-search-options>
            @foreach ($users as $user)
                <button
                    type="button"
                    class="flex w-full items-center gap-3 px-3 py-2 text-left transition hover:bg-slate-100 focus:bg-slate-100 focus:outline-none"
                    data-user-search-option
                    data-value="{{ $user->id }}"
                    data-name="{{ $user->name }}"
                    data-meta="{{ $user->jabatan ?: $user->email }}"
                    data-email="{{ $user->email }}"
                    data-title="{{ $user->jabatan }}"
                    data-initials="{{ $user->initials() }}"
                    data-search="{{ \Illuminate\Support\Str::lower($user->name.' '.$user->email.' '.$user->jabatan) }}"
                >
                    <span class="grid size-9 shrink-0 place-items-center rounded-full bg-sky-50 text-xs font-bold text-sky-700 ring-1 ring-sky-100">
                        {{ $user->initials() }}
                    </span>
                    <span class="min-w-0 flex-1">
                        <span class="block truncate text-sm font-bold text-slate-900">{{ $user->name }}</span>
                        <span class="mt-0.5 block truncate text-xs font-medium text-slate-500">{{ $user->jabatan ?: $user->email }}</span>
                    </span>
                </button>
            @endforeach

            <div class="hidden px-3 py-4 text-center text-sm font-medium text-slate-500" data-user-search-empty>
                User tidak ditemukan.
            </div>
        </div>
    </div>
</div>
