@extends('layouts.auth')

@section('title', 'Masuk - E-SISMAN')
@section('heading', 'Masuk ke E-SISMAN')
@section('description', 'Gunakan NIK dan password akun internal untuk mengakses sistem manajemen dokumen.')

@section('content')
    <form method="POST" action="{{ route('login') }}" class="space-y-5">
        @csrf
        <div>
            <label for="nik" class="block text-sm font-semibold text-slate-700">NIK</label>
            <input id="nik" name="nik" type="text" value="{{ old('nik') }}" required autofocus autocomplete="username" class="mt-2 block w-full rounded-md border border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-sky-500 focus:outline-none focus:ring-2 focus:ring-sky-100">
        </div>

        <div>
            <label for="password" class="block text-sm font-semibold text-slate-700">Password</label>
            <input id="password" name="password" type="password" required autocomplete="current-password" class="mt-2 block w-full rounded-md border border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-sky-500 focus:outline-none focus:ring-2 focus:ring-sky-100">
        </div>

        <div class="flex items-center justify-between gap-4 text-sm">
            <label class="inline-flex items-center gap-2 text-slate-600">
                <input type="checkbox" name="remember" class="rounded border-slate-300 text-sky-600 focus:ring-sky-500">
                Ingat saya
            </label>
            @if (Route::has('password.request'))
                <a href="{{ route('password.request') }}" class="font-semibold text-sky-700 hover:text-sky-800">Lupa password?</a>
            @endif
        </div>

        <button type="submit" class="flex h-11 w-full items-center justify-center rounded-md bg-sky-600 px-4 text-sm font-bold text-white shadow-sm transition hover:bg-sky-700 focus:outline-none focus:ring-2 focus:ring-sky-200">
            Masuk
        </button>
    </form>
@endsection