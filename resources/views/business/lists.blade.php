@php
    $tabs = collect(config('business_tables'))->filter(fn($table) => $table['module'] === $module);
    $firstKey = $tabs->keys()->first();
@endphp
@if($tabs->count() > 1)
<div class="nav-tabs-custom">
    <ul class="nav nav-tabs" role="tablist">
        @foreach($tabs as $key => $table)
        <li class="{{ $key === $firstKey ? 'active' : '' }}"><a href="#tab-{{ $key }}" data-toggle="tab" role="tab">{{ $table['title'] }}</a></li>
        @endforeach
    </ul>
    <div class="tab-content">
        @foreach($tabs as $key => $table)
        <div class="tab-pane {{ $key === $firstKey ? 'active' : '' }}" id="tab-{{ $key }}" role="tabpanel">
            @include('components.data-table',['tableKey'=>$key])
        </div>
        @endforeach
    </div>
</div>
@elseif($tabs->count() === 1)
@include('components.data-table',['tableKey'=>$firstKey])
@endif
