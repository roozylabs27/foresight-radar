@extends('errors.layout')

@section('title', '419 — Sesi Kedaluwarsa')
@section('code', '419')
@section('tone', 'warning')
@section('badge')
    419 &bull; PAGE EXPIRED
@endsection
@section('heading', 'Sesi Kedaluwarsa')

@section('description')
    Sesi interaksi atau token keamanan form Anda telah kedaluwarsa karena tidak ada aktivitas dalam waktu lama. Silakan muat ulang halaman.
@endsection

@section('actions')
    <x-error-button href="javascript:location.reload()">Muat Ulang Halaman</x-error-button>
    <x-error-button :href="route('login')" variant="outline">Masuk Kembali</x-error-button>
@endsection
