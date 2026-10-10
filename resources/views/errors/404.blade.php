@extends('errors.layout')

@section('title', '404 — Halaman Tidak Ditemukan')
@section('code', '404')

@section('content')
    <span class="status-badge status-404">404 &bull; NOT FOUND</span>
    <h1 class="error-title">Halaman Tidak Ditemukan</h1>
    <p class="error-description">
        Halaman atau tautan yang Anda tuju tidak ditemukan atau telah dipindahkan. Pastikan alamat URL yang dimasukkan sudah benar.
    </p>
    <div class="btn-group">
        @auth
            <a href="{{ route('dashboard.') }}" class="btn btn-primary">Ke Dashboard</a>
        @else
            <a href="{{ url('/') }}" class="btn btn-primary">Halaman Utama</a>
        @endauth
        <a href="javascript:history.back()" class="btn btn-default">Halaman Sebelumnya</a>
    </div>
@endsection
