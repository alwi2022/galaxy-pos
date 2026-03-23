@extends('layouts.master')

@section('title')
    Laporan {{ tanggal_indonesia($tanggalAwal, false) }} s/d {{ tanggal_indonesia($tanggalAkhir, false) }}
@endsection

@push('css')
<style>
    .report-filter {
        padding: 16px;
        border: 1px solid #e5e7eb;
        border-radius: 6px;
        background: #fff;
    }

    .report-filter .toolbar-inline {
        margin-bottom: 10px;
    }

    .nav-tabs-custom > .nav-tabs > li > a {
        font-weight: 600;
    }
</style>
@endpush

@section('breadcrumb')
    @parent
    <li class="active">Laporan</li>
@endsection

@section('content')
<div class="row">
    <div class="col-lg-12">
        <div class="report-filter">
            <div class="toolbar-inline">
                <label for="tanggal_awal" class="control-label" style="margin-bottom: 0;">Tanggal Awal</label>
                <input type="date" id="tanggal_awal" class="form-control" value="{{ $tanggalAwal }}">
                <label for="tanggal_akhir" class="control-label" style="margin-bottom: 0;">Tanggal Akhir</label>
                <input type="date" id="tanggal_akhir" class="form-control" value="{{ $tanggalAkhir }}">
                <button type="button" onclick="applyPeriode()" class="btn btn-primary btn-flat"><i class="fa fa-filter"></i> Terapkan</button>
                <a href="{{ route('laporan.export_pdf', [$tanggalAwal, $tanggalAkhir]) }}" target="_blank" class="btn btn-default btn-flat"><i class="fa fa-file-pdf-o"></i> Export PDF Ringkasan</a>
                <a href="#" id="link-export-excel" class="btn btn-success btn-flat"><i class="fa fa-file-excel-o"></i> Export Excel Tab Aktif</a>
            </div>
            <small class="text-muted">Export Excel mengikuti tab laporan yang sedang aktif. Ringkasan PDF tetap tersedia untuk kebutuhan cetak cepat.</small>
        </div>
    </div>
</div>

<div class="row" style="margin-top: 16px;">
    <div class="col-lg-12">
        <div class="nav-tabs-custom">
            <ul class="nav nav-tabs">
                <li class="{{ $activeTab === 'ringkasan' ? 'active' : '' }}"><a href="#tab-ringkasan" data-toggle="tab" data-tab="ringkasan">Ringkasan Kas</a></li>
                <li class="{{ $activeTab === 'penjualan' ? 'active' : '' }}"><a href="#tab-penjualan" data-toggle="tab" data-tab="penjualan">Penjualan</a></li>
                <li class="{{ $activeTab === 'pembelian' ? 'active' : '' }}"><a href="#tab-pembelian" data-toggle="tab" data-tab="pembelian">Pembelian</a></li>
                <li class="{{ $activeTab === 'produk' ? 'active' : '' }}"><a href="#tab-produk" data-toggle="tab" data-tab="produk">Per Barang</a></li>
                <li class="{{ $activeTab === 'kategori' ? 'active' : '' }}"><a href="#tab-kategori" data-toggle="tab" data-tab="kategori">Per Kategori</a></li>
            </ul>
            <div class="tab-content">
                <div class="tab-pane {{ $activeTab === 'ringkasan' ? 'active' : '' }}" id="tab-ringkasan">
                    <div class="table-responsive">
                        <table class="table table-striped table-bordered table-ringkasan">
                            <thead>
                                <th width="5%">No</th>
                                <th>Tanggal</th>
                                <th>Penjualan Masuk</th>
                                <th>Pembelian Keluar</th>
                                <th>Pendapatan Lain</th>
                                <th>Servis</th>
                                <th>Biaya</th>
                                <th>Pendapatan Bersih</th>
                            </thead>
                        </table>
                    </div>
                </div>
                <div class="tab-pane {{ $activeTab === 'penjualan' ? 'active' : '' }}" id="tab-penjualan">
                    <div class="table-responsive">
                        <table class="table table-striped table-bordered table-penjualan-report">
                            <thead>
                                <th>Tanggal</th>
                                <th>No. Transaksi</th>
                                <th>Pelanggan</th>
                                <th>Total Item</th>
                                <th>Subtotal</th>
                                <th>Diskon</th>
                                <th>Diskon Nominal</th>
                                <th>DPP</th>
                                <th>PPN</th>
                                <th>PPN Nominal</th>
                                <th>Grand Total</th>
                                <th>Dibayar</th>
                                <th>Sisa</th>
                                <th>Skema</th>
                                <th>Metode</th>
                                <th>Status</th>
                                <th>Jatuh Tempo</th>
                                <th>Kasir</th>
                            </thead>
                        </table>
                    </div>
                </div>
                <div class="tab-pane {{ $activeTab === 'pembelian' ? 'active' : '' }}" id="tab-pembelian">
                    <div class="table-responsive">
                        <table class="table table-striped table-bordered table-pembelian-report">
                            <thead>
                                <th>Tanggal</th>
                                <th>No. Transaksi</th>
                                <th>Supplier</th>
                                <th>Total Item</th>
                                <th>Subtotal</th>
                                <th>Diskon</th>
                                <th>Diskon Nominal</th>
                                <th>DPP</th>
                                <th>PPN</th>
                                <th>PPN Nominal</th>
                                <th>Grand Total</th>
                                <th>Dibayar</th>
                                <th>Sisa</th>
                                <th>Skema</th>
                                <th>Metode</th>
                                <th>Status</th>
                                <th>Jatuh Tempo</th>
                            </thead>
                        </table>
                    </div>
                </div>
                <div class="tab-pane {{ $activeTab === 'produk' ? 'active' : '' }}" id="tab-produk">
                    <div class="table-responsive">
                        <table class="table table-striped table-bordered table-produk-report">
                            <thead>
                                <th>Kode Produk</th>
                                <th>Nama Produk</th>
                                <th>Kategori</th>
                                <th>Qty Jual</th>
                                <th>Penjualan DPP</th>
                                <th>Penjualan PPN</th>
                                <th>Penjualan Total</th>
                                <th>Qty Beli</th>
                                <th>Pembelian DPP</th>
                                <th>Pembelian PPN</th>
                                <th>Pembelian Total</th>
                                <th>Saldo Qty</th>
                                <th>Stok Saat Ini</th>
                            </thead>
                        </table>
                    </div>
                </div>
                <div class="tab-pane {{ $activeTab === 'kategori' ? 'active' : '' }}" id="tab-kategori">
                    <div class="table-responsive">
                        <table class="table table-striped table-bordered table-kategori-report">
                            <thead>
                                <th>Kategori</th>
                                <th>Jumlah Produk</th>
                                <th>Qty Jual</th>
                                <th>Penjualan DPP</th>
                                <th>Penjualan PPN</th>
                                <th>Penjualan Total</th>
                                <th>Qty Beli</th>
                                <th>Pembelian DPP</th>
                                <th>Pembelian PPN</th>
                                <th>Pembelian Total</th>
                                <th>Saldo Qty</th>
                            </thead>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    const reportUrl = '{{ route('laporan.data', [$tanggalAwal, $tanggalAkhir]) }}';
    const excelBaseUrl = '{{ url('/laporan/excel') }}';
    const activeInitialTab = @json($activeTab);
    let activeTab = activeInitialTab;
    let tables = {};

    $(function () {
        tables.ringkasan = $('.table-ringkasan').DataTable({
            responsive: false,
            processing: true,
            serverSide: true,
            autoWidth: false,
            scrollX: true,
            ajax: {
                url: reportUrl,
                data: { tab: 'ringkasan' },
            },
            columns: [
                {data: 'DT_RowIndex', searchable: false, sortable: false},
                {data: 'tanggal'},
                {data: 'penjualan_masuk'},
                {data: 'pembelian_keluar'},
                {data: 'pendapatan_lain'},
                {data: 'servis'},
                {data: 'biaya'},
                {data: 'pendapatan_bersih'},
            ],
            createdRow: function (row, data) {
                if (!data.DT_RowIndex) {
                    $(row).addClass('info');
                }
            }
        });

        tables.penjualan = $('.table-penjualan-report').DataTable({
            responsive: false,
            processing: true,
            serverSide: true,
            autoWidth: false,
            scrollX: true,
            ajax: {
                url: reportUrl,
                data: { tab: 'penjualan' },
            },
            columns: [
                {data: 'tanggal'},
                {data: 'nomor'},
                {data: 'pelanggan'},
                {data: 'total_item'},
                {data: 'subtotal'},
                {data: 'diskon'},
                {data: 'diskon_nominal'},
                {data: 'dpp'},
                {data: 'ppn'},
                {data: 'ppn_nominal'},
                {data: 'grand_total'},
                {data: 'dibayar'},
                {data: 'sisa'},
                {data: 'skema'},
                {data: 'metode'},
                {data: 'status', searchable: false, sortable: false},
                {data: 'jatuh_tempo'},
                {data: 'kasir'},
            ]
        });

        tables.pembelian = $('.table-pembelian-report').DataTable({
            responsive: false,
            processing: true,
            serverSide: true,
            autoWidth: false,
            scrollX: true,
            ajax: {
                url: reportUrl,
                data: { tab: 'pembelian' },
            },
            columns: [
                {data: 'tanggal'},
                {data: 'nomor'},
                {data: 'supplier'},
                {data: 'total_item'},
                {data: 'subtotal'},
                {data: 'diskon'},
                {data: 'diskon_nominal'},
                {data: 'dpp'},
                {data: 'ppn'},
                {data: 'ppn_nominal'},
                {data: 'grand_total'},
                {data: 'dibayar'},
                {data: 'sisa'},
                {data: 'skema'},
                {data: 'metode'},
                {data: 'status', searchable: false, sortable: false},
                {data: 'jatuh_tempo'},
            ]
        });

        tables.produk = $('.table-produk-report').DataTable({
            responsive: false,
            processing: true,
            serverSide: true,
            autoWidth: false,
            scrollX: true,
            ajax: {
                url: reportUrl,
                data: { tab: 'produk' },
            },
            columns: [
                {data: 'kode_produk'},
                {data: 'nama_produk'},
                {data: 'kategori'},
                {data: 'qty_jual'},
                {data: 'penjualan_dpp'},
                {data: 'penjualan_ppn'},
                {data: 'penjualan_total'},
                {data: 'qty_beli'},
                {data: 'pembelian_dpp'},
                {data: 'pembelian_ppn'},
                {data: 'pembelian_total'},
                {data: 'saldo_qty'},
                {data: 'stok_saat_ini'},
            ]
        });

        tables.kategori = $('.table-kategori-report').DataTable({
            responsive: false,
            processing: true,
            serverSide: true,
            autoWidth: false,
            scrollX: true,
            ajax: {
                url: reportUrl,
                data: { tab: 'kategori' },
            },
            columns: [
                {data: 'kategori'},
                {data: 'jumlah_produk'},
                {data: 'qty_jual'},
                {data: 'penjualan_dpp'},
                {data: 'penjualan_ppn'},
                {data: 'penjualan_total'},
                {data: 'qty_beli'},
                {data: 'pembelian_dpp'},
                {data: 'pembelian_ppn'},
                {data: 'pembelian_total'},
                {data: 'saldo_qty'},
            ]
        });

        $('a[data-toggle="tab"]').on('shown.bs.tab', function (e) {
            activeTab = $(e.target).data('tab');
            syncExportLink();
        });

        syncExportLink();
    });

    function applyPeriode() {
        const tanggalAwal = $('#tanggal_awal').val();
        const tanggalAkhir = $('#tanggal_akhir').val();

        if (!tanggalAwal || !tanggalAkhir) {
            Swal.fire({
                icon: 'warning',
                title: 'Periode belum lengkap',
                text: 'Pilih tanggal awal dan tanggal akhir terlebih dahulu.'
            });
            return;
        }

        window.location.href = `{{ route('laporan.index') }}?tanggal_awal=${tanggalAwal}&tanggal_akhir=${tanggalAkhir}&tab=${activeTab}`;
    }

    function syncExportLink() {
        const tanggalAwal = $('#tanggal_awal').val();
        const tanggalAkhir = $('#tanggal_akhir').val();
        $('#link-export-excel').attr('href', `${excelBaseUrl}/${activeTab}/${tanggalAwal}/${tanggalAkhir}`);
    }
</script>
@endpush
