<button type="button" class="btn btn-success btn-xs btn-flat" data-toggle="modal" data-target="#form-return"><i class="fa fa-exchange" aria-hidden="true"></i> Catat retur</button>
<div class="modal fade" id="form-return" tabindex="-1" role="dialog" aria-labelledby="title-return"><div class="modal-dialog modal-lg" role="document"><div class="modal-content">
<form method="post" action="{{ route('business.store', 'return') }}" id="return-form" class="business-form form-horizontal">@csrf
    <input type="hidden" name="_action" value="return"><input type="hidden" name="type" id="return-type"><input type="hidden" name="transaction_id" id="return-transaction">
    <div class="modal-header"><button type="button" class="close" data-dismiss="modal" aria-label="Tutup formulir"><span aria-hidden="true">&times;</span></button><h4 class="modal-title" id="title-return">Catat retur barang</h4></div>
    <div class="modal-body">
        <div class="form-group row"><label for="return-source" class="col-lg-3 control-label">Transaksi asal</label><div class="col-lg-7"><select id="return-source" class="form-control" required><option value="">Pilih transaksi</option>@foreach ($sales as $s)<option value="penjualan:{{ $s->id_penjualan }}">Penjualan #{{ $s->id_penjualan }} - {{ substr($s->posted_at,0,10) }}</option>@endforeach @foreach ($purchases as $p)<option value="pembelian:{{ $p->id_pembelian }}">Pembelian #{{ $p->id_pembelian }} - {{ substr($p->posted_at,0,10) }}</option>@endforeach</select></div></div>
        <div class="form-group row"><label for="return-detail" class="col-lg-3 control-label">Barang</label><div class="col-lg-7"><select id="return-detail" name="detail_id" class="form-control" required disabled><option value="">Pilih transaksi dahulu</option></select><p id="return-status" role="status" class="help-block"></p></div></div>
        <div class="form-group row"><label for="return-date" class="col-lg-3 control-label">Tanggal</label><div class="col-lg-7"><input id="return-date" class="form-control" type="date" name="date" required max="{{ now()->toDateString() }}" value="{{ old('date',now()->toDateString()) }}"></div></div>
        <div class="form-group row"><label for="return-quantity" class="col-lg-3 control-label">Jumlah retur</label><div class="col-lg-7"><input id="return-quantity" class="form-control" type="number" name="quantity" min="1" step="1" value="{{ old('quantity') }}" required></div></div>
        <div class="form-group row"><label for="return-restock" class="col-lg-3 control-label">Kondisi barang</label><div class="col-lg-7"><select id="return-restock" class="form-control" name="restock"><option value="1">Layak jual, masuk stok</option><option value="0">Rusak, tidak masuk stok</option></select></div></div>
        <div class="form-group row"><label for="return-method" class="col-lg-3 control-label">Pengembalian uang</label><div class="col-lg-7"><select id="return-method" class="form-control" name="method">@foreach(daftar_metode_pembayaran() as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach</select></div></div>
        <div class="form-group row"><label for="return-reason" class="col-lg-3 control-label">Alasan retur</label><div class="col-lg-7"><input id="return-reason" class="form-control" name="reason" maxlength="400" value="{{ old('reason') }}" required></div></div>
    </div>
    <div class="modal-footer"><button class="btn btn-primary btn-sm btn-flat" id="return-submit" type="submit" disabled><i class="fa fa-save" aria-hidden="true"></i> Simpan</button><button type="button" class="btn btn-warning btn-sm btn-flat" data-dismiss="modal"><i class="fa fa-arrow-circle-left" aria-hidden="true"></i> Batal</button></div>
</form></div></div></div>
@push('scripts')
<script>
document.getElementById('return-source').addEventListener('change', async function () {
    const select = document.getElementById('return-detail');
    const status = document.getElementById('return-status');
    const submit = document.getElementById('return-submit');
    select.replaceChildren(); select.disabled = true; submit.disabled = true;
    if (!this.value) { status.textContent = 'Pilih transaksi asal.'; return; }
    const [type,id] = this.value.split(':');
    document.getElementById('return-type').value = type;
    document.getElementById('return-transaction').value = id;
    document.getElementById('return-restock').disabled = type === 'pembelian';
    status.textContent = 'Memuat barang...';
    const source = this.value;
    try {
        const response = await fetch('{{ url('/usaha/detail') }}/' + type + '/' + id, {headers:{Accept:'application/json'}});
        if (!response.ok) throw new Error();
        const rows = await response.json();
        if (this.value !== source) return;
        rows.forEach(row => { if (row.jumlah > row.returned) select.add(new Option(row.nama_produk + ' - dapat diretur ' + (row.jumlah-row.returned),row['id_'+type+'_detail'])); });
        select.disabled = select.options.length === 0; submit.disabled = select.disabled;
        status.textContent = select.disabled ? 'Semua barang sudah diretur.' : '';
    } catch (_) { if(this.value === source) status.textContent = 'Gagal memuat barang. Pilih ulang transaksi untuk mencoba lagi.'; }
});
</script>
@endpush
