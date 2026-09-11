@extends('layouts.auth')

@section('title', 'Konfirmasi Password - E-SISMAN')
@section('heading', 'Konfirmasi password')
@section('description', 'Masukkan password untuk melanjutkan aksi keamanan ini.')

@section('content')
    <form method="POST" action="{{ route('password.confirm') }}" class="space-y-5">
        @csrf
        <div>
            <label for="password" class="block text-sm font-semibold text-slate-700">Password</label>
            <input id="password" name="password" type="password" required autocomplete="current-password" class="mt-2 block w-full rounded-md border border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-sky-500 focus:outline-none focus:ring-2 focus:ring-sky-100">
        </div>
        <button type="submit" class="flex h-11 w-full items-center justify-center rounded-md bg-sky-600 px-4 text-sm font-bold text-white shadow-sm transition hover:bg-sky-700 focus:outline-none focus:ring-2 focus:ring-sky-200">Konfirmasi</button>
    </form>
@endsection