@extends('layouts.master')

@section('title')
    Biaya Operasional
@endsection

@section('breadcrumb')
    @parent
    <li>Keuangan</li>
    <li class="active">Biaya Operasional</li>
@endsection

@section('content')
<div class="row">
    <div class="col-lg-12">
        <div class="box">
            <div class="box-header with-border">
                <div class="toolbar-inline">
                    <button onclick="addForm('{{ route('pengeluaran.store') }}')" class="btn btn-success btn-xs btn-flat"><i class="fa fa-plus-circle"></i> Tambah Biaya</button>
                    <span class="text-muted">Catat biaya seperti listrik, ATK, gaji, dan operasional lain secara manual.</span>
                </div>
            </div>
            <div class="box-body table-responsive">
                <table class="table table-striped table-bordered table-biaya">
                    <thead>
                        <th width="5%">No</th>
                        <th>Tanggal</th>
                        <th>Kategori</th>
                        <th>Deskripsi</th>
                        <th>Metode</th>
                        <th>Nominal</th>
                        <th width="12%"><i class="fa fa-cog"></i></th>
                    </thead>
                </table>
            </div>
        </div>
    </div>
</div>

@includeIf('pengeluaran.form')
@endsection

@push('scripts')
<script>
    let table;

    $(function () {
        table = $('.table-biaya').DataTable({
            responsive: false,
            processing: true,
            serverSide: true,
            autoWidth: false,
            scrollX: true,
            ajax: {
                url: '{{ route('pengeluaran.data') }}',
            },
            columns: [
                {data: 'DT_RowIndex', searchable: false, sortable: false},
                {data: 'tanggal_pengeluaran'},
                {data: 'kategori_pengeluaran'},
                {data: 'deskripsi'},
                {data: 'metode_pembayaran'},
                {data: 'nominal', className: 'text-right'},
                {data: 'aksi', searchable: false, sortable: false},
            ]
        });

        $('#modal-form').validator().on('submit', function (e) {
            if (! e.preventDefault()) {
                $.post($('#modal-form form').attr('action'), $('#modal-form form').serialize())
                    .done(() => {
                        $('#modal-form').modal('hide');
                        table.ajax.reload();

                        Swal.fire({
                            icon: 'success',
                            title: 'Berhasil!',
                            text: 'Data berhasil disimpan.',
                            timer: 1500,
                            showConfirmButton: false
                        });
                    })
                    .fail((errors) => {
                        const message = errors.responseJSON?.message
                            || Object.values(errors.responseJSON?.errors || {})[0]?.[0]
                            || 'Tidak dapat menyimpan data.';

                        Swal.fire({
                            icon: 'error',
                            title: 'Gagal!',
                            text: message,
                            confirmButtonText: 'OK',
                            confirmButtonColor: '#d33'
                        });
                    });
            }
        });
    });

    function addForm(url) {
        $('#modal-form').modal('show');
        $('#modal-form .modal-title').text('Tambah Biaya Operasional');

        $('#modal-form form')[0].reset();
        $('#modal-form form').attr('action', url);
        $('#modal-form [name=_method]').val('post');
        $('#modal-form [name=tanggal_pengeluaran]').val('{{ date('Y-m-d') }}');
        $('#modal-form [name=kategori_pengeluaran]').val('operasional_lainnya');
        $('#modal-form [name=metode_pembayaran]').val('tunai');
        $('#modal-form [name=deskripsi]').focus();
    }

    function editForm(url) {
        $('#modal-form').modal('show');
        $('#modal-form .modal-title').text('Edit Biaya Operasional');

        $('#modal-form form')[0].reset();
        $('#modal-form form').attr('action', url);
        $('#modal-form [name=_method]').val('put');
        $('#modal-form [name=deskripsi]').focus();

        $.get(url)
            .done((response) => {
                $('#modal-form [name=tanggal_pengeluaran]').val(response.tanggal_pengeluaran);
                $('#modal-form [name=kategori_pengeluaran]').val(response.kategori_pengeluaran);
                $('#modal-form [name=deskripsi]').val(response.deskripsi);
                $('#modal-form [name=metode_pembayaran]').val(response.metode_pembayaran);
                $('#modal-form [name=nominal]').val(response.nominal);
            })
            .fail(() => {
                Swal.fire({
                    icon: 'error',
                    title: 'Gagal!',
                    text: 'Tidak dapat menampilkan data.',
                    confirmButtonText: 'OK',
                    confirmButtonColor: '#d33'
                });
            });
    }

    function deleteData(url) {
        Swal.fire({
            title: 'Hapus Data?',
            text: 'Yakin ingin menghapus data terpilih?',
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
                    })
                    .done(() => {
                        table.ajax.reload();

                        Swal.fire({
                            icon: 'success',
                            title: 'Berhasil!',
                            text: 'Data berhasil dihapus.',
                            timer: 1500,
                            showConfirmButton: false
                        });
                    })
                    .fail(() => {
                        Swal.fire({
                            icon: 'error',
                            title: 'Gagal!',
                            text: 'Tidak dapat menghapus data.',
                            confirmButtonText: 'OK',
                            confirmButtonColor: '#d33'
                        });
                    });
            }
        });
    }
</script>
@endpush
