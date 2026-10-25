<div class="modal fade" id="modal-cari-nota" tabindex="-1" role="dialog" aria-labelledby="judul-cari-nota">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Tutup"><span aria-hidden="true">&times;</span></button>
                <h4 class="modal-title" id="judul-cari-nota">Nota <span data-nota="nomor"></span></h4>
            </div>
            <div class="modal-body">
                <div class="row">
                    <div class="col-sm-6">
                        <table class="table table-condensed" style="margin-bottom: 8px;">
                            <tr><th width="35%">Tanggal</th><td data-nota="tanggal"></td></tr>
                            <tr><th>Cara Bayar</th><td data-nota="cara_bayar"></td></tr>
                            <tr><th>Kasir</th><td data-nota="kasir"></td></tr>
                            <tr class="nota-jatuh-tempo"><th>Jatuh Tempo</th><td data-nota="jatuh_tempo"></td></tr>
                        </table>
                    </div>
                    <div class="col-sm-6">
                        <table class="table table-condensed" style="margin-bottom: 8px;">
                            <tr><th width="35%">Customer</th><td data-nota="customer" style="font-weight: bold;"></td></tr>
                            <tr><th>Alamat</th><td data-nota="alamat"></td></tr>
                            <tr><th>Telp</th><td data-nota="telepon"></td></tr>
                        </table>
                    </div>
                </div>
                <table class="table table-bordered table-condensed">
                    <thead><tr><th width="5%">No</th><th>Nama Barang</th><th class="text-right">Qty</th><th class="text-right">Harga</th><th class="text-right">Disc</th><th class="text-right">Jumlah</th></tr></thead>
                    <tbody class="nota-items"></tbody>
                    <tfoot>
                        <tr><th colspan="5" class="text-right">Total</th><th class="text-right" data-nota="total"></th></tr>
                        <tr><th colspan="5" class="text-right">Dibayar</th><th class="text-right" data-nota="dibayar"></th></tr>
                        <tr><th colspan="5" class="text-right">Sisa</th><th class="text-right" data-nota="sisa"></th></tr>
                        <tr class="nota-retur warning"><th colspan="5" class="text-right">Sudah diretur</th><th class="text-right" data-nota="retur"></th></tr>
                    </tfoot>
                </table>
                <p class="text-muted" style="margin: 0;">Status: <span data-nota="status"></span></p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-primary btn-flat btn-cetak-nota"><i class="fa fa-print"></i> Cetak</button>
                <button type="button" class="btn btn-warning btn-flat" data-dismiss="modal"><i class="fa fa-arrow-circle-left"></i> Tutup</button>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
$(function () {
    let notaDitemukan = null;

    $('.form-cari-nota').on('submit', function (e) {
        e.preventDefault();
        const nomor = $('#cari_nomor_nota').val().trim();
        if (!nomor) return $('#cari_nomor_nota').focus();

        $.get('{{ route('transaksi.cari_nota') }}', { nomor: nomor })
            .done(function (nota) {
                notaDitemukan = nota;
                $('#modal-cari-nota [data-nota]').each(function () {
                    $(this).text(nota[this.dataset.nota] ?? '');
                });
                $('.nota-jatuh-tempo').toggle(!!nota.jatuh_tempo);
                $('.nota-retur').toggle(!!nota.retur);
                $('.nota-items').empty().append(nota.items.map(function (item, i) {
                    return $('<tr>').append(
                        $('<td>').text(i + 1),
                        $('<td>').text(item.nama),
                        $('<td class="text-right">').text(item.jumlah),
                        $('<td class="text-right">').text(item.harga),
                        $('<td class="text-right">').text(item.diskon > 0 ? item.diskon + '%' : '-'),
                        $('<td class="text-right">').text(item.subtotal)
                    );
                }));
                $('#modal-cari-nota').modal('show');
            })
            .fail(function (xhr) {
                alert(xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : 'Nota tidak dapat dicari.');
                $('#cari_nomor_nota').select();
            });
    });

    $('#modal-cari-nota').on('shown.bs.modal', function () {
        $(this).find('.btn-cetak-nota').focus();
    }).on('hidden.bs.modal', function () {
        $('#cari_nomor_nota').val('');
        $('#kode_produk').focus();
    });

    $('.btn-cetak-nota').on('click', function () {
        if (!notaDitemukan) return;
        const besar = notaDitemukan.jenis_nota === 'besar';
        window.open(notaDitemukan.cetak, 'cetak_nota', 'scrollbars=yes,width=' + (besar ? 900 : 625) + ',height=' + (besar ? 675 : 500));
    });
});
</script>
@endpush
