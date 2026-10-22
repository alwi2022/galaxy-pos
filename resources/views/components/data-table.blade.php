@php $table = config('business_tables.' . $tableKey); @endphp
<div class="table-responsive">
    <table id="table-{{ $tableKey }}" class="table table-striped table-bordered business-datatable" data-table-key="{{ $tableKey }}" style="width:100%">
        <caption class="sr-only">{{ $table['title'] }}</caption>
        <thead><tr><th width="5%" data-orderable="false">No</th>
            @foreach($table['columns'] as $column)
            <th data-orderable="{{ $column['sort'] ? 'true' : 'false' }}">{{ $column['label'] }}</th>
            @endforeach
        </tr></thead>
        <tbody></tbody>
    </table>
</div>
