@props([
    'groups',
    'mobile' => false,
])

@foreach ($groups as $heading => $items)
    <div class="{{ $mobile ? 'mt-4 first:mt-3' : 'mt-6 first:mt-2' }}">
        <p class="{{ $mobile ? '' : 'sidebar-label' }} px-3 text-xs font-extrabold uppercase tracking-wide text-white">{{ $heading }}</p>

        <div class="mt-2 space-y-1">
            @foreach ($items as $item)
                @php
                    $isRouteActive = fn (string $route) => request()->routeIs($route, "{$route}.*");
                    $children = $item['children'] ?? [];
                    $hasChildren = count($children) > 0;
                    $active = $hasChildren
                        ? collect($children)->contains(fn ($child) => $isRouteActive($child['route']))
                        : $isRouteActive($item['route']);
                @endphp

                @if ($hasChildren)
                    <details class="group rounded-lg {{ $active ? 'bg-sky-900/70' : '' }}" {{ $active ? 'open' : '' }}>
                        <summary class="flex min-h-11 cursor-pointer list-none items-center gap-3 px-3 text-sm font-semibold text-white [&::-webkit-details-marker]:hidden">
                            <x-flux.icon :name="$item['icon']" class="size-5 text-white" />
                            <span @class(['sidebar-label' => ! $mobile])>{{ $item['label'] }}</span>
                            <x-flux.icon name="chevron-down" class="ml-auto size-4 text-white transition group-open:rotate-180" />
                        </summary>

                        <div class="space-y-1 pb-1 pl-8 pr-2">
                            @foreach ($children as $child)
                                @php
                                    $childActive = $isRouteActive($child['route']);
                                @endphp

                                <a
                                    href="{{ route($child['route']) }}"
                                    class="flex min-h-9 items-center gap-2 rounded-md px-3 text-sm font-semibold transition {{ $childActive ? 'bg-white text-sky-800 shadow-sm' : 'text-white/90 hover:bg-sky-900 hover:text-white' }}"
                                    @if ($mobile) data-mobile-nav-close @endif
                                >
                                    <x-flux.icon :name="$child['icon']" class="size-4 {{ $childActive ? 'text-sky-700' : 'text-white' }}" />
                                    <span @class(['sidebar-label' => ! $mobile])>{{ $child['label'] }}</span>

                                    @if (isset($child['badge']) && $child['badge'] !== null)
                                        <span @class([
                                            'ml-auto inline-flex items-center justify-center rounded-full px-2 py-0.5 text-xs font-semibold leading-none',
                                            $childActive
                                                ? 'bg-sky-100 text-sky-800'
                                                : ((int) $child['badge'] > 0 ? 'bg-sky-600 text-white' : 'bg-sky-900 text-sky-300 border border-sky-700/70'),
                                            'sidebar-label' => ! $mobile,
                                        ])>
                                            {{ $child['badge'] }}
                                        </span>
                                    @endif
                                </a>
                            @endforeach
                        </div>
                    </details>
                @else
                    <a
                        href="{{ route($item['route']) }}"
                        class="flex min-h-11 items-center gap-3 rounded-lg px-3 text-sm font-semibold transition {{ $active ? 'bg-white text-sky-800 shadow-sm' : 'text-white hover:bg-sky-900 hover:text-white' }}"
                        @if ($mobile) data-mobile-nav-close @endif
                    >
                        <x-flux.icon :name="$item['icon']" class="size-5 {{ $active ? 'text-sky-700' : 'text-white' }}" />
                        <span @class(['sidebar-label' => ! $mobile])>{{ $item['label'] }}</span>

                        @if (isset($item['badge']) && $item['badge'] !== null)
                            <span @class([
                                'ml-auto inline-flex items-center justify-center rounded-full px-2 py-0.5 text-xs font-semibold leading-none',
                                $active
                                    ? 'bg-sky-100 text-sky-800'
                                    : ((int) $item['badge'] > 0 ? 'bg-sky-600 text-white' : 'bg-sky-900 text-sky-300 border border-sky-700/70'),
                                'sidebar-label' => ! $mobile,
                            ])>
                                {{ $item['badge'] }}
                            </span>
                        @endif
                    </a>
                @endif
            @endforeach
        </div>
    </div>
@endforeach
