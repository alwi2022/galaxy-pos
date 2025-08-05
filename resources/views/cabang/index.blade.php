<!-- resources/views/cabang/index.blade.php -->
@extends('layouts.master') {{-- layout dashboard utama --}}

@section('title')
    Manajemen Cabang
@endsection

@section('content')
<div class="box">
    <div class="box-header with-border">
        <button onclick="tambahCabang()" class="btn btn-success btn-sm"><i class="fa fa-plus-circle"></i> Tambah Cabang</button>
    </div>
    <div class="box-body table-responsive">
        <table class="table table-bordered table-striped" id="table-cabang">
            <thead>
                <tr>
                    <th width="5%">No</th>
                    <th>Nama Cabang</th>
                    <th>Alamat</th>
                    <th>Telepon</th>
                    <th width="15%"><i class="fa fa-cog"></i></th>
                </tr>
            </thead>
        </table>
    </div>
</div>

@includeIf('cabang.form') {{-- form modal cabang --}}
@endsection
@push('scripts')
<script>
$(function () {
    table = $('#table-cabang').DataTable({
        processing: true,
        autoWidth: false,
        ajax: '{{ route('cabang.data') }}',
        columns: [
            { data: 'DT_RowIndex', searchable: false, sortable: false },
            { data: 'nama_cabang' },
            { data: 'alamat' },
            { data: 'telepon' },
            { data: 'aksi', searchable: false, sortable: false },
        ]
    });

    $('#modal-cabang').on('submit', 'form', function (e) {
        e.preventDefault();
        const form = $(this);
        const url = form.attr('action');
        const data = form.serialize();

        $.post(url, data)
            .done(response => {
                $('#modal-cabang').modal('hide');
                table.ajax.reload();

                Swal.fire({
                    icon: 'success',
                    title: 'Berhasil!',
                    text: response.message || 'Data berhasil disimpan',
                    timer: 1500,
                    showConfirmButton: false
                });
            })
            .fail(errors => {
                Swal.fire({
                    icon: 'error',
                    title: 'Gagal!',
                    text: 'Data gagal disimpan',
                });
            });
    });
});

function tambahCabang() {
    $('#modal-cabang').modal('show');
    $('#modal-cabang .modal-title').text('Tambah Cabang');
    $('#modal-cabang form')[0].reset();
    $('#modal-cabang form').attr('action', '{{ route('cabang.store') }}');
    $('#modal-cabang [name=_method]').val('post');
    $('#modal-cabang [name=nama_cabang]').focus();
}

function editForm(id) {
    $.get("{{ url('/cabang') }}/" + id + "/edit")
        .done(response => {
            $('#modal-cabang').modal('show');
            $('#modal-cabang .modal-title').text('Edit Cabang');
            $('#modal-cabang form')[0].reset();
            $('#modal-cabang form').attr('action', "{{ url('/cabang') }}/" + id);
            $('#modal-cabang [name=_method]').val('put');
            $('#modal-cabang [name=nama_cabang]').val(response.nama_cabang);
            $('#modal-cabang [name=alamat]').val(response.alamat);
            $('#modal-cabang [name=telepon]').val(response.telepon);
        })
        .fail(() => {
            Swal.fire({
                icon: 'error',
                title: 'Gagal!',
                text: 'Tidak dapat menampilkan data'
            });
        });
}

function deleteData(id) {
    Swal.fire({
        title: 'Hapus Data?',
        text: "Data yang dihapus tidak dapat dikembalikan!",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#3085d6',
        confirmButtonText: 'Ya, hapus!',
        cancelButtonText: 'Batal'
    }).then((result) => {
        if (result.isConfirmed) {
            $.post("{{ url('/cabang') }}/" + id, {
                '_method': 'delete',
                '_token': '{{ csrf_token() }}'
            })
            .done(response => {
                table.ajax.reload();

                Swal.fire({
                    icon: 'success',
                    title: 'Terhapus!',
                    text: response.message || 'Data berhasil dihapus',
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
</script>

@endpush
