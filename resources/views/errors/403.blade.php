@extends('errors.layout')

@section('title', '403 — Akses Ditolak')
@section('code', '403')

@section('content')
    <span class="status-badge status-403">403 &bull; FORBIDDEN</span>
    <h1 class="error-title">Akses Ditolak</h1>
    <p class="error-description">
        {{ $exception->getMessage() ?: 'Anda tidak memiliki hak akses atau izin yang diperlukan untuk membuka halaman ini. Hubungi administrator apabila Anda memerlukan kewenangan akses.' }}
    </p>
    <div class="btn-group">
        @auth
            <a href="{{ route('dashboard.') }}" class="btn btn-primary">Ke Dashboard</a>
        @else
            <a href="{{ route('login') }}" class="btn btn-primary">Masuk Akun</a>
        @endauth
        <a href="javascript:history.back()" class="btn btn-default">Halaman Sebelumnya</a>
    </div>
@endsection
