@foreach($bankRows->whereNull('ledger_line_id') as $bankRow)
    @php
        $matches = $bankCandidates->filter(fn($line) =>
            (int)$line->account_id === (int)$bankRow->account_id &&
            (int)$line->debit - (int)$line->credit === (int)$bankRow->amount &&
            abs(\Carbon\Carbon::parse($line->date)->diffInDays(\Carbon\Carbon::parse($bankRow->date), false)) <= 7
        );
        $counterparts = $bankRow->amount < 0 ? ['6101'=>'Biaya administrasi bank'] : ['4104'=>'Pendapatan bunga bank','4103'=>'Pendapatan lain'];
    @endphp
    @include('business.form',[
        'title'=>'Cocokkan transaksi ' . $bankRow->reference,
        'action'=>'bank-match', 'dialogId'=>'bank-match-'.$bankRow->id, 'hideTrigger'=>true,
        'fields'=>[['id','ID','hidden',[],$bankRow->id],['line_id','Jurnal','select',$matches->mapWithKeys(fn($line)=>[$line->id=>$line->reference.' - '.$line->date])->all()]],
        'button'=>'Cocokkan',
    ])
    @include('business.form',[
        'title'=>'Bukukan transaksi ' . $bankRow->reference,
        'action'=>'bank-adjust', 'dialogId'=>'bank-adjust-'.$bankRow->id, 'hideTrigger'=>true,
        'fields'=>[['id','ID','hidden',[],$bankRow->id],['counterpart','Akun','select',$counterparts]],
        'button'=>'Bukukan & cocokkan',
    ])
@endforeach
