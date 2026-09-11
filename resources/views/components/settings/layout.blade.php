@props([
    'heading',
    'subheading' => null,
])

@php
    $items = [
        ['route' => 'profile.edit', 'label' => __('Profile')],
        ['route' => 'security.edit', 'label' => __('Security')],
        ['route' => 'appearance.edit', 'label' => __('Appearance')],
    ];
@endphp

<div class="flex items-start gap-6 max-md:flex-col">
    <nav class="w-full shrink-0 space-y-1 md:w-56" aria-label="{{ __('Settings') }}">
        @foreach ($items as $item)
            @php($active = request()->routeIs($item['route']))
            <a
                href="{{ route($item['route']) }}"
                class="block rounded-lg px-3 py-2 text-sm font-semibold transition {{ $active ? 'bg-sky-50 text-sky-700 ring-1 ring-sky-100' : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900' }}"
            >
                {{ $item['label'] }}
            </a>
        @endforeach
    </nav>

    <div class="min-w-0 flex-1 self-stretch">
        <div class="mb-5">
            <h2 class="text-lg font-semibold text-slate-950">{{ $heading }}</h2>

            @if ($subheading)
                <p class="mt-1 text-sm text-slate-500">{{ $subheading }}</p>
            @endif
        </div>

        <div class="w-full max-w-2xl">
            {{ $slot }}
        </div>
    </div>
</div>
