<x-filter-panel>
    <form method="get" class="business-filters toolbar-inline">
        <input type="hidden" name="tab" value="{{ request('tab') }}">
        @if($module === 'pajak')
        <label for="filter-month" class="control-label">Masa Pajak</label>
        <input type="month" class="form-control input-sm" id="filter-month" name="month" value="{{ $taxMonth }}" required>
        @elseif($module !== 'rekening-bank')
        <label for="filter-start" class="control-label">Tanggal Awal</label>
        <input class="form-control input-sm" type="date" id="filter-start" name="start" value="{{ $start }}" required>
        <label for="filter-end" class="control-label">Tanggal Akhir</label>
        <input class="form-control input-sm" type="date" id="filter-end" name="end" value="{{ $end }}" required>
        @endif
        @if(config('business.' . $module . '.statuses') && (!$financialReport || $financialReport === 'tax'))
        <span class="filter-group business-status-filter">
            <label for="filter-status" class="control-label">Status</label>
            <select class="form-control input-sm" name="status" id="filter-status"><option value="">Semua</option>@foreach(config('business.' . $module . '.statuses') as $value=>$label)<option value="{{ $value }}" @if(request('status') === $value) selected @endif>{{ $label }}</option>@endforeach</select>
        </span>
        @endif
        @if($module === 'stok')
        <label for="filter-product" class="control-label">Produk</label>
        <select class="form-control input-sm" name="product" id="filter-product"><option value="">Semua Produk</option>@foreach($products as $product)<option value="{{ $product->id_produk }}" @if(request('product') == $product->id_produk) selected @endif>{{ $product->nama_produk }}</option>@endforeach</select>
        @elseif(in_array($module,['kas','akuntansi','rekonsiliasi']) && !$financialReport)
        <span class="filter-group business-account-filter">
            <label for="filter-account" class="control-label">Akun</label>
            <select class="form-control input-sm" name="account" id="filter-account"><option value="">Semua Akun</option>@foreach($accounts->when($module==='rekonsiliasi',fn($items)=>$items->where('cash_account',true)) as $account)<option value="{{ $account->code }}" @if(request('account') == $account->code) selected @endif>{{ $account->code }} - {{ $account->name }}</option>@endforeach</select>
        </span>
        @endif
        <button class="btn btn-primary btn-sm btn-flat" type="submit"><i class="fa fa-filter"></i> Terapkan</button>
        <a class="btn btn-default btn-sm btn-flat" href="{{ $financialReport ? route('laporan.keuangan',$financialReport) : route('business.index',$module) }}"><i class="fa fa-refresh"></i> Reset</a>
        @if(!$financialReport && in_array($module,['kas','akuntansi']))<a class="btn btn-success btn-sm btn-flat" href="{{ route('business.export',['start'=>$start,'end'=>$end]) }}"><i class="fa fa-file-excel-o"></i> Export CSV</a>@endif
    </form>
</x-filter-panel>
