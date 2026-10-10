@extends('errors.layout')

@section('title', '500 — Kesalahan Server')
@section('code', '500')

@section('content')
    <span class="status-badge status-500">500 &bull; SERVER ERROR</span>
    <h1 class="error-title">Kesalahan Server Internal</h1>
    <p class="error-description">
        Terjadi kendala teknis internal pada sistem saat memproses permintaan Anda. Tim teknis telah diberi notifikasi mengenai kendala ini.
    </p>
    <div class="btn-group">
        <a href="{{ route('dashboard.') }}" class="btn btn-primary">Ke Dashboard</a>
        <a href="javascript:history.back()" class="btn btn-default">Halaman Sebelumnya</a>
    </div>
@endsection
