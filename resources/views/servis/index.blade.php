<!-- resources/views/servis/index.blade.php -->

@extends('layouts.master')

@section('title')
    Daftar Servis
@endsection

@section('breadcrumb')
    @parent
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
                <table class="table table-stiped table-bordered">
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
@endsection

@push('scripts')
<script>
let table;

$(function () {
    table = $('.table').DataTable({
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
</script>
@endpush
