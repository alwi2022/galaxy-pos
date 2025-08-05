<!-- resources/views/teknisi/dashboard.blade.php -->
@extends('layouts.master')

@section('title')
    Dashboard Teknisi
@endsection

@section('breadcrumb')
    @parent
    <li class="active">Dashboard Teknisi</li>
@endsection

@section('content')
<div class="row">
    <div class="col-lg-12">
        <div class="box">
            <div class="box-header with-border">
                <h4>Servis Saya</h4>
            </div>
            <div class="box-body table-responsive">
                <table class="table table-bordered">
                    <thead>
                        <tr>
                            <th>Kode</th>
                            <th>Barang</th>
                            <th>Kerusakan</th>
                            <th>Status</th>
                            <th>Tgl Masuk</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($servisSaya as $s)
                        <tr>
                            <td>{{ $s->kode_servis }}</td>
                            <td>{{ $s->tipe_barang }} - {{ $s->merk }}</td>
                            <td>{{ $s->kerusakan }}</td>
                            <td><span class="label label-info">{{ $s->status }}</span></td>
                            <td>{{ tanggal_indonesia($s->tanggal_masuk, true) }}</td>
                            <td>
                            <form action="{{ route('servis.update', $s->id_servis) }}" method="POST">
    @csrf
    @method('PUT')
    @if (auth()->user()->level == 3)
        <input type="hidden" name="teknisi" value="{{ auth()->user()->name }}">
    @endif
    <select name="status" onchange="updateStatus(this.form)" class="form-control input-sm">
    <option value="diproses" {{ $s->status == 'diproses' ? 'selected' : '' }}>Diproses</option>
    <option value="selesai" {{ $s->status == 'selesai' ? 'selected' : '' }}>Selesai</option>
    <option value="diambil" {{ $s->status == 'diambil' ? 'selected' : '' }}>Diambil</option>
    
</select>

</form>



</td>

                        </tr>
                        @empty
                        <tr><td colspan="5" class="text-center">Tidak ada data</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
function updateStatus(form) {
    const formData = new FormData(form);

    fetch(form.action, {
        method: 'POST',
        body: formData,
        headers: {
            'X-CSRF-TOKEN': '{{ csrf_token() }}',
            'X-Requested-With': 'XMLHttpRequest',
        }
    })
    .then(res => res.json())
    .then(data => {
        Swal.fire({
            icon: 'success',
            title: 'Berhasil!',
            text: data.message || 'Status berhasil diperbarui',
            timer: 1500,
            showConfirmButton: false
        });

        if (data.wa) {
            Swal.fire({
                title: 'Kirim WhatsApp?',
                text: 'Kirim notifikasi ke customer?',
                icon: 'info',
                showCancelButton: true,
                confirmButtonText: 'Ya, kirim',
                cancelButtonText: 'Tidak',
            }).then(result => {
                if (result.isConfirmed) {
                    window.open(data.wa, '_blank');
                }
            });
        }

        setTimeout(() => location.reload(), 1800);
    })
    .catch(err => {
        Swal.fire({
            icon: 'error',
            title: 'Gagal',
            text: 'Gagal memperbarui status',
        });
        console.error(err);
    });
}

</script>
@endpush
