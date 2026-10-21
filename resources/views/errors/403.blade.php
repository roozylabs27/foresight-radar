@extends('errors.layout')

@section('title', '403 — Akses Ditolak')
@section('code', '403')
@section('tone', 'warning')
@section('badge')
    403 &bull; FORBIDDEN
@endsection
@section('heading', 'Akses Ditolak')

@section('description')
    {{ $exception->getMessage() ?: 'Anda tidak memiliki hak akses atau izin yang diperlukan untuk membuka halaman ini. Hubungi administrator apabila Anda memerlukan kewenangan akses.' }}
@endsection

@section('actions')
    @auth
        <x-error-button :href="route('dashboard.')">Ke Dashboard</x-error-button>
    @else
        <x-error-button :href="route('login')">Masuk Akun</x-error-button>
    @endauth
    <x-error-button href="javascript:history.back()" variant="outline">Halaman Sebelumnya</x-error-button>
@endsection
