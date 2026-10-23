<div class="modal fade" id="modal-import" tabindex="-1" role="dialog" aria-labelledby="modal-import-title">
    <div class="modal-dialog modal-lg" role="document">
        <form action="{{ route('produk.import') }}" method="post" class="form-horizontal" enctype="multipart/form-data">
            @csrf

            <div class="modal-content">
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span
                            aria-hidden="true">&times;</span></button>
                    <h4 class="modal-title" id="modal-import-title">Import Produk</h4>
                </div>
                <div class="modal-body">
                    <div class="form-group row">
                        <label for="file_import" class="col-lg-3 control-label">File Excel / CSV</label>
                        <div class="col-lg-7">
                            <input type="file" name="file" id="file_import" class="form-control" accept=".xlsx,.xls,.csv" required>
                            <span class="help-block">Isi sesuai template. Kategori yang belum ada dibuat otomatis; stok awal dicatat ke kartu stok.</span>
                        </div>
                    </div>
                    <div class="form-group row">
                        <div class="col-lg-7 col-lg-offset-3">
                            <a href="{{ route('produk.import_template') }}" class="btn btn-default btn-xs btn-flat"><i class="fa fa-download"></i> Unduh Template</a>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-sm btn-flat btn-primary"><i class="fa fa-upload"></i> Import</button>
                    <button type="button" class="btn btn-sm btn-flat btn-warning" data-dismiss="modal"><i class="fa fa-arrow-circle-left"></i> Batal</button>
                </div>
            </div>
        </form>
    </div>
</div>
