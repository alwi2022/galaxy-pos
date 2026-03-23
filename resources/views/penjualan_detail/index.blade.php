@extends('layouts.master')

@section('title')
    Transaksi Penjualan
@endsection

@push('css')
<style>
    .transaction-box .box-body {
        padding-bottom: 0;
    }

    .transaction-summary {
        border: 1px solid #e5e7eb;
        border-radius: 6px;
        background: #fafcff;
        padding: 18px 18px 4px;
    }

    .tampil-bayar {
        font-size: 4.4em;
        text-align: center;
        min-height: 110px;
        padding: 16px;
        border-radius: 6px;
    }

    .tampil-terbilang {
        margin-top: 12px;
        padding: 14px 16px;
        border-radius: 6px;
        background: #f4f6f9;
        line-height: 1.6;
    }

    .table-penjualan tbody tr:last-child {
        display: none;
    }

    .transaction-summary .form-control[readonly] {
        background: #fff;
        font-weight: 600;
    }

    .transaction-summary .help-block {
        margin-top: 6px;
        margin-bottom: 0;
    }

    .transaction-summary .control-label {
        text-align: left;
    }

    @media (max-width: 991px) {
        .tampil-bayar {
            font-size: 3.2em;
            min-height: 88px;
        }

        .transaction-summary {
            margin-top: 16px;
        }
    }
</style>
@endpush

@section('breadcrumb')
    @parent
    <li class="active">Transaksi Penjaualn</li>
@endsection

@section('content')
<div class="row">
    <div class="col-lg-12">
        <div class="box transaction-box">
            <div class="box-body">
                @if ($errors->any())
                <div class="alert alert-danger">
                    <i class="fa fa-warning"></i> {{ $errors->first() }}
                </div>
                @endif

                <form class="form-produk">
                    @csrf
                    <div class="row">
                        <div class="col-lg-7">
                            <div class="form-group row">
                                <label for="kode_produk" class="col-sm-3 control-label">Kode Produk</label>
                                <div class="col-sm-9">
                                    <div class="input-group">
                                        <input type="hidden" name="id_penjualan" id="id_penjualan" value="{{ $id_penjualan }}">
                                        <input type="hidden" name="id_produk" id="id_produk">
                                        <input type="text" class="form-control" name="kode_produk" id="kode_produk" placeholder="Cari barcode / kode produk">
                                        <span class="input-group-btn">
                                            <button onclick="tampilProduk()" class="btn btn-info btn-flat" type="button"><i class="fa fa-search"></i></button>
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </form>

                <div class="table-responsive">
                    <table class="table table-striped table-bordered table-penjualan">
                        <thead>
                            <th width="5%">No</th>
                            <th>Kode</th>
                            <th>Nama</th>
                            <th>Harga</th>
                            <th width="15%">Jumlah</th>
                            <th>Diskon</th>
                            <th>Subtotal</th>
                            <th width="12%"><i class="fa fa-cog"></i></th>
                        </thead>
                    </table>
                </div>

                <div class="row" style="margin-top: 16px;">
                    <div class="col-lg-7">
                        <div class="tampil-bayar bg-primary"></div>
                        <div class="tampil-terbilang"></div>
                    </div>
                    <div class="col-lg-5">
                        <div class="transaction-summary">
                            <form action="{{ route('transaksi.simpan') }}" class="form-penjualan" method="post">
                                @csrf
                                <input type="hidden" name="id_penjualan" value="{{ $id_penjualan }}">
                                <input type="hidden" name="total" id="total">
                                <input type="hidden" name="total_item" id="total_item">
                                <input type="hidden" name="bayar" id="bayar">
                                <input type="hidden" name="dibayar" id="dibayar">
                                <input type="hidden" name="id_member" id="id_member" value="{{ $memberSelected->id_member }}">
                                <input type="hidden" name="ppn_persen" id="ppn_persen" value="{{ old('ppn_persen', $ppnPersen) }}">

                                <div class="form-group row">
                                    <label for="totalrp" class="col-sm-4 control-label">Subtotal</label>
                                    <div class="col-sm-8">
                                        <input type="text" id="totalrp" class="form-control" readonly>
                                    </div>
                                </div>
                                <div class="form-group row">
                                    <label for="kode_member" class="col-sm-4 control-label">Member</label>
                                    <div class="col-sm-8">
                                        <div class="input-group">
                                            <input type="text" class="form-control" id="kode_member" value="{{ $memberSelected->kode_member }}" placeholder="Pilih member untuk piutang">
                                            <span class="input-group-btn">
                                                <button onclick="tampilMember()" class="btn btn-info btn-flat" type="button"><i class="fa fa-users"></i></button>
                                            </span>
                                        </div>
                                        <span class="help-block text-muted">Member wajib dipilih jika transaksi dibuat kredit / hutang.</span>
                                    </div>
                                </div>
                                <div class="form-group row">
                                    <label for="diskon" class="col-sm-4 control-label">Diskon (%)</label>
                                    <div class="col-sm-8">
                                        <input type="number" name="diskon" id="diskon" class="form-control"
                                            value="{{ ! empty($memberSelected->id_member) ? $diskon : 0 }}"
                                            min="0" max="100" readonly>
                                    </div>
                                </div>
                                <div class="form-group row">
                                    <label for="diskonrp" class="col-sm-4 control-label">Diskon</label>
                                    <div class="col-sm-8">
                                        <input type="text" id="diskonrp" class="form-control" readonly>
                                    </div>
                                </div>
                                <div class="form-group row">
                                    <label for="ppnrp" class="col-sm-4 control-label">PPN ({{ rtrim(rtrim(number_format((float) $ppnPersen, 2, ',', '.'), '0'), ',') }}%)</label>
                                    <div class="col-sm-8">
                                        <input type="text" id="ppnrp" class="form-control" readonly>
                                    </div>
                                </div>
                                <div class="form-group row">
                                    <label for="skema_pembayaran" class="col-sm-4 control-label">Skema</label>
                                    <div class="col-sm-8">
                                        <select name="skema_pembayaran" id="skema_pembayaran" class="form-control">
                                            @foreach (daftar_skema_pembayaran() as $value => $label)
                                            <option value="{{ $value }}" {{ old('skema_pembayaran', $penjualan->skema_pembayaran ?? 'langsung') === $value ? 'selected' : '' }}>{{ $label }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>
                                <div class="form-group row">
                                    <label for="metode_pembayaran" class="col-sm-4 control-label">Metode</label>
                                    <div class="col-sm-8">
                                        <select name="metode_pembayaran" id="metode_pembayaran" class="form-control">
                                            @foreach (daftar_metode_pembayaran() as $value => $label)
                                            <option value="{{ $value }}" {{ old('metode_pembayaran', $penjualan->metode_pembayaran ?? 'tunai') === $value ? 'selected' : '' }}>{{ $label }}</option>
                                            @endforeach
                                        </select>
                                        <span class="help-block text-muted">Bank dan QRIS pada fase ini hanya untuk pencatatan manual.</span>
                                    </div>
                                </div>
                                <div class="form-group row" id="jatuh-tempo-group">
                                    <label for="jatuh_tempo" class="col-sm-4 control-label">Jatuh Tempo</label>
                                    <div class="col-sm-8">
                                        <input type="date" name="jatuh_tempo" id="jatuh_tempo" class="form-control" value="{{ old('jatuh_tempo', $penjualan->jatuh_tempo ?? '') }}">
                                    </div>
                                </div>
                                <div class="form-group row">
                                    <label for="bayarrp" class="col-sm-4 control-label">Tagihan</label>
                                    <div class="col-sm-8">
                                        <input type="text" id="bayarrp" class="form-control" readonly>
                                    </div>
                                </div>
                                <div class="form-group row">
                                    <label for="diterima" class="col-sm-4 control-label">Nominal Bayar</label>
                                    <div class="col-sm-8">
                                        <input type="number" id="diterima" class="form-control" name="diterima" value="{{ old('diterima', $penjualan->diterima ?? 0) }}" min="0">
                                    </div>
                                </div>
                                <div class="form-group row">
                                    <label for="dibayarrp" class="col-sm-4 control-label">Terbayar</label>
                                    <div class="col-sm-8">
                                        <input type="text" id="dibayarrp" class="form-control" readonly>
                                    </div>
                                </div>
                                <div class="form-group row">
                                    <label for="sisarp" class="col-sm-4 control-label">Sisa</label>
                                    <div class="col-sm-8">
                                        <input type="text" id="sisarp" class="form-control" readonly>
                                    </div>
                                </div>
                                <div class="form-group row" id="kembali-group">
                                    <label for="kembali" class="col-sm-4 control-label">Kembali</label>
                                    <div class="col-sm-8">
                                        <input type="text" id="kembali" name="kembali" class="form-control" value="0" readonly>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

            <div class="box-footer clearfix">
                <button type="submit" class="btn btn-primary btn-sm btn-flat pull-right btn-simpan"><i class="fa fa-floppy-o"></i> Simpan Transaksi</button>
            </div>
        </div>
    </div>
</div>

@includeIf('penjualan_detail.produk')
@includeIf('penjualan_detail.member')
@endsection

@push('scripts')
<script>
let table, table2;

    $(function () {
        $('body').addClass('sidebar-collapse');

    table = $('.table-penjualan').DataTable({
        responsive: false,
        processing: true,
        serverSide: true,
        autoWidth: false,
        scrollX: true,
        ajax: {
            url: '{{ route('transaksi.data', $id_penjualan) }}',
        },
        columns: [
            {data: 'DT_RowIndex', searchable: false, sortable: false},
            {data: 'kode_produk'},
            {data: 'nama_produk'},
            {data: 'harga_jual'},
            {data: 'jumlah'},
            {data: 'diskon'},
            {data: 'subtotal'},
            {data: 'aksi', searchable: false, sortable: false},
        ],
        dom: 'Brt',
        bSort: false,
        paginate: false
    })
    .on('draw.dt', function () {
        loadForm($('#diskon').val(), $('#diterima').val());
        setTimeout(() => {
            $('#diterima').trigger('input');
        }, 150);
    });

    table2 = $('.table-produk').DataTable();

    $(document).on('input', '.quantity', function () {
        let id = $(this).data('id');
        let jumlah = parseInt($(this).val());

        if (jumlah < 1) {
            $(this).val(1);
            Swal.fire({
                icon: 'warning',
                title: 'Jumlah minimal 1',
                text: 'Jumlah tidak boleh kurang dari 1'
            });
            return;
        }

        if (jumlah > 10000) {
            $(this).val(10000);
            Swal.fire({
                icon: 'warning',
                title: 'Jumlah terlalu besar',
                text: 'Jumlah tidak boleh lebih dari 10000'
            });
            return;
        }

        $.post(`{{ url('/transaksi') }}/${id}`, {
                '_token': $('[name=csrf-token]').attr('content'),
                '_method': 'put',
                'jumlah': jumlah
            })
            .done(() => {
                $(this).on('mouseout', function () {
                    table.ajax.reload(() => loadForm($('#diskon').val(), $('#diterima').val()));
                });
            })
            .fail(() => {
                Swal.fire({
                    icon: 'error',
                    title: 'Gagal!',
                    text: 'Tidak dapat menyimpan data'
                });
            });
    });

    $(document).on('input', '#diskon', function () {
        if ($(this).val() === '') {
            $(this).val(0);
        }

        loadForm($('#diskon').val(), $('#diterima').val());
    });

    $('#diterima').on('input', function () {
        if ($(this).val() === '') {
            $(this).val(0).select();
        }
        loadForm($('#diskon').val(), $(this).val());
    }).focus(function () {
        $(this).select();
    });

    $('#skema_pembayaran, #metode_pembayaran').on('change', function () {
        togglePaymentState();
        loadForm($('#diskon').val(), $('#diterima').val());
    });

    $('.btn-simpan').on('click', function () {
        const totalTagihan = parseInt($('#bayar').val() || 0);
        const totalDibayar = parseInt($('#dibayar').val() || 0);
        const nominalBayar = parseInt($('#diterima').val() || 0);
        const skema = $('#skema_pembayaran').val();
        const metode = $('#metode_pembayaran').val();

        if (skema === 'kredit' && !$('#id_member').val()) {
            Swal.fire({
                icon: 'warning',
                title: 'Member wajib dipilih',
                text: 'Penjualan kredit harus terhubung ke member.'
            });
            return;
        }

        if (skema === 'kredit' && !$('#jatuh_tempo').val()) {
            Swal.fire({
                icon: 'warning',
                title: 'Jatuh tempo belum diisi',
                text: 'Lengkapi tanggal jatuh tempo untuk transaksi kredit.'
            });
            return;
        }

        if (skema === 'langsung' && totalDibayar < totalTagihan) {
            Swal.fire({
                icon: 'warning',
                title: 'Pembayaran belum lunas',
                text: 'Skema pembayaran langsung harus lunas.'
            });
            return;
        }

        if (metode !== 'tunai' && nominalBayar > totalTagihan) {
            Swal.fire({
                icon: 'warning',
                title: 'Nominal terlalu besar',
                text: 'Pembayaran non tunai tidak boleh melebihi total tagihan.'
            });
            return;
        }

        $('.form-penjualan').submit();
    });

    togglePaymentState();
});

function tampilProduk() {
    $('#modal-produk').modal('show');
}

function hideProduk() {
    $('#modal-produk').modal('hide');
}

function pilihProduk(id, kode) {
    $('#id_produk').val(id);
    $('#kode_produk').val(kode);
    hideProduk();
    tambahProduk();
}

function tambahProduk() {
    $.post('{{ route('transaksi.store') }}', $('.form-produk').serialize())
        .done(() => {
            $('#kode_produk').focus();
            table.ajax.reload(() => loadForm($('#diskon').val(), $('#diterima').val()));
        })
        .fail(() => {
            Swal.fire({
                icon: 'error',
                title: 'Gagal!',
                text: 'Tidak dapat menyimpan data'
            });
        });
}

function tampilMember() {
    $('#modal-member').modal('show');
}

function pilihMember(id, kode) {
    $('#id_member').val(id);
    $('#kode_member').val(kode);
    $('#diskon').val('{{ $diskon }}');
    loadForm($('#diskon').val(), $('#diterima').val());
    $('#diterima').val(0).focus().select();
    hideMember();
}

function hideMember() {
    $('#modal-member').modal('hide');
}

function deleteData(url) {
    Swal.fire({
        title: 'Hapus Data?',
        text: 'Yakin ingin menghapus data terpilih?',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Ya, hapus!',
        cancelButtonText: 'Batal',
        confirmButtonColor: '#d33',
        cancelButtonColor: '#3085d6'
    }).then((result) => {
        if (result.isConfirmed) {
            $.post(url, {
                '_token': $('[name=csrf-token]').attr('content'),
                '_method': 'delete'
            })
            .done(() => {
                table.ajax.reload(() => loadForm($('#diskon').val(), $('#diterima').val()));
                Swal.fire({
                    icon: 'success',
                    title: 'Berhasil!',
                    text: 'Data berhasil dihapus',
                    timer: 1500,
                    showConfirmButton: false
                });
            })
            .fail(() => {
                Swal.fire({
                    icon: 'error',
                    title: 'Gagal!',
                    text: 'Tidak dapat menghapus data'
                });
            });
        }
    });
}

function loadForm(diskon = 0, diterima = 0) {
    $('#total').val($('.total').text());
    $('#total_item').val($('.total_item').text());

        $.get(`{{ url('/transaksi/loadform') }}/${diskon}/${$('.total').text()}/${diterima}`, {
            ppn_persen: $('#ppn_persen').val() || 0
        })
        .done(response => {
            $('#totalrp').val('Rp. ' + response.totalrp);
            $('#diskonrp').val('Rp. ' + response.diskonrp);
            $('#ppnrp').val('Rp. ' + response.ppnrp);
            $('#bayarrp').val('Rp. ' + response.bayarrp);
            $('#bayar').val(response.bayar);
            $('#dibayar').val(response.dibayar);
            $('#dibayarrp').val('Rp. ' + response.dibayarrp);
            $('#sisarp').val('Rp. ' + response.sisarp);
            $('#kembali').val('Rp. ' + response.kembalirp);

            $('.tampil-bayar').text('Tagihan: Rp. ' + response.bayarrp);
            $('.tampil-terbilang').html(
                'PPN Rp. ' + response.ppnrp +
                '<br>Terbayar Rp. ' + response.dibayarrp +
                ' | Sisa Rp. ' + response.sisarp
            );

            if (parseInt(response.kembalirp.replace(/\./g, '')) > 0) {
                $('.tampil-terbilang').html(
                    'PPN Rp. ' + response.ppnrp +
                    '<br>Kembali Rp. ' + response.kembalirp
                );
            }
        })
        .fail(() => {
            Swal.fire({
                icon: 'error',
                title: 'Gagal!',
                text: 'Tidak dapat menampilkan data'
            });
        });
}

function togglePaymentState() {
    const isCredit = $('#skema_pembayaran').val() === 'kredit';
    const isCash = $('#metode_pembayaran').val() === 'tunai';

    $('#jatuh-tempo-group').toggle(isCredit);
    $('#kembali-group').toggle(isCash);

    if (!isCredit) {
        $('#jatuh_tempo').val('');
    }
}
</script>
@endpush
