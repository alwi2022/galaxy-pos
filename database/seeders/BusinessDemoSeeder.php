<?php

namespace Database\Seeders;

use App\Models\PendapatanLain;
use App\Models\Pengeluaran;
use App\Models\Servis;
use App\Models\User;
use App\Services\BusinessFinanceService;
use App\Services\BusinessTransactionService;
use App\Services\StockLedgerService;
use App\Support\TransactionCalculator;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class BusinessDemoSeeder extends Seeder
{
    public function run()
    {
        if (DB::table('ledger_entries')->exists()) {
            $this->command->info('Jurnal sudah terisi; demo dilewati agar tidak menggandakan transaksi.');
            return;
        }
        $end = Carbon::parse(env('DEMO_SEED_END', '2026-10-22'), 'Asia/Jakarta')->startOfDay();
        $start = $end->copy()->startOfMonth();
        $previous = Carbon::getTestNow();
        $previousUser = auth()->user();
        auth()->setUser(User::where('email', 'admin@gmail.com')->firstOrFail());
        try {
            DB::transaction(function () use ($start, $end) {
                $finance = app(BusinessFinanceService::class);
                $branch = (int) auth()->user()->id_cabang;
                Carbon::setTestNow($start->copy()->subYear()->endOfYear()->setTime(16,0));
                foreach ([['capital',5000000,'Modal'],['income',500000,'Pendapatan'],['expense',100000,'Beban'],['withdrawal',50000,'Pengambilan pemilik']] as [$type,$amount,$description]) {
                    $finance->money($branch,['date'=>now()->toDateString(),'type'=>$type,'cash'=>'1101','amount'=>$amount,'description'=>'Demo tahun sebelumnya - '.$description]);
                }
                Carbon::setTestNow($start->copy()->startOfYear()->setTime(8,0));
                $finance->closeYear($branch,$start->year-1);
                Carbon::setTestNow($start->copy()->subMonth()->day(1)->setTime(8, 0));
                $finance->money($branch, ['date' => now()->toDateString(), 'type' => 'capital', 'cash' => '1101', 'amount' => 40000000, 'description' => 'Demo · Modal awal kas toko']);
                $finance->money($branch, ['date' => now()->toDateString(), 'type' => 'capital', 'cash' => '1102', 'amount' => 60000000, 'description' => 'Demo · Modal awal rekening bank']);
                $finance->asset($branch, ['date' => now()->toDateString(), 'name' => 'Demo · Komputer kasir', 'amount' => 6000000, 'residual' => 600000, 'life_months' => 36, 'cash' => '1102']);
                $finance->defer($branch, ['date' => now()->toDateString(), 'type' => 'expense', 'description' => 'Demo · Sewa toko 12 bulan', 'amount' => 12000000, 'months' => 12, 'cash' => '1102']);
                $loan = $finance->loan($branch, ['date' => now()->toDateString(), 'party' => 'Demo · Pinjaman usaha bank', 'direction' => 'borrowed', 'due_date' => $end->copy()->addYear()->toDateString(), 'amount' => 10000000, 'cash' => '1102']);
                Carbon::setTestNow($start->copy()->setTime(8, 0));
                $finance->monthEnd($branch, $start->copy()->subMonth()->toDateString(), true);
                $category = DB::table('kategori')->where('nama_kategori', 'Mouse')->value('id_kategori');
                $products = [];
                foreach ([['Mouse Wireless', 45000, 75000], ['Keyboard USB', 60000, 95000], ['SSD 256GB', 220000, 285000], ['Flashdisk 32GB', 35000, 55000], ['Charger Laptop', 85000, 135000], ['Kabel HDMI', 25000, 45000]] as $i => [$name, $buy, $sell]) {
                    $id = DB::table('produk')->insertGetId(['id_kategori' => $category, 'kode_produk' => 'DEMO-' . sprintf('%03d', $i + 1), 'nama_produk' => $name . ' (Demo)', 'merk' => 'Demo', 'harga_beli' => $buy, 'harga_jual' => $sell, 'diskon' => 0, 'stok' => 0, 'inventory_value' => 0, 'id_cabang' => $branch, 'created_at' => now(), 'updated_at' => now()]);
                    $products[] = ['id' => $id, 'buy' => $buy, 'sell' => $sell];
                }
                $members = [];
                foreach (['Andi', 'Budi', 'Citra', 'Dewi', 'Eko', 'Fajar'] as $i => $name) {
                    $members[] = DB::table('member')->insertGetId(['kode_member' => 'DEMO-M' . $i, 'nama' => $name . ' (Demo)', 'telepon' => '000000000' . $i, 'alamat' => 'Alamat contoh', 'id_cabang' => $branch, 'created_at' => now(), 'updated_at' => now()]);
                }
                $suppliers = DB::table('supplier')->where('id_cabang', $branch)->pluck('id_supplier')->all();
                $sales = $purchases = [];
                for ($day = 1; $day <= $end->day; $day++) {
                    $date = $start->copy()->day($day);
                    Carbon::setTestNow($date->copy()->setTime(8, 0));
                    if (in_array($day, [1, 8, 15, 22], true)) {
                        $purchases[] = $this->createTransaction('pembelian', $products, 25, $suppliers[$day % count($suppliers)], $day % 2 === 0, $day);
                    }
                    for ($i = 0; $i < 3 + $day % 3; $i++) {
                        Carbon::setTestNow($date->copy()->setTime(10 + $i, 0));
                        $sales[] = $this->createTransaction('penjualan', [$products[($day + $i) % 6], $products[($day + $i + 2) % 6]], 1, $members[($day + $i) % 6], ($day + $i) % 4 === 0, $day + $i);
                    }
                    Carbon::setTestNow($date->copy()->setTime(16, 0));
                    Pengeluaran::create(['id_cabang' => $branch, 'tanggal_pengeluaran' => $date->toDateString(), 'kategori_pengeluaran' => $day === 5 ? 'listrik' : 'operasional_lainnya', 'deskripsi' => 'Demo · ' . ($day === 5 ? 'Tagihan listrik' : 'Transportasi dan kebersihan'), 'nominal' => $day === 5 ? 450000 : 15000 + $day % 4 * 5000, 'metode_pembayaran' => 'tunai']);
                    if ($day % 4 === 0) {
                        PendapatanLain::create(['id_cabang' => $branch, 'id_user' => auth()->id(), 'tanggal_pendapatan' => $date->toDateString(), 'kategori_pendapatan' => 'penjualan_kardus_bekas', 'deskripsi' => 'Demo · Penjualan kardus bekas', 'nominal' => 30000 + $day * 1000, 'metode_pembayaran' => 'tunai']);
                    }
                    if ($day % 2 === 0) {
                        $finished = $day < $end->day - 1;
                        $service = Servis::create(['id_cabang' => $branch, 'id_user' => auth()->id(), 'kode_servis' => 'DEMO-S' . sprintf('%02d', $day), 'id_member' => $members[$day % 6], 'nama_pelanggan' => 'Pelanggan Demo ' . $day, 'telepon' => '0000000000', 'keluhan' => 'Laptop panas dan lambat', 'tipe_barang' => 'Laptop', 'merk' => 'Demo', 'kerusakan' => 'Perlu perawatan sistem dan pembersihan', 'status' => $finished ? 'selesai' : 'diproses', 'biaya_servis' => $finished ? 150000 : 0, 'teknisi' => 'Teknisi 1', 'garansi_hari' => 7, 'tanggal_masuk' => $date->copy()->setTime(9, 0), 'tanggal_selesai' => $finished ? now() : null]);
                        DB::table('servis_logs')->insert(['id_servis' => $service->id_servis, 'id_user' => auth()->id(), 'status' => $service->status, 'created_at' => now(), 'updated_at' => now()]);
                        if ($finished && $day%6!==0) {
                            app(\App\Services\ServicePaymentService::class)->pay($branch,$service->id_servis,['date'=>now()->toDateString(),'cash'=>['1101','1102','1103'][$day%3],'amount'=>$day%4===0?50000:150000,'description'=>'Demo - pembayaran servis']);
                        }
                    }
                    if ($day === 10) {
                        app(StockLedgerService::class)->adjustment($branch, $products[0]['id'], 2, 'internal', 'Demo · Mouse untuk komputer administrasi', now()->toDateTimeString());
                    }
                    if ($day === 12) {
                        $this->demoReturn('penjualan', $sales[0], $branch);
                    }
                    if ($day === 16) {
                        $this->demoReturn('pembelian', $purchases[0], $branch);
                    }
                    if ($day === 18) {
                        $finance->repay($branch, $loan, ['date' => $date->toDateString(), 'principal' => 1000000, 'interest' => 100000, 'cash' => '1102']);
                        $finance->money($branch, ['date' => $date->toDateString(), 'type' => 'transfer', 'cash' => '1101', 'destination' => '1102', 'amount' => 1000000, 'description' => 'Demo · Setoran kas ke bank']);
                    }
                    if ($day === 20) {
                        $creditSale = DB::table('penjualan')->where('sisa', '>', 0)->orderBy('id_penjualan')->first();
                        if ($creditSale) {
                            \App\Models\PenjualanPembayaran::create(['id_penjualan' => $creditSale->id_penjualan, 'nominal' => $creditSale->sisa, 'metode_pembayaran' => 'transfer_bank', 'keterangan' => 'Demo · Pelunasan piutang', 'id_user' => auth()->id(), 'id_cabang' => $branch]);
                            \App\Models\Penjualan::find($creditSale->id_penjualan)->syncStatusPembayaran();
                        }
                    }
                }
                $this->seedBankAndTax($branch,$start,$end);
                $this->command->info('Demo lengkap dibuat: ' . $start->toDateString() . ' s/d ' . $end->toDateString() . '. Bulan sebelumnya berisi modal, aset, sewa di muka, dan contoh periode tertutup.');
            });
        } finally {
            Carbon::setTestNow($previous);
            if ($previousUser) { auth()->setUser($previousUser); } else { auth()->logout(); }
        }
    }

    private function createTransaction(string $type, array $products, int $quantity, int $party, bool $credit, int $sequence): int
    {
        $sale = $type === 'penjualan';
        $price = $sale ? 'sell' : 'buy';
        $subtotal = array_sum(array_column($products, $price)) * $quantity;
        $discount = $sequence % 5 === 0 ? 5 : 0;
        $totals = app(TransactionCalculator::class)->calculateFromSubtotal($subtotal, $discount, 11);
        $total = $totals['grand_total'];
        $initial = $credit ? (int) floor($total / 2) : $total;
        $method = ['tunai', 'transfer_bank', 'qris'][$sequence % 3];
        $data = ['id_cabang' => auth()->user()->id_cabang, 'total_item' => count($products) * $quantity, 'total_harga' => $subtotal, 'diskon' => $discount, 'ppn_persen' => 11, 'ppn_nominal' => $totals['ppn_nominal'], 'bayar' => $total, 'skema_pembayaran' => $credit ? 'kredit' : 'langsung', 'metode_pembayaran' => $method, 'dibayar' => 0, 'sisa' => $total, 'status_pembayaran' => 'belum_bayar', 'jatuh_tempo' => $credit ? now()->addDays(7)->toDateString() : null, 'created_at' => now(), 'updated_at' => now()];
        $data += $sale ? ['id_member' => $party, 'id_user' => auth()->id(), 'diterima' => $initial] : ['id_supplier' => $party];
        $id = DB::table($type)->insertGetId($data);
        foreach ($products as $product) {
            $detail = ['id_' . $type => $id, 'id_produk' => $product['id'], $sale ? 'harga_jual' : 'harga_beli' => $product[$price], 'jumlah' => $quantity, 'subtotal' => $product[$price] * $quantity, 'created_at' => now(), 'updated_at' => now()];
            if ($sale) { $detail['diskon'] = 0; }
            DB::table($type . '_detail')->insert($detail);
        }
        app(BusinessTransactionService::class)->post($type, $id, auth()->user()->id_cabang);
        $paymentClass = $sale ? \App\Models\PenjualanPembayaran::class : \App\Models\PembelianPembayaran::class;
        $paymentClass::create(['id_' . $type => $id, 'nominal' => $initial, 'metode_pembayaran' => $method, 'keterangan' => 'Demo · Pembayaran awal', 'id_user' => auth()->id(), 'id_cabang' => auth()->user()->id_cabang]);
        $modelClass = $sale ? \App\Models\Penjualan::class : \App\Models\Pembelian::class;
        $modelClass::find($id)->syncStatusPembayaran();
        return $id;
    }

    private function demoReturn(string $type, int $id, int $branch): void
    {
        $detail = DB::table($type . '_detail')->where('id_' . $type, $id)->first();
        app(BusinessTransactionService::class)->returnItem($branch, ['type' => $type, 'transaction_id' => $id, 'detail_id' => $detail->{'id_' . $type . '_detail'}, 'quantity' => 1, 'date' => now()->toDateString(), 'restock' => true, 'method' => 'tunai', 'reason' => 'Demo · Barang tidak sesuai kebutuhan']);
    }

    private function seedBankAndTax(int $branch, Carbon $start, Carbon $end): void
    {
        $lines=DB::table('ledger_lines as l')->join('ledger_entries as e','e.id','=','l.entry_id')->join('ledger_accounts as a','a.id','=','l.account_id')->where('e.id_cabang',$branch)->where('a.code','1102')->whereBetween('e.date',[$start->toDateString(),$end->toDateString()])->orderBy('l.id')->select('l.*','e.date')->limit(2)->get();
        $path=tempnam(sys_get_temp_dir(),'galaxy-bank-');
        try {
            $stream=fopen($path,'w');
            fputcsv($stream,['date','reference','description','amount']);
            foreach($lines as $i=>$line) { fputcsv($stream,[$line->date,'DEMO-BANK-'.($i+1),'Demo - transaksi rekening',(int)$line->debit-(int)$line->credit]); }
            fputcsv($stream,[$end->toDateString(),'DEMO-BANK-FEE','Demo - biaya administrasi bank',-6500]);
            fputcsv($stream,[$end->toDateString(),'DEMO-BANK-OPEN','Demo - transaksi menunggu pencocokan',-12500]);
            fclose($stream);
            $bank=app(\App\Services\BankReconciliationService::class);
            $bank->import($branch,'1102',$path);
            foreach($lines as $i=>$line) { $id=DB::table('bank_statement_lines')->where('id_cabang',$branch)->where('reference','DEMO-BANK-'.($i+1))->value('id'); $bank->match($branch,$id,$line->id); }
            $bank->adjust($branch,DB::table('bank_statement_lines')->where('id_cabang',$branch)->where('reference','DEMO-BANK-FEE')->value('id'),'6101');
        } finally { if(is_file($path)) unlink($path); }
        DB::table('tax_profiles')->insert(['id_cabang'=>$branch,'name'=>'Galaxy Computer (Demo)','npwp'=>'0000000000000000','nitku'=>'0000000000000000000000','address'=>'Alamat contoh, bukan identitas wajib pajak nyata','pkp'=>false,'created_at'=>now(),'updated_at'=>now()]);
        foreach(['penjualan','pembelian'] as $type) {
            foreach(DB::table($type)->where('id_cabang',$branch)->whereNotNull('posted_at')->get() as $source) {
                $id=$source->{'id_'.$type};
                DB::table('tax_invoices')->insert(['id_cabang'=>$branch,'source_type'=>$type,'source_id'=>$id,'date'=>substr($source->posted_at,0,10),'invoice_number'=>'DEMO-'.strtoupper($type).'-'.sprintf('%05d',$id),'party_name'=>'Lawan transaksi Demo '.$id,'party_npwp'=>'0000000000000000','creditable'=>$type==='pembelian','created_at'=>now(),'updated_at'=>now()]);
            }
        }
        app(\App\Services\TaxReportingService::class)->prepare($branch,$start->toDateString());
    }
}
