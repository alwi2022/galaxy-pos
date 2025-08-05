<!-- resources/views/servis/form.blade.php -->
<div class="modal fade" id="modal-form" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document">
        <form action="" method="post" class="form-horizontal">
            @csrf
            @method('post')

            @if (auth()->user()->level == 3)
                <input type="hidden" name="teknisi" value="{{ auth()->user()->name }}">
            @endif

            <div class="modal-content">
                <div class="modal-header">
                    <h4 class="modal-title">Form Servis</h4>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span>&times;</span></button>
                </div>
                <div class="modal-body">
                    @php $statusList = ['diproses', 'selesai', 'diambil']; @endphp

                    <div class="form-group row">
                        <label class="col-lg-3 control-label">Nama Pelanggan</label>
                        <div class="col-lg-8">
                            <input type="text" name="nama_pelanggan" class="form-control" required>
                        </div>
                    </div>
                    <div class="form-group row">
                        <label class="col-lg-3 control-label">Telepon</label>
                        <div class="col-lg-8">
                            <input type="text" name="telepon" class="form-control">
                        </div>
                    </div>
                    <div class="form-group row">
                        <label class="col-lg-3 control-label">Tipe Barang</label>
                        <div class="col-lg-8">
                            <input type="text" name="tipe_barang" class="form-control">
                        </div>
                    </div>
                    <div class="form-group row">
                        <label class="col-lg-3 control-label">Kerusakan</label>
                        <div class="col-lg-8">
                            <textarea name="kerusakan" class="form-control"></textarea>
                        </div>
                    </div>
                    <div class="form-group row">
                        <label class="col-lg-3 control-label">Biaya</label>
                        <div class="col-lg-8">
                            <input type="number" name="biaya_servis" class="form-control" value="0">
                        </div>
                    </div>

                    <div class="form-group row">
                        <label class="col-lg-3 control-label">Garansi (hari)</label>
                        <div class="col-lg-8">
                            <input type="number" name="garansi_hari" class="form-control" value="30">
                        </div>
                    </div>

                    <div class="form-group row">
                        <label class="col-lg-3 control-label">Status</label>
                        <div class="col-lg-8">
                            <select name="status" class="form-control">
                                @foreach($statusList as $status)
                                    <option value="{{ $status }}">{{ ucfirst($status) }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-flat btn-primary btn-sm"><i class="fa fa-save"></i> Simpan</button>
                    <button type="button" class="btn btn-flat btn-warning btn-sm" data-dismiss="modal"><i class="fa fa-arrow-circle-left"></i> Batal</button>
                </div>
            </div>
        </form>
    </div>
</div>
