@props([
    'active' => false,
    'name' => null,
    'label' => 'Status',
    'activeText' => 'Active',
    'inactiveText' => 'Inactive',
    'activeDescription' => null,
    'inactiveDescription' => null,
])

<label
    x-data="{ active: @js((bool) $active) }"
    class="flex cursor-pointer items-center justify-between gap-4 rounded-lg border border-slate-200 bg-slate-50 px-4 py-3 transition hover:border-sky-200 hover:bg-white"
>
    <input
        type="checkbox"
        name="{{ $name }}"
        x-model="active"
        @checked($active)
        {{ $attributes->class(['sr-only']) }}
    >

    <span class="min-w-0">
        <span class="block text-sm font-semibold text-slate-800">{{ $label }}</span>

        @if ($activeDescription || $inactiveDescription)
            <span class="block text-xs text-slate-500">
                @if ($activeDescription)
                    <span x-show="active">{{ $activeDescription }}</span>
                @endif

                @if ($inactiveDescription)
                    <span x-show="! active">{{ $inactiveDescription }}</span>
                @endif
            </span>
        @endif
    </span>

    <span class="inline-flex shrink-0 items-center gap-3">
        <span class="min-w-14 text-right text-sm font-semibold text-slate-700">
            <span x-show="active">{{ $activeText }}</span>
            <span x-show="! active">{{ $inactiveText }}</span>
        </span>

        <span
            class="relative h-6 w-11 rounded-full transition"
            :class="active ? 'bg-sky-600' : 'bg-slate-300'"
        >
            <span
                class="absolute left-1 top-1 size-4 rounded-full bg-white shadow-sm transition"
                :class="active ? 'translate-x-5' : 'translate-x-0'"
            ></span>
        </span>
    </span>
</label>
