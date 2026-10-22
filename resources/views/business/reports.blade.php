<div class="nav-tabs-custom">
    <ul class="nav nav-tabs" role="tablist">
        <li class="active"><a href="#tab-journal" data-toggle="tab" role="tab">Jurnal</a></li>
        @if($module === 'kas')
        <li><a href="#tab-balances" data-toggle="tab" role="tab">Saldo Rekening</a></li>
        <li><a href="#tab-reconciliation" data-toggle="tab" role="tab">Pencocokan Saldo</a></li>
        @else
        <li><a href="#tab-periods" data-toggle="tab" role="tab">Periode Bulanan</a></li>
        <li><a href="#tab-years" data-toggle="tab" role="tab">Tutup Tahun</a></li>
        @endif
    </ul>
    <div class="tab-content">
        <div class="tab-pane active" id="tab-journal" role="tabpanel">@include('business.journal')</div>
        @if($module === 'kas')
        <div class="tab-pane" id="tab-balances" role="tabpanel">
            <div class="table-responsive"><table class="table table-striped table-bordered business-static-table"><thead><tr><th>Akun</th><th>Saldo per {{ \Carbon\Carbon::parse($end)->format('d/m/Y') }}</th></tr></thead><tbody>
            @foreach($balances->where('cash_account',true) as $balance)<tr><td>{{ $balance->code }} - {{ $balance->name }}</td><td>{{ format_uang($balance->balance) }}</td></tr>@endforeach
            </tbody></table></div>
        </div>
        <div class="tab-pane" id="tab-reconciliation" role="tabpanel">@include('components.data-table',['tableKey'=>'reconcile_page'])</div>
        @else
        <div class="tab-pane" id="tab-periods" role="tabpanel">
            <div class="table-responsive"><table class="table table-striped table-bordered business-static-table"><thead><tr><th>Bulan</th><th>Status</th></tr></thead><tbody>
            @foreach($periods as $period)<tr><td data-order="{{ $period->month }}">{{ \Carbon\Carbon::parse($period->month)->format('m/Y') }}</td><td><span class="label {{ $period->closed?'label-success':'label-warning' }}">{{ $period->closed?'Ditutup':'Terbuka' }}</span></td></tr>@endforeach
            </tbody></table></div>
        </div>
        <div class="tab-pane" id="tab-years" role="tabpanel">
            <div class="table-responsive"><table class="table table-striped table-bordered business-static-table"><thead><tr><th>Tahun</th><th>Laba / Rugi</th><th>ID Jurnal</th></tr></thead><tbody>
            @foreach($years as $year)<tr><td>{{ $year->year }}</td><td>{{ format_uang($year->profit) }}</td><td>{{ $year->entry_id ?? '-' }}</td></tr>@endforeach
            </tbody></table></div>
        </div>
        @endif
    </div>
</div>
