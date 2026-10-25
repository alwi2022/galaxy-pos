@if($actionType === 'service')
    @if($row->sisa>0)<button class="btn btn-primary btn-xs btn-flat" type="button" data-toggle="modal" data-target="#form-service-pay" data-service-id="{{ $row->id_servis }}" data-service-amount="{{ $row->sisa }}"><i class="fa fa-credit-card" aria-hidden="true"></i> Bayar</button>@else<span class="text-muted">-</span>@endif
@elseif($actionType === 'bank-account')
    <button class="btn btn-info btn-xs btn-flat" type="button" data-toggle="modal" data-target="#form-bank-account-update" data-bank-account="{{ json_encode(['id'=>$row->id,'bank_name'=>$row->bank_name,'account_number'=>$row->account_number,'account_holder'=>$row->account_holder,'address'=>$row->address,'city'=>$row->city,'phone'=>$row->phone,'active'=>(int)$row->active]) }}"><i class="fa fa-pencil" aria-hidden="true"></i> Ubah</button>
@elseif($actionType === 'tax')
    <div class="btn-group">@foreach(['csv'=>'file-excel-o','pdf'=>'file-pdf-o','xml'=>'file-code-o'] as $format=>$icon)<a class="btn btn-default btn-xs btn-flat" href="{{ route('business.tax-export',[$row->id,$format]) }}" aria-label="Unduh {{ strtoupper($format) }} masa {{ substr($row->month,0,7) }} revisi {{ $row->revision }}"><i class="fa fa-{{ $icon }}" aria-hidden="true"></i> {{ strtoupper($format) }}</a>@endforeach</div>
@elseif($actionType === 'bank')
    @if($row->ledger_line_id)
        <form method="post" action="{{ route('business.store','bank-unmatch') }}" class="business-inline-form">@csrf<input type="hidden" name="id" value="{{ $row->id }}"><button class="btn btn-warning btn-xs btn-flat" type="submit"><i class="fa fa-unlink" aria-hidden="true"></i> Lepas pasangan</button></form>
    @else
        <div class="btn-group">
            <button class="btn btn-info btn-xs btn-flat" type="button" data-toggle="modal" data-target="#bank-match-{{ $row->id }}"><i class="fa fa-link" aria-hidden="true"></i> Cocokkan</button>
            <button class="btn btn-default btn-xs btn-flat" type="button" data-toggle="modal" data-target="#bank-adjust-{{ $row->id }}"><i class="fa fa-book" aria-hidden="true"></i> Bukukan</button>
        </div>
    @endif
@endif
