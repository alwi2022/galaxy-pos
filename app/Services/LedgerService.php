<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class LedgerService
{
    public const ACCOUNTS = [
        '1101' => ['Kas Toko', 'asset', true],
        '1102' => ['Bank', 'asset', true],
        '1103' => ['QRIS', 'asset', true],
        '1201' => ['Piutang Usaha', 'asset', false],
        '1202' => ['Piutang Pihak Ketiga', 'asset', false],
        '1203' => ['Piutang Servis', 'asset', false],
        '1301' => ['Persediaan Barang', 'asset', false],
        '1401' => ['PPN Masukan', 'asset', false],
        '1501' => ['Biaya Dibayar di Muka', 'asset', false],
        '1601' => ['Aset Tetap', 'asset', false],
        '1602' => ['Akumulasi Penyusutan', 'contra_asset', false],
        '2101' => ['Utang Supplier', 'liability', false],
        '2102' => ['Utang Pinjaman', 'liability', false],
        '2201' => ['PPN Keluaran', 'liability', false],
        '2301' => ['Pendapatan Diterima di Muka', 'liability', false],
        '3101' => ['Modal Pemilik', 'equity', false],
        '3102' => ['Pengambilan dan Pembagian Hasil', 'contra_equity', false],
        '3103' => ['Laba Ditahan', 'equity', false],
        '4101' => ['Penjualan Barang', 'income', false],
        '4102' => ['Pendapatan Servis', 'income', false],
        '4103' => ['Pendapatan Lain', 'income', false],
        '4104' => ['Pendapatan Bunga', 'income', false],
        '4105' => ['Laba Penjualan Aset', 'income', false],
        '5101' => ['Harga Pokok Penjualan', 'expense', false],
        '6101' => ['Biaya Operasional', 'expense', false],
        '6102' => ['Pemakaian dan Selisih Stok', 'expense', false],
        '6103' => ['Biaya Penyusutan', 'expense', false],
        '6104' => ['Biaya Bunga', 'expense', false],
        '6105' => ['Rugi Pelepasan Aset', 'expense', false],
    ];

    public function initialize(int $branch): void
    {
        if (! DB::table('cabang')->where('id_cabang', $branch)->exists()) {
            $this->fail('Pilih cabang toko yang valid terlebih dahulu.');
        }
        foreach (self::ACCOUNTS as $code => [$name, $type, $cash]) {
            DB::table('ledger_accounts')->insertOrIgnore(['id_cabang' => $branch, 'code' => (string) $code, 'name' => $name, 'type' => $type, 'cash_account' => $cash, 'created_at' => now(), 'updated_at' => now()]);
        }
    }

    public function open(int $branch, string $date): void
    {
        if (DB::table('ledger_years')->where('id_cabang', $branch)->where('year', Carbon::parse($date)->year)->exists()) {
            $this->fail('Tahun buku sudah ditutup.');
        }
        $month = Carbon::parse($date)->startOfMonth()->toDateString();
        DB::table('ledger_periods')->insertOrIgnore(['id_cabang' => $branch, 'month' => $month, 'closed' => false, 'created_at' => now(), 'updated_at' => now()]);
        $period = DB::table('ledger_periods')->where('id_cabang', $branch)->where('month', $month)->lockForUpdate()->first();
        if ($period->closed) {
            $this->fail('Periode ' . $month . ' sudah ditutup.');
        }
        if (Carbon::parse($date)->startOfDay()->gt(Carbon::now('Asia/Jakarta')->startOfDay())) {
            $this->fail('Tanggal transaksi tidak boleh di masa depan.');
        }
    }

    public function post(int $branch, string $date, string $reference, string $description, array $lines): int
    {
        return DB::transaction(function () use ($branch, $date, $reference, $description, $lines) {
            $this->open($branch, $date);
            $this->initialize($branch);
            $existing = DB::table('ledger_entries')->where('reference', $reference)->first();
            if ($existing) {
                $this->fail('Referensi jurnal sudah dipakai.');
            }
            $debits = $credits = 0;
            $payload = [];
            foreach ($lines as [$code, $debit, $credit]) {
                if (! is_int($debit) || ! is_int($credit) || $debit < 0 || $credit < 0 || ($debit > 0 && $credit > 0)) {
                    $this->fail('Nilai jurnal harus rupiah bulat dan tidak negatif.');
                }
                if ($debit === 0 && $credit === 0) {
                    continue;
                }
                $account = DB::table('ledger_accounts')->where('id_cabang', $branch)->where('code', $code)->first();
                if (! $account) {
                    $this->fail('Akun jurnal tidak ditemukan pada cabang ini.');
                }
                $debits += $debit;
                $credits += $credit;
                $payload[] = ['account_id' => $account->id, 'debit' => $debit, 'credit' => $credit];
            }
            if ($debits !== $credits || $debits === 0) {
                $this->fail('Jurnal harus seimbang dan bernilai lebih dari nol.');
            }
            $id = DB::table('ledger_entries')->insertGetId(['id_cabang' => $branch, 'date' => $date, 'reference' => $reference, 'description' => $description, 'id_user' => auth()->id(), 'created_at' => now(), 'updated_at' => now()]);
            foreach ($payload as $line) {
                DB::table('ledger_lines')->insert(['entry_id' => $id] + $line);
            }
            return $id;
        });
    }

    public function reverse(int $branch, int $id, string $date): int
    {
        return DB::transaction(function () use ($branch, $id, $date) {
            $entry = DB::table('ledger_entries')->where('id_cabang', $branch)->where('id', $id)->lockForUpdate()->first();
            abort_unless($entry, 404);
            if ($entry->reversal_of || DB::table('ledger_entries')->where('reversal_of', $id)->exists()) {
                $this->fail('Jurnal sudah dibalik atau merupakan jurnal pembalik.');
            }
            if (Carbon::parse($date)->lt(Carbon::parse($entry->date))) {
                $this->fail('Tanggal pembalikan mendahului transaksi.');
            }
            $lines = DB::table('ledger_lines')->join('ledger_accounts', 'ledger_accounts.id', '=', 'ledger_lines.account_id')->where('entry_id', $id)->get()->map(fn ($l) => [$l->code, (int) $l->credit, (int) $l->debit])->all();
            $reversal = $this->post($branch, $date, 'REV-' . $id, 'Pembalikan: ' . $entry->description, $lines);
            DB::table('ledger_entries')->where('id', $reversal)->update(['reversal_of' => $id]);
            return $reversal;
        });
    }

    public function cashCode(string $method): string
    {
        return ['tunai' => '1101', 'transfer_bank' => '1102', 'qris' => '1103'][$method] ?? '1101';
    }

    public function balances(int $branch, string $end, ?string $start = null, bool $excludeClosing = false)
    {
        $sums = DB::table('ledger_lines as l')->join('ledger_entries as e', 'e.id', '=', 'l.entry_id')->where('e.id_cabang', $branch)->where('e.date', '<=', $end);
        if ($start) {
            $sums->where('e.date', '>=', $start);
        }
        if ($excludeClosing) {
            $sums->where('e.is_closing', false);
        }
        $sums = $sums->selectRaw('l.account_id, SUM(l.debit) as debit, SUM(l.credit) as credit')->groupBy('l.account_id');
        return DB::table('ledger_accounts as a')->leftJoinSub($sums, 's', 's.account_id', '=', 'a.id')->where('a.id_cabang', $branch)->orderBy('a.code')->selectRaw('a.*, COALESCE(s.debit,0) as debit, COALESCE(s.credit,0) as credit, COALESCE(s.debit,0)-COALESCE(s.credit,0) as balance')->get();
    }

    public function fail(string $message): void
    {
        throw ValidationException::withMessages(['transaksi' => $message]);
    }
}
