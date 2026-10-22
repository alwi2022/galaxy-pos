@php
    $actionIcons = ['stock' => 'cubes', 'money' => 'money', 'account' => 'bank', 'reconcile' => 'check-square-o', 'reverse' => 'undo', 'loan' => 'handshake-o', 'repay' => 'credit-card', 'asset' => 'desktop', 'dispose' => 'sign-out', 'defer' => 'calendar', 'close' => 'calendar-check-o', 'year-close' => 'lock', 'journal' => 'book', 'service-pay' => 'credit-card', 'service-cancel-payment' => 'undo', 'bank-import' => 'upload', 'tax-profile' => 'building-o', 'tax-invoice' => 'file-text-o', 'tax-prepare' => 'calculator', 'tax-pay' => 'credit-card', 'tax-filed' => 'check-square-o'];
    $restore = old('_action') === $action && (!isset($dialogId) || $dialogId === $action . '-' . old('id'));
    $modalId = $dialogId ?? 'form-' . $action;
    $unavailable = collect($fields)->contains(fn($field) => $field[2] === 'select' && empty($field[3]));
@endphp
@unless($hideTrigger ?? false)
<button type="button" class="btn btn-xs btn-flat {{ ($primary ?? false) ? 'btn-success' : 'btn-default' }}" data-toggle="modal" data-target="#{{ $modalId }}"><i class="fa fa-{{ $actionIcons[$action] ?? 'plus' }}" aria-hidden="true"></i> {{ $title }}</button>
@endunless
<div class="modal fade" id="{{ $modalId }}" tabindex="-1" role="dialog" aria-labelledby="title-{{ $modalId }}">
    <div class="modal-dialog modal-lg" role="document"><div class="modal-content">
        <form method="post" action="{{ route('business.store', $action) }}" class="business-form form-horizontal" enctype="multipart/form-data">
            @csrf
            <input type="hidden" name="_action" value="{{ $action }}">
            <div class="modal-header"><button type="button" class="close" data-dismiss="modal" aria-label="Tutup formulir"><span aria-hidden="true">&times;</span></button><h4 class="modal-title" id="title-{{ $modalId }}">{{ $title }}</h4></div>
            <div class="modal-body">
                @foreach ($fields as $field)
                    @php
                        [$name, $label, $type] = $field;
                        $options = $field[3] ?? [];
                        $default = $field[4] ?? (in_array($type, ['date', 'future-date']) ? now()->toDateString() : '');
                        $value = $restore ? old($name, $default) : $default;
                        $inputType = $type === 'future-date' ? 'date' : $type;
                        $fieldId = ($dialogId ?? $action) . '-' . $name;
                    @endphp
                    @if ($type === 'hidden')
                        <input type="hidden" name="{{ $name }}" value="{{ $value }}">
                    @else
                    <div class="form-group row">
                        <label for="{{ $fieldId }}" class="col-lg-3 control-label">{{ $label }}</label>
                        <div class="col-lg-7">
                        @if ($type === 'select')
                            <select class="form-control" id="{{ $fieldId }}" name="{{ $name }}" required>
                                @if (!$options)<option value="">Belum ada pilihan tersedia</option>@endif
                                @foreach ($options as $key => $text)<option value="{{ $key }}" @if ((string) $value === (string) $key) selected @endif>{{ $text }}</option>@endforeach
                            </select>
                        @elseif ($type === 'textarea')
                            <textarea class="form-control" id="{{ $fieldId }}" name="{{ $name }}" rows="3" required>{{ $value }}</textarea>
                        @else
                            <input class="form-control" id="{{ $fieldId }}" name="{{ $name }}" type="{{ $inputType }}" @if ($type !== 'file') value="{{ $value }}" @else accept=".csv,text/csv" @endif required @if ($type === 'number') min="0" step="1" @endif @if ($type === 'date') max="{{ now()->toDateString() }}" @endif>
                        @endif
                        </div>
                    </div>
                    @endif
                @endforeach
                @isset($hint)<div class="form-group row"><div class="col-lg-7 col-lg-offset-3"><p class="help-block">{{ $hint }}</p></div></div>@endisset
            </div>
            <div class="modal-footer"><button class="btn btn-primary btn-sm btn-flat" type="submit" @if($unavailable) disabled @endif><i class="fa fa-save" aria-hidden="true"></i> {{ $button ?? 'Simpan' }}</button><button type="button" class="btn btn-warning btn-sm btn-flat" data-dismiss="modal"><i class="fa fa-arrow-circle-left" aria-hidden="true"></i> Batal</button></div>
        </form>
    </div></div>
</div>
