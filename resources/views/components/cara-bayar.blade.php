@props(['name' => 'metode_pembayaran', 'id' => 'metode_pembayaran', 'selected' => 'tunai', 'except' => [], 'labelClass' => 'col-lg-3', 'fieldClass' => 'col-lg-7'])
@php
    $rows = app(\App\Services\PaymentMethodService::class)->all((int) auth()->user()->id_cabang)
        ->filter(fn ($row) => ($row->active || $row->code === $selected) && ! in_array($row->code, $except, true));
    $types = collect(\App\Services\PaymentMethodService::TYPES)->only($rows->pluck('type')->unique()->all());
@endphp
<div class="form-group row">
    <label for="{{ $id }}-type" class="{{ $labelClass }} control-label">Cara Bayar</label>
    <div class="{{ $fieldClass }}">
        <select id="{{ $id }}-type" class="form-control js-cara-bayar" data-target="{{ $id }}">
            @foreach ($types as $type => $label)<option value="{{ $type }}">{{ $label }}</option>@endforeach
        </select>
    </div>
</div>
<div class="form-group row js-cara-bayar-bank" data-for="{{ $id }}">
    <label for="{{ $id }}" class="{{ $labelClass }} control-label">Rekening Bank</label>
    <div class="{{ $fieldClass }}">
        <select name="{{ $name }}" id="{{ $id }}" class="form-control" data-cara-bayar required>
            @foreach ($rows as $row)<option value="{{ $row->code }}" data-type="{{ $row->type }}" @if ((string) $selected === $row->code) selected @endif>{{ $row->name }}</option>@endforeach
        </select>
        {{ $slot }}
    </div>
</div>
@once
@push('scripts')
<script>
(function ($) {
    'use strict';
    function show(select, type) {
        $(select).find('option').each(function () { this.hidden = this.dataset.type !== type; });
        $('.js-cara-bayar-bank[data-for="' + select.id + '"]').toggle(type === 'bank');
    }
    $(document).on('change', '.js-cara-bayar', function () {
        var select = document.getElementById(this.dataset.target), type = this.value;
        show(select, type);
        if ($(select).find('option:selected').data('type') !== type) {
            $(select).val($(select).find('option[data-type="' + type + '"]').first().val()).trigger('change');
        }
    });
    function sync() {
        var type = $(this).find('option:selected').data('type');
        $('.js-cara-bayar[data-target="' + this.id + '"]').val(type);
        show(this, type);
    }
    $(document).on('change', 'select[data-cara-bayar]', sync);
    $(document).on('reset', 'form', function () {
        var form = this;
        setTimeout(function () { $(form).find('select[data-cara-bayar]').each(sync); });
    });
    $(function () { $('select[data-cara-bayar]').each(sync); });
})(jQuery);
</script>
@endpush
@endonce
