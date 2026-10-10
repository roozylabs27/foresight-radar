@extends('errors.layout')

@section('title', '503 — Pemeliharaan Sistem')
@section('code', '503')

@section('content')
    <span class="status-badge status-503">503 &bull; SERVICE UNAVAILABLE</span>
    <h1 class="error-title">Pemeliharaan Sistem</h1>
    <p class="error-description">
        Sistem Foresight Radar sedang dalam proses pemeliharaan rutin atau pembaharuan versi. Layanan akan segera aktif kembali. Silakan coba beberapa saat lagi.
    </p>
    <div class="btn-group">
        <a href="javascript:location.reload()" class="btn btn-primary">Coba Muat Ulang</a>
    </div>
@endsection
