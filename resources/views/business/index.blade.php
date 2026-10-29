@extends('layouts.master')
@php
    $meta = config('business.' . $module);
    $financialTitles = ['profit'=>'Laporan Laba Rugi','balance'=>'Neraca','cash'=>'Laporan Arus Kas','tax'=>'Laporan PPN'];
    $pageTitle = $financialReport ? $financialTitles[$financialReport] : $meta['title'];
    $sectionTitle = $financialReport ? 'Laporan' : ($module === 'rekening-bank' ? 'Master' : (in_array($module,['stok','retur']) ? 'Persediaan' : ($module === 'piutang-servis' ? 'Servis' : (in_array($module,['aset','akrual','rekonsiliasi','pajak','akuntansi']) ? 'Akuntansi' : 'Keuangan'))));
    $cashOptions = $accounts->where('cash_account',true)->mapWithKeys(fn($a) => [$a->code => $a->name])->all();
    $productOptions = $products->mapWithKeys(fn($p) => [$p->id_produk => $p->nama_produk . ' (stok ' . $p->stok . ')'])->all();
    $dateField = ['date','Tanggal','date'];
    $cashField = ['cash','Akun kas / bank','select',$cashOptions];
    $amountField = ['amount','Nominal (Rp)','number'];
    $descriptionField = ['description','Keterangan','text'];
@endphp
@section('title', $pageTitle)
@push('css')
<link rel="stylesheet" href="{{ asset('css/galaxy-ui.css') }}?v=11">
@endpush
@section('breadcrumb')
    @parent
    <li>{{ $sectionTitle }}</li><li class="active">{{ $pageTitle }}</li>
@endsection
@section('content')
<div class="row galaxy-business" data-source="{{ route('business.index',$module) }}" data-restore-action="{{ $errors->any() ? old('_action') : '' }}" data-restore-id="{{ old('id') }}"><div class="col-lg-12">
@if ($errors->any())<div class="alert alert-danger" role="alert"><strong>Data belum disimpan.</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
    @include('business.filters')
    <div class="box business-main">
        @unless($financialReport)
        <div class="box-header with-border"><div class="toolbar-inline">@include('business.actions')</div></div>
        @endunless
        @if ($module === 'rekening-bank' && (session('import_errors') || session('success')))
        <div class="box-body" style="padding-bottom: 0;">
            @if (session('import_errors'))
            <div class="alert alert-danger" style="margin-bottom: 0;"><strong>Import dibatalkan, tidak ada rekening bank yang disimpan.</strong> Perbaiki {{ session('import_error_total') }} baris berikut lalu unggah ulang:
                <ul>@foreach (session('import_errors') as $error)<li>{{ $error }}</li>@endforeach</ul>
                @if (session('import_error_total') > count(session('import_errors')))<small>Menampilkan {{ count(session('import_errors')) }} dari {{ session('import_error_total') }} error.</small>@endif
            </div>
            @else
            <div class="alert alert-success" style="margin-bottom: 0;">{{ session('success') }}</div>
            @endif
        </div>
        @endif
        <div class="box-body">
        @if($financialReport && $financialReport !== 'tax')
            @include('business.financial-report')
        @elseif (!$financialReport && in_array($module,['kas','akuntansi']))
            @include('business.reports')
        @else
            @include('business.lists')
        @endif
        </div>
    </div>
</div>
<div class="business-dialogs"></div>
</div>
@endsection
@push('scripts')
<script src="{{ asset('js/galaxy-ui.js') }}?v=13"></script>
@if ($errors->any() && old('_action'))
<script>$(function(){
    var action = @json(old('_action')), id = @json(old('id'));
    $('.galaxy-business .modal').filter(function () {
        var form = $(this).find('form');
        return form.find('[name="_action"]').val() === action && (!id || form.find('[name="id"]').val() === String(id));
    }).first().modal('show');
});</script>
@endif
@endpush
