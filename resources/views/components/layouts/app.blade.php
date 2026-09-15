<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ? $title.' - E-SISMAN' : 'E-SISMAN' }}</title>
    <link rel="stylesheet" href="{{ mix('css/app.css') }}">
    @livewireStyles
    <style>
        .sidebar-icon-open { display: block; }
        .sidebar-icon-closed { display: none; }
        .app-shell.is-sidebar-collapsed .sidebar-icon-open { display: none !important; }
        .app-shell.is-sidebar-collapsed .sidebar-icon-closed { display: block !important; }
    </style>
</head>
<body class="min-h-screen bg-slate-50 text-slate-900 antialiased">
    @php
        $user = auth()->user();
        $canSeeMenuItem = function (array $item) use ($user): bool {
            if (! $user) {
                return false;
            }

            $permission = $item['permission'] ?? null;
            $route = $item['route'] ?? null;

            if ($permission !== null) {
                return $user->hasPermission($permission);
            }

            if ($route !== null) {
                return $user->canAccessRoute($route);
            }

            return true;
        };
        $resolveBadge = function (array $item) use ($user): ?int {
            if (! $user) {
                return null;
            }

            $badgeKey = $item['badge'] ?? null;
            $route = $item['route'] ?? null;

            if ($badgeKey === 'needs_process' || $route === 'documents.inbox') {
                return app(\App\Http\Controllers\DocumentManagement\DocumentInboxController::class)->needsProcessCount($user);
            }

            return is_numeric($badgeKey) ? (int) $badgeKey : null;
        };
        $menuGroups = collect(config('navigation'))
            ->map(function (array $items) use ($canSeeMenuItem, $resolveBadge): array {
                return collect($items)
                    ->map(function (array $item) use ($canSeeMenuItem, $resolveBadge): ?array {
                        if (isset($item['children'])) {
                            $children = collect($item['children'])
                                ->filter($canSeeMenuItem)
                                ->map(fn (array $child): array => array_merge($child, ['badge' => $resolveBadge($child)]))
                                ->values()
                                ->all();

                            return count($children) > 0 ? array_merge($item, ['children' => $children]) : null;
                        }

                        return $canSeeMenuItem($item) ? array_merge($item, ['badge' => $resolveBadge($item)]) : null;
                    })
                    ->filter()
                    ->values()
                    ->all();
            })
            ->filter(fn (array $items): bool => count($items) > 0)
            ->all();
    @endphp

    <div class="app-shell min-h-screen bg-slate-50 lg:grid lg:grid-cols-[280px_1fr]" data-app-shell>
        <aside class="hidden border-r border-sky-900 bg-sky-950 text-white lg:sticky lg:top-0 lg:flex lg:h-screen lg:flex-col">
            <div class="sidebar-header flex items-center justify-between gap-3 px-7 py-7">
                <a href="{{ route('dashboard') }}" class="sidebar-brand ml-3 flex min-w-0 items-center">
                    <img src="{{ asset('image/esisman_logo.png') }}" alt="E-SISMAN" class="sidebar-brand-logo h-auto w-[140px] max-w-full object-contain">
                </a>
                <button type="button" class="sidebar-toggle grid size-9 shrink-0 place-items-center rounded-lg border border-sky-700 bg-sky-900 text-white shadow-sm transition hover:bg-sky-800" data-sidebar-toggle aria-label="Sembunyikan sidebar" aria-expanded="true">
                    <x-flux.icon name="chevron-left" class="sidebar-icon-open size-4 text-white" />
                    <x-flux.icon name="chevron-right" class="sidebar-icon-closed size-4 text-white" />
                </button>
            </div>

            <x-ui.scroll-area as="nav" class="sidebar-scrollbar flex-1 px-5 pb-5">
                <x-layouts.sidebar-nav :groups="$menuGroups" />
            </x-ui.scroll-area>

            <div class="sidebar-user border-t border-sky-800 p-5">
                <details class="group">
                    <summary class="sidebar-user-trigger flex w-full cursor-pointer list-none items-center gap-3 rounded-lg bg-sky-900 px-2.5 py-2 text-left transition hover:bg-sky-800">
                        <span class="sidebar-account-avatar grid size-11 shrink-0 place-items-center rounded-lg bg-white text-xl font-bold leading-none text-sky-800 ring-1 ring-sky-200">
                            {{ $user?->initials() ?? '?' }}
                        </span>
                        <span class="sidebar-label min-w-0">
                            <span class="block truncate text-sm font-semibold text-white">{{ $user?->name }}</span>
                            <span class="block truncate text-xs text-white/80">{{ $user?->email }}</span>
                        </span>
                    </summary>
                    <div class="mt-2 space-y-1">
                        <a href="{{ route('profile.edit') }}" class="block rounded-lg border border-sky-700 bg-sky-900 px-3 py-2 text-sm font-semibold text-white transition hover:bg-sky-800">Profile</a>
                        <a href="{{ route('security.edit') }}" class="block rounded-lg border border-sky-700 bg-sky-900 px-3 py-2 text-sm font-semibold text-white transition hover:bg-sky-800">Security</a>
                    </div>
                    <form method="POST" action="{{ route('logout') }}" class="mt-2">
                        @csrf
                        <button type="submit" class="w-full rounded-lg border border-sky-700 bg-sky-900 px-3 py-2 text-left text-sm font-semibold text-white transition hover:bg-sky-800">Log out</button>
                    </form>
                </details>

                <div class="sidebar-kip-brand flex w-full items-center justify-start gap-3 px-2.5 pt-5">
                    <span class="grid size-10 shrink-0 place-items-center rounded-lg bg-white shadow-sm ring-1 ring-slate-200">
                        <img src="{{ asset('image/krakatau_logo.png') }}" alt="Krakatau International Port" class="size-7 object-contain">
                    </span>
                    <span class="sidebar-label grid leading-none">
                        <span class="text-sm font-extrabold uppercase text-white">Krakatau</span>
                        <span class="mt-1 text-[10px] font-bold uppercase text-white">International Port</span>
                    </span>
                </div>
            </div>
        </aside>

        <div class="min-w-0">
            <div class="fixed inset-x-0 top-0 z-30 lg:hidden">
                <header class="flex h-16 items-center justify-between border-b border-sky-800 bg-sky-950 px-4 text-white shadow-sm">
                    <a href="{{ route('dashboard') }}" class="flex items-center gap-2">
                        <span class="grid size-9 place-items-center rounded-md bg-white shadow-sm ring-1 ring-slate-200">
                            <img src="{{ asset('image/krakatau_logo.png') }}" alt="Krakatau International Port" class="size-6 object-contain">
                        </span>
                        <span class="text-sm font-extrabold text-white">E-SISMAN</span>
                    </a>
                    <button type="button" class="grid size-10 place-items-center rounded-lg border border-sky-700 bg-sky-900 text-white shadow-sm transition hover:bg-sky-800" data-mobile-nav-toggle aria-label="Tampilkan menu" aria-expanded="false">
                        <x-flux.icon name="bars-3" class="size-6 text-white" />
                    </button>
                </header>
                <div class="mobile-nav-panel border-b border-sky-800 bg-sky-950 text-white shadow-lg">
                    <x-ui.scroll-area max-height="calc(100vh - 4rem)" class="sidebar-scrollbar px-4 pb-4">
                        <x-layouts.sidebar-nav :groups="$menuGroups" mobile />
                    </x-ui.scroll-area>
                </div>
            </div>

            <main class="min-h-screen bg-slate-50 p-5 pt-20 md:p-8 md:pt-20 lg:pt-8">
                {{ $slot }}
            </main>
        </div>
    </div>

    @if (session('document_success'))
        <x-ui.success-dialog :title="session('document_success.title')" :message="session('document_success.message')" />
    @endif

    @if (session('department_warning'))
        <x-ui.success-dialog variant="warning" :title="session('department_warning.title')" :message="session('department_warning.message')" />
    @endif

    @if (session('restore_warning'))
        <x-ui.success-dialog variant="warning" :title="session('restore_warning.title')" :message="session('restore_warning.message')" />
    @endif

    @if (session('delete_warning'))
        <x-ui.success-dialog variant="warning" :title="session('delete_warning.title')" :message="session('delete_warning.message')" />
    @endif

    @livewireScripts
    <script src="{{ mix('js/app.js') }}" defer></script>
    <script>
        (() => {
            const shell = document.querySelector('[data-app-shell]');
            const toggle = document.querySelector('[data-sidebar-toggle]');
            if (!shell) return;
            if (toggle) {
                const applyState = (collapsed) => {
                    shell.classList.toggle('is-sidebar-collapsed', collapsed);
                    toggle.setAttribute('aria-expanded', String(!collapsed));
                    toggle.setAttribute('aria-label', collapsed ? 'Tampilkan sidebar' : 'Sembunyikan sidebar');
                };
                applyState(localStorage.getItem('esisman-sidebar-collapsed') === 'true');
                toggle.addEventListener('click', () => {
                    const collapsed = !shell.classList.contains('is-sidebar-collapsed');
                    localStorage.setItem('esisman-sidebar-collapsed', String(collapsed));
                    applyState(collapsed);
                });
            }
            const mobileToggle = document.querySelector('[data-mobile-nav-toggle]');
            document.querySelectorAll('[data-mobile-nav-close]').forEach((element) => {
                element.addEventListener('click', () => {
                    shell.classList.remove('is-mobile-nav-open');
                    mobileToggle?.setAttribute('aria-expanded', 'false');
                });
            });
            mobileToggle?.addEventListener('click', () => {
                const isOpen = shell.classList.toggle('is-mobile-nav-open');
                mobileToggle.setAttribute('aria-expanded', String(isOpen));
            });
        })();
    </script>
</body>
</html>