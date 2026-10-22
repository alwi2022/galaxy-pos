@php $value=data_get($row,$column['field']??''); $format=$column['format']??'text'; @endphp
@if($format === 'actions')
    @include('business.row-actions',['actionType'=>$rowAction])
@elseif(in_array($format,['money','number']))
    {{ format_uang($value??0) }}
@elseif($format === 'quantity')
    {{ $value>0?'+':'' }}{{ format_uang($value??0) }}
@elseif(in_array($format,['date','datetime','month']))
    {{ $value ? \Carbon\Carbon::parse($value)->format($format==='datetime'?'d/m/Y H:i':($format==='month'?'m/Y':'d/m/Y')) : '-' }}
@elseif($format === 'payment')
    <span class="label {{ class_status_pembayaran($value) }}">{{ label_status_pembayaran($value) }}</span>
@elseif($format === 'payment-state')
    <span class="label {{ $row->cancelled_at?'label-default':'label-success' }}">{{ $row->cancelled_at?'Dibatalkan':'Aktif' }}</span>
@elseif($format === 'asset-value')
    {{ format_uang($row->cost-$row->depreciated) }}
@elseif($format === 'deferral-balance')
    {{ format_uang($row->amount-$row->recognized) }}
@elseif($format === 'enum')
    {{ $column['options'][$value]??$value }}
@elseif($format === 'matched')
    <span class="label {{ $value?'label-success':'label-warning' }}">{{ $value?'Sudah cocok':'Belum cocok' }}</span>
@elseif($format === 'retur-condition')
    {{ $row->type==='pembelian'?'Kembali ke supplier':($row->restock?'Layak jual':'Rusak') }}
@elseif($format === 'reference')
    <span title="{{ $value }}">{{ preg_replace('/-[a-f0-9]{8}-[a-f0-9-]{27}$/i','',(string)$value) }}</span>
@else
    {{ $value??'-' }}
@endif
