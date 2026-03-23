@extends('layouts.master')

@section('title')
    Pembayaran Hutang Piutang
@endsection

@push('css')
<style>
    .summary-table td {
        padding: 4px 8px;
        vertical-align: top;
    }

    .history-wrapper {
        max-height: 260px;
        overflow-y: auto;
    }
</style>
@endpush

@section('breadcrumb')
    @parent
    <li class="active">Pembayaran Hutang Piutang</li>
@endsection

@section('content')
<div class="row">
    <div class="col-lg-12">
        <div class="nav-tabs-custom">
            <ul class="nav nav-tabs">
                <li class="{{ $tab === 'piutang' ? 'active' : '' }}">
                    <a href="#tab-piutang" data-toggle="tab">Piutang Penjualan</a>
                </li>
                @if ($canManageHutang)
                <li class="{{ $tab === 'hutang' ? 'active' : '' }}">
                    <a href="#tab-hutang" data-toggle="tab">Hutang Pembelian</a>
                </li>
                @endif
            </ul>
            <div class="tab-content">
                <div class="tab-pane {{ $tab === 'piutang' ? 'active' : '' }}" id="tab-piutang">
                    <div class="table-responsive">
                        <table class="table table-striped table-bordered table-piutang">
                            <thead>
                                <th width="5%">No</th>
                                <th>Tanggal</th>
                                <th>Pelanggan</th>
                                <th>Tagihan</th>
                                <th>Dibayar</th>
                                <th>Sisa</th>
                                <th>Jatuh Tempo</th>
                                <th>Status</th>
                                <th width="12%"><i class="fa fa-cog"></i></th>
                            </thead>
                        </table>
                    </div>
                </div>
                @if ($canManageHutang)
                <div class="tab-pane {{ $tab === 'hutang' ? 'active' : '' }}" id="tab-hutang">
                    <div class="table-responsive">
                        <table class="table table-striped table-bordered table-hutang">
                            <thead>
                                <th width="5%">No</th>
                                <th>Tanggal</th>
                                <th>Supplier</th>
                                <th>Tagihan</th>
                                <th>Dibayar</th>
                                <th>Sisa</th>
                                <th>Jatuh Tempo</th>
                                <th>Status</th>
                                <th width="12%"><i class="fa fa-cog"></i></th>
                            </thead>
                        </table>
                    </div>
                </div>
                @endif
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="modal-payment" tabindex="-1" role="dialog" aria-labelledby="modal-payment">
    <div class="modal-dialog modal-lg" role="document">
        <form action="" method="post" class="form-horizontal" id="form-payment">
            @csrf
            <div class="modal-content">
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span
                            aria-hidden="true">&times;</span></button>
                    <h4 class="modal-title">Pembayaran</h4>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-lg-6">
                            <table class="summary-table">
                                <tr>
                                    <td>No. Transaksi</td>
                                    <td>: <strong id="summary-nomor">-</strong></td>
                                </tr>
                                <tr>
                                    <td>Pihak</td>
                                    <td>: <strong id="summary-pihak">-</strong></td>
                                </tr>
                                <tr>
                                    <td>Tanggal</td>
                                    <td>: <span id="summary-tanggal">-</span></td>
                                </tr>
                                <tr>
                                    <td>Tagihan</td>
                                    <td>: Rp. <span id="summary-tagihan">0</span></td>
                                </tr>
                                <tr>
                                    <td>Dibayar</td>
                                    <td>: Rp. <span id="summary-dibayar">0</span></td>
                                </tr>
                                <tr>
                                    <td>Sisa</td>
                                    <td>: Rp. <strong id="summary-sisa">0</strong></td>
                                </tr>
                                <tr>
                                    <td>Jatuh Tempo</td>
                                    <td>: <span id="summary-jatuh-tempo">-</span></td>
                                </tr>
                                <tr>
                                    <td>Status</td>
                                    <td>: <span id="summary-status">-</span></td>
                                </tr>
                            </table>
                        </div>
                        <div class="col-lg-6">
                            <div class="form-group row">
                                <label for="nominal" class="col-lg-4 control-label">Nominal</label>
                                <div class="col-lg-8">
                                    <input type="number" name="nominal" id="nominal" class="form-control" min="1" required>
                                </div>
                            </div>
                            <div class="form-group row">
                                <label for="metode_pembayaran" class="col-lg-4 control-label">Metode</label>
                                <div class="col-lg-8">
                                    <select name="metode_pembayaran" id="metode_pembayaran" class="form-control" required>
                                        @foreach (collect(daftar_metode_pembayaran())->except(['qris']) as $value => $label)
                                        <option value="{{ $value }}">{{ $label }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                            <div class="form-group row">
                                <label for="keterangan" class="col-lg-4 control-label">Keterangan</label>
                                <div class="col-lg-8">
                                    <textarea name="keterangan" id="keterangan" rows="4" class="form-control"></textarea>
                                </div>
                            </div>
                        </div>
                    </div>

                    <hr>

                    <h4>Riwayat Pembayaran</h4>
                    <div class="history-wrapper">
                        <table class="table table-striped table-bordered">
                            <thead>
                                <th width="20%">Tanggal</th>
                                <th width="18%">Metode</th>
                                <th width="20%">Nominal</th>
                                <th>Keterangan</th>
                            </thead>
                            <tbody id="history-body">
                                <tr>
                                    <td colspan="4" class="text-center text-muted">Belum ada riwayat pembayaran.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-sm btn-flat btn-primary"><i class="fa fa-save"></i> Simpan Pembayaran</button>
                    <button type="button" class="btn btn-sm btn-flat btn-warning" data-dismiss="modal"><i class="fa fa-arrow-circle-left"></i> Tutup</button>
                </div>
            </div>
        </form>
    </div>
</div>
@endsection

@push('scripts')
<script>
    let piutangTable;
    let hutangTable;
    let activeType = 'piutang';
    let activeId = null;

    $(function () {
        piutangTable = $('.table-piutang').DataTable({
            responsive: true,
            processing: true,
            serverSide: true,
            autoWidth: false,
            ajax: {
                url: '{{ route('pembayaran.piutang.data') }}',
            },
            columns: [
                {data: 'DT_RowIndex', searchable: false, sortable: false},
                {data: 'tanggal'},
                {data: 'pelanggan'},
                {data: 'tagihan'},
                {data: 'dibayar'},
                {data: 'sisa'},
                {data: 'jatuh_tempo'},
                {data: 'status_pembayaran'},
                {data: 'aksi', searchable: false, sortable: false},
            ]
        });

        @if ($canManageHutang)
        hutangTable = $('.table-hutang').DataTable({
            responsive: true,
            processing: true,
            serverSide: true,
            autoWidth: false,
            ajax: {
                url: '{{ route('pembayaran.hutang.data') }}',
            },
            columns: [
                {data: 'DT_RowIndex', searchable: false, sortable: false},
                {data: 'tanggal'},
                {data: 'supplier'},
                {data: 'tagihan'},
                {data: 'dibayar'},
                {data: 'sisa'},
                {data: 'jatuh_tempo'},
                {data: 'status_pembayaran'},
                {data: 'aksi', searchable: false, sortable: false},
            ]
        });
        @endif

        $('#form-payment').on('submit', function (e) {
            e.preventDefault();

            $.post($(this).attr('action'), $(this).serialize())
                .done((response) => {
                    Swal.fire({
                        icon: 'success',
                        title: 'Berhasil!',
                        text: response.message,
                        timer: 1500,
                        showConfirmButton: false
                    });

                    reloadTables();
                    openPaymentModal(activeType, activeId);
                })
                .fail((error) => {
                    const message = error.responseJSON?.message
                        || Object.values(error.responseJSON?.errors || {})[0]?.[0]
                        || 'Tidak dapat menyimpan pembayaran.';

                    Swal.fire({
                        icon: 'error',
                        title: 'Gagal!',
                        text: message
                    });
                });
        });
    });

    function openPaymentModal(type, id) {
        activeType = type;
        activeId = id;

        const url = type === 'piutang'
            ? `{{ url('/pembayaran/piutang') }}/${id}`
            : `{{ url('/pembayaran/hutang') }}/${id}`;

        $.get(url)
            .done((response) => {
                $('#modal-payment').modal('show');
                $('#modal-payment .modal-title').text(response.judul);
                $('#form-payment').attr('action', response.route);
                $('#nominal').val('').attr('max', response.transaksi.sisa.replace(/\./g, ''));
                $('#keterangan').val('');

                $('#summary-nomor').text(response.transaksi.nomor);
                $('#summary-pihak').text(response.transaksi.pihak);
                $('#summary-tanggal').text(response.transaksi.tanggal);
                $('#summary-tagihan').text(response.transaksi.tagihan);
                $('#summary-dibayar').text(response.transaksi.dibayar);
                $('#summary-sisa').text(response.transaksi.sisa);
                $('#summary-jatuh-tempo').text(response.transaksi.jatuh_tempo);
                $('#summary-status').text(response.transaksi.status);

                renderHistory(response.history);
            })
            .fail(() => {
                Swal.fire({
                    icon: 'error',
                    title: 'Gagal!',
                    text: 'Tidak dapat memuat data pembayaran.'
                });
            });
    }

    function renderHistory(rows) {
        const $body = $('#history-body');
        $body.empty();

        if (!rows.length) {
            $body.append(`
                <tr>
                    <td colspan="4" class="text-center text-muted">Belum ada riwayat pembayaran.</td>
                </tr>
            `);
            return;
        }

        rows.forEach((row) => {
            $body.append(`
                <tr>
                    <td>${row.tanggal ?? '-'}</td>
                    <td>${row.metode}</td>
                    <td>Rp. ${row.nominal}</td>
                    <td>${row.keterangan}</td>
                </tr>
            `);
        });
    }

    function reloadTables() {
        if (piutangTable) {
            piutangTable.ajax.reload(null, false);
        }

        if (hutangTable) {
            hutangTable.ajax.reload(null, false);
        }
    }
</script>
@endpush
