<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ trim($__env->yieldContent('title', 'E-SISMAN')) }}</title>
    <link rel="stylesheet" href="{{ mix('css/app.css') }}">
</head>
<body class="min-h-screen bg-slate-100 text-slate-900 antialiased">
    <main class="flex min-h-screen items-center justify-center px-4 py-10">
        <section class="w-full max-w-md rounded-lg border border-slate-200 bg-white p-8 shadow-sm">
            <div class="mb-8 text-center">
                <p class="text-xs font-semibold uppercase tracking-widest text-sky-600">E-SISMAN</p>
                <h1 class="mt-2 text-2xl font-bold text-slate-950">@yield('heading', 'Masuk')</h1>
                @hasSection('description')
                    <p class="mt-2 text-sm leading-6 text-slate-600">@yield('description')</p>
                @endif
            </div>

            @if (session('status'))
                <div class="mb-5 rounded-md border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
                    {{ session('status') }}
                </div>
            @endif

            @if (session('department_warning'))
                <div class="mb-5 rounded-md border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
                    <p class="font-semibold">{{ session('department_warning.title') }}</p>
                    <p class="mt-1">{{ session('department_warning.message') }}</p>
                </div>
            @endif

            @if ($errors->any())
                <div class="mb-5 rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                    <ul class="space-y-1">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @yield('content')
        </section>
    </main>
    <script src="{{ mix('js/app.js') }}" defer></script>
</body>
</html>