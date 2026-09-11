@extends('layouts.auth')

@section('title', 'Lupa Password - E-SISMAN')
@section('heading', 'Reset password')
@section('description', 'Masukkan email akun. Sistem akan mengirimkan tautan reset password jika email terdaftar.')

@section('content')
    <form method="POST" action="{{ route('password.email') }}" class="space-y-5">
        @csrf
        <div>
            <label for="email" class="block text-sm font-semibold text-slate-700">Email</label>
            <input id="email" name="email" type="email" value="{{ old('email') }}" required autofocus autocomplete="email" class="mt-2 block w-full rounded-md border border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-sky-500 focus:outline-none focus:ring-2 focus:ring-sky-100">
        </div>
        <button type="submit" class="flex h-11 w-full items-center justify-center rounded-md bg-sky-600 px-4 text-sm font-bold text-white shadow-sm transition hover:bg-sky-700 focus:outline-none focus:ring-2 focus:ring-sky-200">Kirim tautan reset</button>
        <a href="{{ route('login') }}" class="block text-center text-sm font-semibold text-sky-700 hover:text-sky-800">Kembali ke login</a>
    </form>
@endsection