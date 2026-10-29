@extends('layouts.master')

@section('title')
    Daftar Supplier
@endsection

@section('breadcrumb')
    @parent
    <li>Master</li>
    <li class="active">Daftar Supplier</li>
@endsection

@section('content')
<div class="row">
    <div class="col-lg-12">
        <div class="box">
            <div class="box-header with-border">
                <div class="btn-group">
                    <button onclick="addForm('{{ route('supplier.store') }}')" class="btn btn-success btn-xs btn-flat"><i class="fa fa-plus-circle"></i> Tambah</button>
                    <button type="button" data-toggle="modal" data-target="#modal-import" class="btn btn-primary btn-xs btn-flat"><i class="fa fa-upload"></i> Import</button>
                    <a href="{{ route('supplier.export') }}" class="btn btn-default btn-xs btn-flat"><i class="fa fa-download"></i> Export</a>
                    <a href="{{ route('supplier.import_template') }}" class="btn btn-default btn-xs btn-flat"><i class="fa fa-file-excel-o"></i> Unduh Template</a>
                </div>
            </div>
            @if (session('import_errors') || $errors->any())
            <div class="box-body">
                <div class="alert alert-danger" style="margin-bottom: 0;">
                    @if (session('import_errors'))
                    <strong>Import dibatalkan, tidak ada supplier yang disimpan.</strong> Perbaiki {{ session('import_error_total') }} baris berikut lalu unggah ulang:
                    <ul>@foreach (session('import_errors') as $error)<li>{{ $error }}</li>@endforeach</ul>
                    @if (session('import_error_total') > count(session('import_errors')))<small>Menampilkan {{ count(session('import_errors')) }} dari {{ session('import_error_total') }} error.</small>@endif
                    @else
                    <ul style="margin-bottom: 0;">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
                    @endif
                </div>
            </div>
            @endif
            <div class="box-body table-responsive">
                <table class="table table-stiped table-bordered">
                    <thead>
                        <th width="5%">No</th>
                        <th>Nama</th>
                        <th>Telepon</th>
                        <th>Alamat</th>
                        <th width="15%"><i class="fa fa-cog"></i></th>
                    </thead>
                </table>
            </div>
        </div>
    </div>
</div>

@includeIf('supplier.form')
@includeIf('supplier.import')
@endsection

@push('scripts')
<script>
    let table;

    $(function () {
        table = $('.table').DataTable({
            responsive: true,
            processing: true,
            serverSide: true,
            autoWidth: false,
            ajax: {
                url: '{{ route('supplier.data') }}',
            },
            columns: [
                {data: 'DT_RowIndex', searchable: false, sortable: false},
                {data: 'nama'},
                {data: 'telepon'},
                {data: 'alamat'},
                {data: 'aksi', searchable: false, sortable: false},
            ]
        });

        $('#modal-form').validator().on('submit', function (e) {
            if (! e.preventDefault()) {
                $.post($('#modal-form form').attr('action'), $('#modal-form form').serialize())
                    .done((response) => {
                        $('#modal-form').modal('hide');
                        table.ajax.reload();

                        Swal.fire({
                            icon: 'success',
                            title: 'Berhasil!',
                            text: 'Data berhasil disimpan',
                            timer: 1500,
                            showConfirmButton: false
                        });
                    })
                    .fail((errors) => {
                        Swal.fire({
                            icon: 'error',
                            title: 'Gagal!',
                            text: 'Tidak dapat menyimpan data',
                            confirmButtonText: 'OK',
                            confirmButtonColor: '#d33'
                        });
                        return;
                    });
            }
        });
    });

    function addForm(url) {
        $('#modal-form').modal('show');
        $('#modal-form .modal-title').text('Tambah Supplier');

        $('#modal-form form')[0].reset();
        $('#modal-form form').attr('action', url);
        $('#modal-form [name=_method]').val('post');
        $('#modal-form [name=nama]').focus();
    }

    function editForm(url) {
        $('#modal-form').modal('show');
        $('#modal-form .modal-title').text('Edit Supplier');

        $('#modal-form form')[0].reset();
        $('#modal-form form').attr('action', url.replace('/edit', ''));
        $('#modal-form [name=_method]').val('put');
        $('#modal-form [name=nama]').focus();

        $.get(url)
            .done((response) => {
                $('#modal-form [name=nama]').val(response.nama);
                $('#modal-form [name=telepon]').val(response.telepon);
                $('#modal-form [name=alamat]').val(response.alamat);
            })
            .fail((errors) => {
                Swal.fire({
                    icon: 'error',
                    title: 'Gagal!',
                    text: 'Tidak dapat menampilkan data',
                    confirmButtonText: 'OK',
                    confirmButtonColor: '#d33'
                });
                return;
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
                    .done((response) => {
                        table.ajax.reload();

                        Swal.fire({
                            icon: 'success',
                            title: 'Berhasil!',
                            text: 'Data berhasil dihapus',
                            timer: 1500,
                            showConfirmButton: false
                        });
                    })
                    .fail((errors) => {
                        Swal.fire({
                            icon: 'error',
                            title: 'Gagal!',
                            text: 'Tidak dapat menghapus data',
                            confirmButtonText: 'OK',
                            confirmButtonColor: '#d33'
                        });
                        return;
                    });
            }
        });
    }
</script>
@endpush
