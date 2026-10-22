@php
    $cashEnd = (int)$balances->where('cash_account',true)->sum('balance');
    $cashStart = $cashEnd - (int)$periodBalances->where('cash_account',true)->sum('balance');
    $assetTotal = (int)$balances->whereIn('type',['asset','contra_asset'])->sum('balance');
    $liabilityEquity = -(int)$balances->whereIn('type',['liability','equity','contra_equity'])->sum('balance')+$cumulativeProfit;
@endphp
<div class="table-responsive">
<table class="table table-striped table-bordered business-static-table">
    <thead><tr><th>Akun / Keterangan</th><th>Kelompok</th><th>Nilai (Rp)</th></tr></thead>
    <tbody>
    @if($financialReport === 'profit')
        @foreach(['income'=>'Pendapatan','expense'=>'HPP & Beban'] as $type=>$label)
            @foreach($periodBalances->where('type',$type) as $balance)<tr><td>{{ $balance->code }} - {{ $balance->name }}</td><td>{{ $label }}</td><td data-order="{{ $type==='income'?-$balance->balance:$balance->balance }}">{{ format_uang($type==='income'?-$balance->balance:$balance->balance) }}</td></tr>@endforeach
        @endforeach
    @elseif($financialReport === 'balance')
        @foreach($balances->whereIn('type',['asset','contra_asset','liability','equity','contra_equity']) as $balance)
        <tr><td>{{ $balance->code }} - {{ $balance->name }}</td><td>{{ ['asset'=>'Aset','contra_asset'=>'Akumulasi Penyusutan','liability'=>'Kewajiban','equity'=>'Ekuitas','contra_equity'=>'Pengambilan Pemilik'][$balance->type] }}</td><td data-order="{{ in_array($balance->type,['liability','equity','contra_equity'])?-$balance->balance:$balance->balance }}">{{ format_uang(in_array($balance->type,['liability','equity','contra_equity'])?-$balance->balance:$balance->balance) }}</td></tr>
        @endforeach
        <tr><td>Laba berjalan yang belum ditutup</td><td>Ekuitas</td><td data-order="{{ $cumulativeProfit }}">{{ format_uang($cumulativeProfit) }}</td></tr>
    @else
        <tr><td>Saldo awal</td><td>Kas & Bank</td><td data-order="{{ $cashStart }}">{{ format_uang($cashStart) }}</td></tr>
        @foreach($cashFlows as $category=>$amount)<tr><td>{{ $category }}</td><td>Arus Kas</td><td data-order="{{ $amount }}">{{ format_uang($amount) }}</td></tr>@endforeach
    @endif
    </tbody>
    <tfoot>
    @if($financialReport === 'profit')
        <tr><th>Laba / Rugi</th><th></th><th>{{ format_uang($profit) }}</th></tr>
    @elseif($financialReport === 'balance')
        <tr><th>Total Aset Bersih</th><th></th><th>{{ format_uang($assetTotal) }}</th></tr>
        <tr><th>Total Kewajiban & Ekuitas</th><th></th><th>{{ format_uang($liabilityEquity) }}</th></tr>
        <tr><th>Selisih Neraca</th><th></th><th>{{ format_uang($assetTotal-$liabilityEquity) }}</th></tr>
    @else
        <tr><th>Perubahan Kas Bersih</th><th></th><th>{{ format_uang(array_sum($cashFlows)) }}</th></tr>
        <tr><th>Saldo Akhir</th><th></th><th>{{ format_uang($cashEnd) }}</th></tr>
    @endif
    </tfoot>
</table>
</div>
