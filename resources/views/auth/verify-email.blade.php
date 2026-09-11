@extends('layouts.auth')

@section('title', 'Verifikasi Email - E-SISMAN')
@section('heading', 'Verifikasi email')
@section('description', 'Cek email Anda untuk tautan verifikasi. Anda dapat mengirim ulang tautan jika belum menerima email.')

@section('content')
    <form method="POST" action="{{ route('verification.send') }}" class="space-y-5">
        @csrf
        <button type="submit" class="flex h-11 w-full items-center justify-center rounded-md bg-sky-600 px-4 text-sm font-bold text-white shadow-sm transition hover:bg-sky-700 focus:outline-none focus:ring-2 focus:ring-sky-200">Kirim ulang email verifikasi</button>
    </form>
    <form method="POST" action="{{ route('logout') }}" class="mt-4">
        @csrf
        <button type="submit" class="w-full text-center text-sm font-semibold text-slate-600 hover:text-slate-900">Logout</button>
    </form>
@endsection