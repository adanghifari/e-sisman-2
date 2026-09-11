@extends('layouts.auth')

@section('title', 'Reset Password - E-SISMAN')
@section('heading', 'Buat password baru')
@section('description', 'Masukkan email dan password baru untuk akun Anda.')

@section('content')
    <form method="POST" action="{{ route('password.update') }}" class="space-y-5">
        @csrf
        <input type="hidden" name="token" value="{{ request()->route('token') }}">
        <div>
            <label for="email" class="block text-sm font-semibold text-slate-700">Email</label>
            <input id="email" name="email" type="email" value="{{ old('email', request('email')) }}" required autofocus autocomplete="email" class="mt-2 block w-full rounded-md border border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-sky-500 focus:outline-none focus:ring-2 focus:ring-sky-100">
        </div>
        <div>
            <label for="password" class="block text-sm font-semibold text-slate-700">Password baru</label>
            <input id="password" name="password" type="password" required autocomplete="new-password" class="mt-2 block w-full rounded-md border border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-sky-500 focus:outline-none focus:ring-2 focus:ring-sky-100">
        </div>
        <div>
            <label for="password_confirmation" class="block text-sm font-semibold text-slate-700">Konfirmasi password</label>
            <input id="password_confirmation" name="password_confirmation" type="password" required autocomplete="new-password" class="mt-2 block w-full rounded-md border border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-sky-500 focus:outline-none focus:ring-2 focus:ring-sky-100">
        </div>
        <button type="submit" class="flex h-11 w-full items-center justify-center rounded-md bg-sky-600 px-4 text-sm font-bold text-white shadow-sm transition hover:bg-sky-700 focus:outline-none focus:ring-2 focus:ring-sky-200">Simpan password</button>
    </form>
@endsection