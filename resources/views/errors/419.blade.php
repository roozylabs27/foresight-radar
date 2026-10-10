@extends('errors.layout')

@section('title', '419 — Sesi Kedaluwarsa')
@section('code', '419')

@section('content')
    <span class="status-badge status-419">419 &bull; PAGE EXPIRED</span>
    <h1 class="error-title">Sesi Kedaluwarsa</h1>
    <p class="error-description">
        Sesi interaksi atau token keamanan form Anda telah kedaluwarsa karena tidak ada aktivitas dalam waktu lama. Silakan muat ulang halaman.
    </p>
    <div class="btn-group">
        <a href="javascript:location.reload()" class="btn btn-primary">Muat Ulang Halaman</a>
        <a href="{{ route('login') }}" class="btn btn-default">Masuk Kembali</a>
    </div>
@endsection
