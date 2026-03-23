<div class="modal fade" id="modal-form" tabindex="-1" role="dialog" aria-labelledby="modal-form">
    <div class="modal-dialog modal-lg" role="document">
        <form action="" method="post" class="form-horizontal">
            @csrf
            @method('post')

            <div class="modal-content">
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span
                            aria-hidden="true">&times;</span></button>
                    <h4 class="modal-title"></h4>
                </div>
                <div class="modal-body">
                    <div class="form-group row">
                        <label for="tanggal_pendapatan" class="col-lg-3 control-label">Tanggal</label>
                        <div class="col-lg-7">
                            <input type="date" name="tanggal_pendapatan" id="tanggal_pendapatan" class="form-control" required autofocus>
                            <span class="help-block with-errors"></span>
                        </div>
                    </div>
                    <div class="form-group row">
                        <label for="kategori_pendapatan" class="col-lg-3 control-label">Kategori</label>
                        <div class="col-lg-7">
                            <select name="kategori_pendapatan" id="kategori_pendapatan" class="form-control" required>
                                @foreach (daftar_kategori_pendapatan_lain() as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
                                @endforeach
                            </select>
                            <span class="help-block with-errors"></span>
                        </div>
                    </div>
                    <div class="form-group row">
                        <label for="deskripsi" class="col-lg-3 control-label">Deskripsi</label>
                        <div class="col-lg-7">
                            <textarea name="deskripsi" id="deskripsi" rows="3" class="form-control" required></textarea>
                            <span class="help-block with-errors"></span>
                        </div>
                    </div>
                    <div class="form-group row">
                        <label for="metode_pembayaran" class="col-lg-3 control-label">Metode</label>
                        <div class="col-lg-7">
                            <select name="metode_pembayaran" id="metode_pembayaran" class="form-control" required>
                                @foreach (daftar_metode_pembayaran() as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
                                @endforeach
                            </select>
                            <span class="help-block with-errors"></span>
                        </div>
                    </div>
                    <div class="form-group row">
                        <label for="nominal" class="col-lg-3 control-label">Nominal</label>
                        <div class="col-lg-7">
                            <input type="number" name="nominal" id="nominal" class="form-control" min="1" required>
                            <span class="help-block with-errors"></span>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-sm btn-flat btn-primary"><i class="fa fa-save"></i> Simpan</button>
                    <button type="button" class="btn btn-sm btn-flat btn-warning" data-dismiss="modal"><i class="fa fa-arrow-circle-left"></i> Batal</button>
                </div>
            </div>
        </form>
    </div>
</div>
