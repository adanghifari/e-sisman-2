@extends('layouts.auth')

@section('title', 'Two Factor Challenge - E-SISMAN')
@section('heading', 'Verifikasi dua faktor')
@section('description', 'Masukkan kode autentikator atau recovery code untuk melanjutkan login.')

@section('content')
    <form method="POST" action="{{ url('/two-factor-challenge') }}" class="space-y-5">
        @csrf
        <div>
            <label for="code" class="block text-sm font-semibold text-slate-700">Kode autentikator</label>
            <input id="code" name="code" type="text" inputmode="numeric" autocomplete="one-time-code" class="mt-2 block w-full rounded-md border border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-sky-500 focus:outline-none focus:ring-2 focus:ring-sky-100">
        </div>
        <div>
            <label for="recovery_code" class="block text-sm font-semibold text-slate-700">Recovery code</label>
            <input id="recovery_code" name="recovery_code" type="text" autocomplete="one-time-code" class="mt-2 block w-full rounded-md border border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-sky-500 focus:outline-none focus:ring-2 focus:ring-sky-100">
        </div>
        <button type="submit" class="flex h-11 w-full items-center justify-center rounded-md bg-sky-600 px-4 text-sm font-bold text-white shadow-sm transition hover:bg-sky-700 focus:outline-none focus:ring-2 focus:ring-sky-200">Verifikasi</button>
    </form>
@endsection