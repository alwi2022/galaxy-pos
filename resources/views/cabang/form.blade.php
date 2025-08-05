<!-- resources/views/cabang/form.blade.php -->
<div class="modal fade" id="modal-cabang" tabindex="-1" role="dialog">
  <div class="modal-dialog">
      <form method="post" class="form-horizontal" data-toggle="validator">
        @csrf
        @method('POST') 
      <div class="modal-content">
        <div class="modal-header">
          <h3 class="modal-title">Tambah Cabang</h3>
        </div>
        <div class="modal-body">
            <div class="form-group row">
                <label class="col-md-3 control-label">Nama Cabang</label>
                <div class="col-md-6">
                    <input type="text" name="nama_cabang" class="form-control" required autofocus>
                </div>
            </div>
            <div class="form-group row">
                <label class="col-md-3 control-label">Alamat</label>
                <div class="col-md-6">
                    <input type="text" name="alamat" class="form-control">
                </div>
            </div>
            <div class="form-group row">
                <label class="col-md-3 control-label">Telepon</label>
                <div class="col-md-6">
                    <input type="text" name="telepon" class="form-control">
                </div>
            </div>
        </div>
        <div class="modal-footer">
          <button class="btn btn-primary">Simpan</button>
          <button type="button" class="btn btn-warning" data-dismiss="modal">Batal</button>
        </div>
      </div>
    </form>
  </div>
</div>
