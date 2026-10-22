<?php

$column = fn ($field, $label, $format = 'text', $sort = null, $options = []) => compact('field', 'label', 'format', 'sort', 'options');
$table = fn ($module, $records, $title, $columns, $rowAction = null) => compact('module', 'records', 'title', 'columns', 'rowAction');

return [
    'stock_page' => $table('stok', 'movements', 'Kartu Stok', [
        $column('occurred_at','Tanggal','datetime','m.occurred_at'), $column('nama_produk','Produk','text','p.nama_produk'), $column('reference','Referensi','reference','m.reference'), $column('reason','Keperluan','text','m.reason'), $column('quantity','Perubahan','quantity','m.quantity'), $column('balance','Saldo','number','m.balance'), $column('unit_cost','Biaya / Unit','money','m.unit_cost'),
    ]),
    'return_page' => $table('retur', 'returns', 'Retur Barang', [
        $column('date','Tanggal','date','r.date'), $column('type','Sumber','text','r.type'), $column('transaction_id','No. Transaksi','number','r.transaction_id'), $column('nama_produk','Produk','text','p.nama_produk'), $column('quantity','Jumlah','number','r.quantity'), $column('amount','Nilai Retur','money','r.amount'), $column('refund','Uang Kembali','money','r.refund'), $column('','Kondisi','retur-condition'), $column('reason','Alasan','text','r.reason'),
    ]),
    'loan_page' => $table('pinjaman', 'loanRows', 'Pinjaman', [
        $column('date','Tanggal','date','date'), $column('party','Pihak','text','party'), $column('direction','Jenis','enum','direction',['borrowed'=>'Utang toko','lent'=>'Piutang toko']), $column('due_date','Jatuh Tempo','date','due_date'), $column('principal','Pokok','money','principal'), $column('outstanding','Sisa','money','outstanding'),
    ]),
    'asset_page' => $table('aset', 'assetRows', 'Inventaris', [
        $column('name','Aset','text','name'), $column('purchased_at','Tanggal Beli','date','purchased_at'), $column('cost','Harga Perolehan','money','cost'), $column('depreciated','Penyusutan','money','depreciated'), $column('residual','Nilai Sisa','money','residual'), $column('life_months','Umur (Bulan)','number','life_months'), $column('','Nilai Buku','asset-value'), $column('disposed_at','Tanggal Pelepasan','date','disposed_at'),
    ]),
    'deferral_page' => $table('akrual', 'deferralRows', 'Pembayaran di Muka', [
        $column('description','Keterangan','text','description'), $column('type','Jenis','enum','type',['expense'=>'Biaya','income'=>'Pendapatan']), $column('start_date','Mulai','date','start_date'), $column('months','Bulan','number','months'), $column('amount','Total','money','amount'), $column('recognized','Diakui','money','recognized'), $column('','Sisa','deferral-balance'),
    ]),
    'service_page' => $table('piutang-servis', 'serviceRows', 'Tagihan Servis', [
        $column('kode_servis','Kode Servis','text','kode_servis'), $column('nama_pelanggan','Pelanggan','text','nama_pelanggan'), $column('tipe_barang','Barang','text','tipe_barang'), $column('tanggal_selesai','Tanggal Tagihan','date','tanggal_selesai'), $column('jatuh_tempo','Jatuh Tempo','date','jatuh_tempo'), $column('biaya_servis','Tagihan','money','biaya_servis'), $column('dibayar','Dibayar','money','dibayar'), $column('sisa','Sisa','money','sisa'), $column('status_pembayaran','Status','payment','status_pembayaran'), $column('','Tindakan','actions'),
    ], 'service'),
    'service_payment_page' => $table('piutang-servis', 'servicePayments', 'Riwayat Pembayaran', [
        $column('date','Tanggal','date','p.date'), $column('kode_servis','Kode Servis','text','s.kode_servis'), $column('nama_pelanggan','Pelanggan','text','s.nama_pelanggan'), $column('description','Keterangan','text','p.description'), $column('nominal','Nominal','money','p.nominal'), $column('cash_code','Akun','text','p.cash_code'), $column('','Status','payment-state'),
    ]),
    'bank_page' => $table('rekonsiliasi', 'bankRows', 'Rekening Koran', [
        $column('date','Tanggal','date','b.date'), $column('reference','Referensi Bank','text','b.reference'), $column('description','Keterangan','text','b.description'), $column('name','Akun','text','a.name'), $column('amount','Masuk / Keluar','money','b.amount'), $column('ledger_line_id','Status','matched','b.ledger_line_id'), $column('','Tindakan','actions'),
    ], 'bank'),
    'tax_page' => $table('pajak', 'taxReturns', 'Masa PPN', [
        $column('month','Masa','month','month'), $column('revision','Revisi','number','revision'), $column('output_tax','Keluaran','money','output_tax'), $column('input_tax','Masukan','money','input_tax'), $column('carry_in','Kompensasi Masuk','money','carry_in'), $column('payable','Kurang Bayar','money','payable'), $column('carry_out','Kompensasi Keluar','money','carry_out'), $column('status','Status','enum','status',['draft'=>'Draft','filed'=>'Bukti lapor dicatat']), $column('receipt','Bukti Lapor','text','receipt'), $column('','Unduh','actions'),
    ], 'tax'),
    'invoice_page' => $table('pajak', 'taxInvoices', 'Register Faktur', [
        $column('date','Tanggal','date','date'), $column('source_type','Sumber','text','source_type'), $column('source_id','No. Transaksi','number','source_id'), $column('invoice_number','Nomor Faktur','text','invoice_number'), $column('party_name','Lawan Transaksi','text','party_name'), $column('party_npwp','NPWP','text','party_npwp'), $column('creditable','Dikreditkan','enum','creditable',[0=>'Tidak',1=>'Ya']),
    ]),
    'tax_payment_page' => $table('pajak', 'taxPayments', 'Setoran PPN', [
        $column('date','Tanggal','date','p.date'), $column('receipt','Bukti Setoran','text','p.receipt'), $column('revision','Revisi','number','r.revision'), $column('cash_code','Akun','text','p.cash_code'), $column('amount','Nominal','money','p.amount'),
    ]),
    'journal_page' => $table(['kas','akuntansi'], 'entries', 'Jurnal & Buku Besar', [
        $column('date','Tanggal','date','e.date'), $column('id','ID Jurnal','number','e.id'), $column('reference','Referensi','reference','e.reference'), $column('description','Keterangan','text','e.description'), $column('name','Akun','text','a.name'), $column('debit','Debit','money','l.debit'), $column('credit','Kredit','money','l.credit'),
    ]),
    'reconcile_page' => $table('kas', 'reconciliations', 'Pencocokan Saldo', [
        $column('date','Tanggal','date','r.date'), $column('name','Akun','text','a.name'), $column('statement_balance','Saldo Fisik / Bank','money','r.statement_balance'), $column('system_balance','Saldo Aplikasi','money','r.system_balance'), $column('difference','Selisih','money'), $column('note','Keterangan','text','r.note'),
    ]),
];
