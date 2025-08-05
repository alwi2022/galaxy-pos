<!-- resources/views/servis/track-lite.blade.php -->
@extends('layouts.track')

@section('title', 'Cek Status Servis')

@section('content')
    <div class="box">
        <h3 class="text-center">Status Servis</h3>
        <ul>
@foreach ($servis->logs as $log)
    <li>{{ $log->created_at->format('d/m/Y H:i') }} - {{ ucfirst($log->status) }} oleh <strong>{{ $log->user->name }}</strong></li>
@endforeach
</ul>
        <hr>
        <p><strong>Kode Servis:</strong> {{ $servis->kode_servis }}</p>
        <p><strong>Nama:</strong> {{ $servis->nama_pelanggan }}</p>
        <p><strong>Barang:</strong> {{ $servis->tipe_barang }}</p>
        <p><strong>Kerusakan:</strong> {{ $servis->kerusakan }}</p>
        <p><strong>Status:</strong> <span class="label label-info">{{ strtoupper($servis->status) }}</span></p>
        <p><strong>Update Terakhir:</strong> {{ tanggal_indonesia($servis->updated_at, true) }}</p>
    </div>
@endsection
