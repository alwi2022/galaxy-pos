<!-- resources/views/servis/sparepart.blade.php -->
<div class="modal fade" id="modal-sparepart" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h4 class="modal-title">Sparepart Servis <span id="sp-kode" class="label label-success"></span></h4>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span>&times;</span></button>
            </div>
            <div class="modal-body">
                <div id="sp-alert-selesai" class="alert alert-info" style="display:none">
                    Servis sudah selesai/diambil. Sparepart tidak dapat diubah lagi.
                </div>

                {{-- Form tambah sparepart --}}
                <div id="sp-form-wrap" class="form-inline" style="margin-bottom:10px">
                    <div class="input-group">
                        <input type="text" id="sp-kode-produk" class="form-control" placeholder="Kode / cari produk" style="width:220px">
                        <span class="input-group-btn">
                            <button class="btn btn-info btn-flat" type="button" onclick="tampilProdukSparepart()"><i class="fa fa-search"></i></button>
                        </span>
                    </div>
                    <input type="number" id="sp-jumlah" class="form-control" value="1" min="1" style="width:90px" placeholder="Qty">
                    <button class="btn btn-success btn-flat" type="button" onclick="tambahSparepart()"><i class="fa fa-plus-circle"></i> Tambah Sparepart</button>
                </div>

                <div class="table-responsive">
                    <table class="table table-bordered table-striped table-sparepart" style="width:100%">
                        <thead>
                            <tr>
                                <th width="5%">No</th>
                                <th>Kode</th>
                                <th>Nama Barang</th>
                                <th class="text-right">Harga</th>
                                <th class="text-right">Qty</th>
                                <th class="text-right">Subtotal</th>
                                <th width="8%"><i class="fa fa-cog"></i></th>
                            </tr>
                        </thead>
                    </table>
                </div>

                {{-- Ringkasan: Sub Total Jasa + Sub Total Sparepart + Total --}}
                <table class="table" style="margin-top:10px">
                    <tr>
                        <td class="text-right" style="border:none"><strong>Sub Total Jasa</strong></td>
                        <td class="text-right" style="border:none;width:180px"><span id="sp-jasa">Rp. 0</span></td>
                    </tr>
                    <tr>
                        <td class="text-right" style="border:none"><strong>Sub Total Sparepart</strong></td>
                        <td class="text-right" style="border:none"><span id="sp-sparepart">Rp. 0</span></td>
                    </tr>
                    <tr>
                        <td class="text-right" style="border:none"><strong>TOTAL TAGIHAN</strong></td>
                        <td class="text-right" style="border:none"><strong><span id="sp-total">Rp. 0</span></strong></td>
                    </tr>
                </table>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-flat btn-default btn-sm" data-dismiss="modal">Tutup</button>
            </div>
        </div>
    </div>
</div>

{{-- Modal pemilih produk untuk sparepart --}}
<div class="modal fade" id="modal-produk-sparepart" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h4 class="modal-title">Pilih Produk</h4>
                <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <div class="modal-body table-responsive">
                <table class="table table-bordered table-striped table-produk-sparepart" style="width:100%">
                    <thead>
                        <tr>
                            <th>Kode</th>
                            <th>Nama</th>
                            <th class="text-right">Harga Jual</th>
                            <th class="text-right">Stok</th>
                            <th></th>
                        </tr>
                    </thead>
                </table>
            </div>
        </div>
    </div>
</div>
