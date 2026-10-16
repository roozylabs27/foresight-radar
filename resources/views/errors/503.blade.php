@extends('errors.layout')

@section('title', '503 — Pemeliharaan Sistem')
@section('code', '503')
@section('tone', 'info')
@section('badge')
    503 &bull; SERVICE UNAVAILABLE
@endsection
@section('heading', 'Pemeliharaan Sistem')

@section('description')
    Sistem Foresight Radar sedang dalam proses pemeliharaan rutin atau pembaharuan versi. Layanan akan segera aktif kembali. Silakan coba beberapa saat lagi.
@endsection

@section('actions')
    <x-error-button href="javascript:location.reload()">Coba Muat Ulang</x-error-button>
@endsection
