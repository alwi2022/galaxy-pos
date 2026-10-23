<!-- resources/views/servis/index.blade.php -->

@extends('layouts.master')

@section('title')
    Daftar Servis
@endsection

@section('breadcrumb')
    @parent
    <li>Servis</li>
    <li class="active">Daftar Servis</li>
@endsection

@section('content')
<div class="row">
    <div class="col-lg-12">
        <div class="box">
            <div class="box-header with-border">
                <button onclick="addForm('{{ route('servis.store') }}')" class="btn btn-success btn-xs btn-flat"><i class="fa fa-plus-circle"></i> Tambah</button>
            </div>
            <div class="box-body table-responsive">
                <table class="table table-stiped table-bordered table-servis">
                    <thead>
                        <th width="5%">No</th>
                        <th>Kode</th>
                        <th>Nama Pelanggan</th>
                        <th>Barang</th>
                        <th>Kerusakan</th>
                        <th>Status</th>
                        <th width="15%"><i class="fa fa-cog"></i></th>
                    </thead>
                </table>
            </div>
        </div>
    </div>
</div>

@includeIf('servis.form')
@includeIf('servis.sparepart')
@endsection

@push('scripts')
<script>
let table;

$(function () {
    table = $('.table-servis').DataTable({
        processing: true,
        autoWidth: false,
        ajax: {
            url: '{{ route('servis.data') }}',
        },
        columns: [
            {data: 'DT_RowIndex', searchable: false, sortable: false},
            {data: 'kode_servis'},
            {data: 'nama_pelanggan'},
            {data: 'tipe_barang'},
            {data: 'kerusakan'},
            {data: 'status'},
            {data: 'aksi', searchable: false, sortable: false},
        ]
    });

    $('#modal-form').validator().on('submit', function (e) {
        if (!e.preventDefault()) {
            $.post($('#modal-form form').attr('action'), $('#modal-form form').serialize())
                .done((res) => {
                    $('#modal-form').modal('hide');
                    table.ajax.reload();

                    // ✅ Konfirmasi kirim WA teknisi
                    if (res.wa_teknisi) {
                        Swal.fire({
                            title: 'Kirim WA ke Teknisi?',
                            text: 'Ingin kirim notifikasi WhatsApp ke teknisi?',
                            icon: 'question',
                            showCancelButton: true,
                            confirmButtonText: 'Ya, kirim',
                            cancelButtonText: 'Batal',
                        }).then((result) => {
                            if (result.isConfirmed) {
                                window.open(res.wa_teknisi, '_blank');
                            }
                        });
                    }

                    // ✅ Konfirmasi kirim WA customer
                    if (res.wa) {
                        Swal.fire({
                            title: 'Kirim WA ke Customer?',
                            text: 'Ingin kirim notifikasi WhatsApp ke customer?',
                            icon: 'question',
                            showCancelButton: true,
                            confirmButtonText: 'Ya, kirim',
                            cancelButtonText: 'Batal',
                        }).then((result) => {
                            if (result.isConfirmed) {
                                window.open(res.wa, '_blank');
                            }
                        });
                    }
                })
                .fail(() => {
                    Swal.fire({
                        icon: 'error',
                        title: 'Gagal!',
                        text: 'Tidak dapat menyimpan data.'
                    });
                });
        }
    });
});

function addForm(url) {
    $('#modal-form').modal('show');
    $('#modal-form .modal-title').text('Tambah Servis');
    $('#modal-form form')[0].reset();
    $('#modal-form form').attr('action', url);
    $('#modal-form [name=_method]').val('post');
}

function editForm(url) {
    $.get(url).done((res) => {
        $('#modal-form').modal('show');
        $('#modal-form .modal-title').text('Edit Servis');
        $('#modal-form form').attr('action', url.replace('/edit', ''));
        $('#modal-form [name=_method]').val('put');

        $('#modal-form [name=nama_pelanggan]').val(res.nama_pelanggan);
        $('#modal-form [name=telepon]').val(res.telepon);
        $('#modal-form [name=tipe_barang]').val(res.tipe_barang);
        $('#modal-form [name=kerusakan]').val(res.kerusakan);
        $('#modal-form [name=biaya_servis]').val(res.biaya_servis);
        $('#modal-form [name=status]').val(res.status);
        $('#modal-form [name=jatuh_tempo]').val(res.jatuh_tempo);
    });
}

function deleteData(url) {
    Swal.fire({
        title: 'Hapus Data?',
        text: 'Yakin ingin menghapus data ini?',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Ya, Hapus!',
        cancelButtonText: 'Batal',
        confirmButtonColor: '#d33',
        cancelButtonColor: '#3085d6',
        reverseButtons: true
    }).then((result) => {
        if (result.isConfirmed) {
            $.post(url, {
                '_token': $('[name=csrf-token]').attr('content'),
                '_method': 'delete'
            }).done(() => {
                table.ajax.reload();
                Swal.fire({
                    icon: 'success',
                    title: 'Berhasil!',
                    text: 'Data berhasil dihapus.',
                    timer: 1500,
                    showConfirmButton: false
                });
            }).fail(() => {
                Swal.fire({
                    icon: 'error',
                    title: 'Gagal!',
                    text: 'Tidak dapat menghapus data.'
                });
            });
        }
    });
}

// ================= SPAREPART SERVIS =================
let tableSparepart, tableProdukSparepart, spServisId = null, spJasa = 0, spBisaUbah = true;

function rupiah(n) {
    return 'Rp. ' + (parseInt(n) || 0).toLocaleString('id-ID');
}

function kelolaSparepart(id, kode, status, biaya) {
    spServisId = id;
    spJasa = parseInt(biaya) || 0;
    spBisaUbah = (status !== 'selesai' && status !== 'diambil');

    $('#sp-kode').text(kode);
    $('#sp-jasa').text(rupiah(spJasa));
    $('#sp-form-wrap').toggle(spBisaUbah);
    $('#sp-alert-selesai').toggle(!spBisaUbah);
    $('#sp-kode-produk').val('');
    $('#sp-jumlah').val(1);

    if (tableSparepart) {
        tableSparepart.ajax.url('{{ url('servis') }}/' + id + '/sparepart/data').load();
    } else {
        tableSparepart = $('.table-sparepart').DataTable({
            processing: true,
            autoWidth: false,
            paging: false,
            searching: false,
            info: false,
            ajax: { url: '{{ url('servis') }}/' + id + '/sparepart/data' },
            columns: [
                {data: 'DT_RowIndex', searchable: false, sortable: false},
                {data: 'kode_produk'},
                {data: 'nama_produk'},
                {data: 'harga', className: 'text-right'},
                {data: 'jumlah', className: 'text-right'},
                {data: 'subtotal', className: 'text-right'},
                {data: 'aksi', searchable: false, sortable: false},
            ],
            drawCallback: function () {
                let sub = 0;
                let rows = this.api().rows().data();
                for (let i = 0; i < rows.length; i++) {
                    sub += parseInt(String(rows[i].subtotal).replace(/[^0-9]/g, '')) || 0;
                }
                $('#sp-sparepart').text(rupiah(sub));
                $('#sp-total').text(rupiah(spJasa + sub));
            }
        });
    }

    $('#modal-sparepart').modal('show');
}

function tampilProdukSparepart() {
    if (tableProdukSparepart) {
        tableProdukSparepart.ajax.reload();
    } else {
        tableProdukSparepart = $('.table-produk-sparepart').DataTable({
            processing: true,
            autoWidth: false,
            ajax: { url: '{{ route('servis.produk.data') }}' },
            columns: [
                {data: 'kode_produk'},
                {data: 'nama_produk'},
                {data: 'harga', className: 'text-right'},
                {data: 'stok', className: 'text-right'},
                {data: 'aksi', searchable: false, sortable: false},
            ]
        });
    }
    $('#modal-produk-sparepart').modal('show');
}

function pilihProdukSparepart(id, kode) {
    $('#sp-kode-produk').val(kode).data('id', id);
    $('#modal-produk-sparepart').modal('hide');
}

function tambahSparepart() {
    let idProduk = $('#sp-kode-produk').data('id');
    let jumlah = parseInt($('#sp-jumlah').val()) || 0;
    if (!idProduk) {
        Swal.fire({icon: 'warning', title: 'Pilih produk', text: 'Cari dan pilih produk dulu.'});
        return;
    }
    if (jumlah < 1) {
        Swal.fire({icon: 'warning', title: 'Jumlah tidak valid', text: 'Jumlah minimal 1.'});
        return;
    }
    $.post('{{ url('servis') }}/' + spServisId + '/sparepart', {
        '_token': $('[name=csrf-token]').attr('content'),
        'id_produk': idProduk,
        'jumlah': jumlah
    }).done(() => {
        $('#sp-kode-produk').val('').removeData('id');
        $('#sp-jumlah').val(1);
        tableSparepart.ajax.reload();
        table.ajax.reload();
    }).fail((xhr) => {
        let pesan = (xhr.responseJSON && (xhr.responseJSON.message || xhr.responseJSON)) || 'Tidak dapat menambah sparepart.';
        if (typeof pesan === 'object') pesan = 'Tidak dapat menambah sparepart.';
        Swal.fire({icon: 'error', title: 'Gagal!', text: pesan});
    });
}

function hapusSparepart(sparepartId) {
    Swal.fire({
        title: 'Hapus sparepart?',
        text: 'Stok barang akan dikembalikan.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Ya, hapus',
        cancelButtonText: 'Batal',
        confirmButtonColor: '#d33',
        reverseButtons: true
    }).then((r) => {
        if (!r.isConfirmed) return;
        $.post('{{ url('servis') }}/' + spServisId + '/sparepart/' + sparepartId, {
            '_token': $('[name=csrf-token]').attr('content'),
            '_method': 'delete'
        }).done(() => {
            tableSparepart.ajax.reload();
            table.ajax.reload();
        }).fail((xhr) => {
            let pesan = (xhr.responseJSON && (xhr.responseJSON.message || xhr.responseJSON)) || 'Tidak dapat menghapus sparepart.';
            if (typeof pesan === 'object') pesan = 'Tidak dapat menghapus sparepart.';
            Swal.fire({icon: 'error', title: 'Gagal!', text: pesan});
        });
    });
}
</script>
@endpush
