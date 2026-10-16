@extends('errors.layout')

@section('title', '404 — Halaman Tidak Ditemukan')
@section('code', '404')
@section('tone', 'danger')
@section('badge')
    404 &bull; NOT FOUND
@endsection
@section('heading', 'Halaman Tidak Ditemukan')

@section('description')
    Halaman atau tautan yang Anda tuju tidak ditemukan atau telah dipindahkan. Pastikan alamat URL yang dimasukkan sudah benar.
@endsection

@section('actions')
    @auth
        <x-error-button :href="route('dashboard.')">Ke Dashboard</x-error-button>
    @else
        <x-error-button :href="url('/')">Halaman Utama</x-error-button>
    @endauth
    <x-error-button href="javascript:history.back()" variant="outline">Halaman Sebelumnya</x-error-button>
@endsection
