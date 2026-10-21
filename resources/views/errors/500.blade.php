@extends('errors.layout')

@section('title', '500 — Kesalahan Server')
@section('code', '500')
@section('tone', 'danger')
@section('badge')
    500 &bull; SERVER ERROR
@endsection
@section('heading', 'Kesalahan Server Internal')

@section('description')
    Terjadi kendala teknis internal pada sistem saat memproses permintaan Anda. Tim teknis telah diberi notifikasi mengenai kendala ini.
@endsection

@section('actions')
    <x-error-button :href="route('dashboard.')">Ke Dashboard</x-error-button>
    <x-error-button href="javascript:history.back()" variant="outline">Halaman Sebelumnya</x-error-button>
@endsection
