@extends('layouts.master')
@section('title')
    Transaksi Penjualan
@endsection

@push('css')
<style>
    .tampil-bayar {
        font-size: 5em;
        text-align: center;
        height: 100px;
    }

    .tampil-terbilang {
        padding: 10px;
        background: #f0f0f0;
    }

    .table-penjualan tbody tr:last-child {
        display: none;
    }

    .barcode-input {
        font-size: 16px;
        border: 2px solid #007BFF;
        background-color: #f8f9ff;
    }

    .barcode-input:focus {
        border-color: #0056b3;
        box-shadow: 0 0 0 0.2rem rgba(0, 123, 255, 0.25);
    }

    .scan-indicator {
        background: linear-gradient(45deg, #28a745, #20c997);
        color: white;
        padding: 8px 12px;
        border-radius: 4px;
        font-weight: bold;
        text-align: center;
        margin-bottom: 10px;
        display: none;
    }

    .scan-indicator.active {
        display: block;
        animation: pulse 1s infinite;
    }

    @keyframes pulse {
        0% { opacity: 1; }
        50% { opacity: 0.7; }
        100% { opacity: 1; }
    }

    @media(max-width: 768px) {
        .tampil-bayar {
            font-size: 3em;
            height: 70px;
            padding-top: 5px;
        }
    }
</style>
@endpush

@section('breadcrumb')
    @parent
    <li class="active">Transaksi Penjualan</li>
@endsection

@section('content')
<div class="row">
    <div class="col-lg-12">
        <div class="box">
            <div class="box-body">
                
                {{-- Barcode Scanner Input --}}
                <div class="row">
                    <div class="col-lg-12">
                        <div class="scan-indicator" id="scan-indicator">
                            <i class="fa fa-barcode"></i> Siap untuk scan barcode...
                        </div>
                        <div class="form-group">
                            <label for="barcode_input"><i class="fa fa-barcode"></i> Scan Barcode Produk</label>
                            <div class="input-group">
                                <span class="input-group-addon"><i class="fa fa-search"></i></span>
                                <input type="text" id="barcode_input" class="form-control barcode-input" 
                                       placeholder="Scan barcode produk di sini atau ketik kode produk..." 
                                       autofocus>
                                <span class="input-group-addon">
                                    <i class="fa fa-barcode text-primary"></i>
                                </span>
                            </div>
                            <small class="text-muted">
                                <i class="fa fa-info-circle"></i> 
                                Arahkan scanner ke barcode produk atau ketik kode produk secara manual
                            </small>
                        </div>
                    </div>
                </div>

                <hr>
                    
                <form class="form-produk">
                    @csrf
                    <div class="form-group row">
                        <label for="kode_produk" class="col-lg-2">Kode Produk (Manual)</label>
                        <div class="col-lg-5">
                            <div class="input-group">
                                <input type="hidden" name="id_penjualan" id="id_penjualan" value="{{ $id_penjualan }}">
                                <input type="hidden" name="id_produk" id="id_produk">
                                <input type="text" class="form-control" name="kode_produk" id="kode_produk" 
                                       placeholder="Masukkan kode produk manual">
                                <span class="input-group-btn">
                                    <button onclick="tampilProduk()" class="btn btn-info btn-flat" type="button">
                                        <i class="fa fa-search"></i> Cari
                                    </button>
                                </span>
                            </div>
                        </div>
                    </div>
                </form>

                <table class="table table-striped table-bordered table-penjualan">
                    <thead>
                        <th width="5%">No</th>
                        <th>Kode</th>
                        <th>Nama</th>
                        <th>Harga</th>
                        <th width="15%">Jumlah</th>
                        <th>Diskon</th>
                        <th>Subtotal</th>
                        <th width="15%"><i class="fa fa-cog"></i></th>
                    </thead>
                </table>

                <div class="row">
                    <div class="col-lg-8">
                        <div class="tampil-bayar bg-primary"></div>
                        <div class="tampil-terbilang"></div>
                    </div>
                    <div class="col-lg-4">
                        <form action="{{ route('transaksi.simpan') }}" class="form-penjualan" method="post">
                            @csrf
                            <input type="hidden" name="id_penjualan" value="{{ $id_penjualan }}">
                            <input type="hidden" name="total" id="total">
                            <input type="hidden" name="total_item" id="total_item">
                            <input type="hidden" name="bayar" id="bayar">
                            <input type="hidden" name="id_member" id="id_member" value="{{ $memberSelected->id_member }}">

                            <div class="form-group row">
                                <label for="totalrp" class="col-lg-2 control-label">Total</label>
                                <div class="col-lg-8">
                                    <input type="text" id="totalrp" class="form-control" readonly>
                                </div>
                            </div>
                            <div class="form-group row">
                                <label for="kode_member" class="col-lg-2 control-label">Member</label>
                                <div class="col-lg-8">
                                    <div class="input-group">
                                        <input type="text" class="form-control" id="kode_member" value="{{ $memberSelected->kode_member }}">
                                        <span class="input-group-btn">
                                            <button onclick="tampilMember()" class="btn btn-info btn-flat" type="button"><i class="fa fa-arrow-right"></i></button>
                                        </span>
                                    </div>
                                </div>
                            </div>
                            <div class="form-group row">
                                <label for="diskon" class="col-lg-2 control-label">Diskon</label>
                                <div class="col-lg-8">
                                    <input type="number" name="diskon" id="diskon" class="form-control" 
                                        value="{{ ! empty($memberSelected->id_member) ? $diskon : 0 }}" 
                                        readonly>
                                </div>
                            </div>
                            <div class="form-group row">
                                <label for="bayar" class="col-lg-2 control-label">Bayar</label>
                                <div class="col-lg-8">
                                    <input type="text" id="bayarrp" class="form-control" readonly>
                                </div>
                            </div>
                            <div class="form-group row">
                                <label for="diterima" class="col-lg-2 control-label">Diterima</label>
                                <div class="col-lg-8">
                                    <input type="number" id="diterima" class="form-control" name="diterima" value="{{ $penjualan->diterima ?? 0 }}">
                                </div>
                            </div>
                            <div class="form-group row">
                                <label for="kembali" class="col-lg-2 control-label">Kembali</label>
                                <div class="col-lg-8">
                                    <input type="text" id="kembali" name="kembali" class="form-control" value="0" readonly>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <div class="box-footer">
                <button type="submit" class="btn btn-primary btn-sm btn-flat pull-right btn-simpan">
                    <i class="fa fa-floppy-o"></i> Simpan Transaksi
                </button>
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
    let isScanning = false;

    $(function () {
        $('body').addClass('sidebar-collapse');

        table = $('.table-penjualan').DataTable({
            responsive: true,
            processing: true,
            serverSide: true,
            autoWidth: false,
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
            loadForm($('#diskon').val());
            setTimeout(() => {
                $('#diterima').trigger('input');
            }, 300);
        });
        table2 = $('.table-produk').DataTable();

        // Barcode Scanner Handler
        $('#barcode_input').on('keypress', function (e) {
            if (e.which == 13) { // Enter key
                e.preventDefault();
                let kode = $(this).val().trim();
                
                if (kode.length === 0) {
                    alert('Masukkan kode produk terlebih dahulu');
                    return;
                }

                // Show loading indicator
                showScanIndicator();
                
                // Tambah produk via barcode
                tambahProdukViaBarcode(kode);
            }
        });

        // Auto focus barcode input when page loads
        $('#barcode_input').focus();

        // Re-focus barcode input after any action
        $(document).on('click', function() {
            setTimeout(function() {
                if (!$('.modal').hasClass('in')) {
                    $('#barcode_input').focus();
                }
            }, 100);
        });

        $(document).on('input', '.quantity', function () {
            let id = $(this).data('id');
            let jumlah = parseInt($(this).val());

            if (jumlah < 1) {
                $(this).val(1);
                alert('Jumlah tidak boleh kurang dari 1');
                return;
            }
            if (jumlah > 10000) {
                $(this).val(10000);
                alert('Jumlah tidak boleh lebih dari 10000');
                return;
            }

            $.post(`{{ url('/transaksi') }}/${id}`, {
                    '_token': $('[name=csrf-token]').attr('content'),
                    '_method': 'put',
                    'jumlah': jumlah
                })
                .done(response => {
                    $(this).on('mouseout', function () {
                        table.ajax.reload(() => loadForm($('#diskon').val()));
                    });
                })
                .fail(errors => {
                    alert('Tidak dapat menyimpan data');
                    return;
                });
        });

        $(document).on('input', '#diskon', function () {
            if ($(this).val() == "") {
                $(this).val(0).select();
            }

            loadForm($(this).val());
        });

        $('#diterima').on('input', function () {
            if ($(this).val() == "") {
                $(this).val(0).select();
            }

            loadForm($('#diskon').val(), $(this).val());
        }).focus(function () {
            $(this).select();
        });

        $('.btn-simpan').on('click', function () {
            $('.form-penjualan').submit();
        });
    });

    // Function untuk menampilkan indikator scanning
    function showScanIndicator() {
        $('#scan-indicator').addClass('active').html('<i class="fa fa-spinner fa-spin"></i> Mencari produk...');
        isScanning = true;
    }

    function hideScanIndicator(success = true, message = '') {
        isScanning = false;
        if (success) {
            $('#scan-indicator').removeClass('active').html('<i class="fa fa-check text-success"></i> Produk berhasil ditambahkan!');
            setTimeout(() => {
                $('#scan-indicator').html('<i class="fa fa-barcode"></i> Siap untuk scan barcode...');
            }, 2000);
        } else {
            $('#scan-indicator').html(`<i class="fa fa-times text-danger"></i> ${message}`);
            setTimeout(() => {
                $('#scan-indicator').html('<i class="fa fa-barcode"></i> Siap untuk scan barcode...');
            }, 3000);
        }
    }

    // Function untuk menambah produk via barcode
    function tambahProdukViaBarcode(kode) {
        $.post('{{ route('transaksi.tambah_barcode') }}', {
            '_token': $('[name=csrf-token]').attr('content'),
            'kode_produk': kode,
            'id_penjualan': $('#id_penjualan').val()
        })
        .done(function(response) {
            if (response.success) {
                hideScanIndicator(true);
                $('#barcode_input').val('').focus();
                table.ajax.reload(() => loadForm($('#diskon').val()));
                
                // Sound notification (optional)
                playSuccessSound();
            } else {
                hideScanIndicator(false, response.message);
                $('#barcode_input').val('').focus();
            }
        })
        .fail(function(xhr) {
            let message = 'Produk tidak ditemukan';
            if (xhr.responseJSON && xhr.responseJSON.message) {
                message = xhr.responseJSON.message;
            }
            hideScanIndicator(false, message);
            $('#barcode_input').val('').focus();
        });
    }

    // Optional: Play success sound
    function playSuccessSound() {
        // You can add audio feedback here
        // var audio = new Audio('/sounds/beep.mp3');
        // audio.play();
    }

    function tampilProduk() {
        $('#modal-produk').modal('show');
    }

    function hideProduk() {
        $('#modal-produk').modal('hide');
        $('#barcode_input').focus();
    }

    function pilihProduk(id, kode) {
        $('#id_produk').val(id);
        $('#kode_produk').val(kode);
        hideProduk();
        tambahProduk();
    }

    function tambahProduk() {
        $.post('{{ route('transaksi.store') }}', $('.form-produk').serialize())
            .done(response => {
                $('#kode_produk').val('');
                $('#barcode_input').focus();
                table.ajax.reload(() => loadForm($('#diskon').val()));
            })
            .fail(errors => {
                alert('Tidak dapat menyimpan data');
                return;
            });
    }

    function tampilMember() {
        $('#modal-member').modal('show');
    }

    function pilihMember(id, kode) {
        $('#id_member').val(id);
        $('#kode_member').val(kode);
        $('#diskon').val('{{ $diskon }}');
        loadForm($('#diskon').val());
        $('#diterima').val(0).focus().select();
        hideMember();
    }

    function hideMember() {
        $('#modal-member').modal('hide');
        $('#barcode_input').focus();
    }

    function deleteData(url) {
        if (confirm('Yakin ingin menghapus data terpilih?')) {
            $.post(url, {
                    '_token': $('[name=csrf-token]').attr('content'),
                    '_method': 'delete'
                })
                .done((response) => {
                    table.ajax.reload(() => loadForm($('#diskon').val()));
                    $('#barcode_input').focus();
                })
                .fail((errors) => {
                    alert('Tidak dapat menghapus data');
                    return;
                });
        }
    }

    function loadForm(diskon = 0, diterima = 0) {
        $('#total').val($('.total').text());
        $('#total_item').val($('.total_item').text());

        $.get(`{{ url('/transaksi/loadform') }}/${diskon}/${$('.total').text()}/${diterima}`)
            .done(response => {
                $('#totalrp').val('Rp. '+ response.totalrp);
                $('#bayarrp').val('Rp. '+ response.bayarrp);
                $('#bayar').val(response.bayar);
                $('.tampil-bayar').text('Bayar: Rp. '+ response.bayarrp);
                $('.tampil-terbilang').text(response.terbilang);

                $('#kembali').val('Rp.'+ response.kembalirp);
                if ($('#diterima').val() != 0) {
                    $('.tampil-bayar').text('Kembali: Rp. '+ response.kembalirp);
                    $('.tampil-terbilang').text(response.kembali_terbilang);
                }
            })
            .fail(errors => {
                alert('Tidak dapat menampilkan data');
                return;
            })
    }

    // Keyboard shortcuts
    $(document).keydown(function(e) {
        // F1 - Focus barcode input
        if (e.which == 112) {
            e.preventDefault();
            $('#barcode_input').focus().select();
        }
        
        // F2 - Tampil produk
        if (e.which == 113) {
            e.preventDefault();
            tampilProduk();
        }
        
        // F3 - Tampil member
        if (e.which == 114) {
            e.preventDefault();
            tampilMember();
        }
        
        // F4 - Focus diterima
        if (e.which == 115) {
            e.preventDefault();
            $('#diterima').focus().select();
        }
        
        // Escape - Close modals and focus barcode
        if (e.which == 27) {
            $('.modal').modal('hide');
            setTimeout(() => {
                $('#barcode_input').focus();
            }, 300);
        }
    });
</script>
@endpush