<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class BusinessFinanceService
{
    public function closeYear(int $branch, int $year): void
    {
        DB::transaction(function () use ($branch, $year) {
            $ledger = app(LedgerService::class);
            $ledger->initialize($branch);
            DB::table('cabang')->where('id_cabang', $branch)->lockForUpdate()->first();
            if ($year >= now()->year || $year < 2000) {
                $ledger->fail('Tutup tahun hanya untuk tahun buku yang sudah berakhir.');
            }
            if (DB::table('ledger_years')->where('id_cabang', $branch)->where('year', $year)->exists()) {
                $ledger->fail('Tahun buku sudah ditutup; jurnal tidak digandakan.');
            }
            $earlierYears = DB::table('ledger_entries')->where('id_cabang', $branch)->where('date', '<', $year . '-01-01')->selectRaw('SUBSTR(date,1,4) as year')->distinct()->pluck('year');
            $closedYears = DB::table('ledger_years')->where('id_cabang', $branch)->pluck('year');
            if ($earlierYears->diff($closedYears)->isNotEmpty()) {
                $ledger->fail('Tutup tahun buku terdahulu terlebih dahulu.');
            }
            foreach (['penjualan', 'pembelian'] as $table) {
                if (DB::table($table)->where('id_cabang', $branch)->whereNull('posted_at')->whereNull('cancelled_at')->where('total_item', '>', 0)->whereBetween('created_at', [$year . '-01-01 00:00:00', $year . '-12-31 23:59:59'])->exists()) {
                    $ledger->fail('Masih ada transaksi barang yang belum difinalkan pada tahun ini.');
                }
            }
            for ($month = 1; $month <= 12; $month++) {
                $date = sprintf('%04d-%02d-01', $year, $month);
                $closed = DB::table('ledger_periods')->where('id_cabang', $branch)->where('month', $date)->value('closed');
                if (! $closed) {
                    $this->monthEnd($branch, $date, $month < 12);
                }
            }
            $december = DB::table('ledger_periods')->where('id_cabang', $branch)->where('month', $year . '-12-01')->lockForUpdate()->first();
            if ($december->closed) {
                DB::table('ledger_periods')->where('id', $december->id)->update(['closed' => false]);
            }
            $balances = $ledger->balances($branch, $year . '-12-31');
            $profit = -(int) $balances->whereIn('type', ['income', 'expense'])->sum('balance');
            $lines = [];
            $sum = 0;
            foreach ($balances as $balance) {
                if (! in_array($balance->type, ['income', 'expense'], true) && $balance->code !== '3102') {
                    continue;
                }
                $value = (int) $balance->balance;
                if ($value !== 0) {
                    $lines[] = [$balance->code, max(-$value, 0), max($value, 0)];
                    $sum += $value;
                }
            }
            $entry = null;
            if ($lines) {
                $lines[] = ['3103', max($sum, 0), max(-$sum, 0)];
                $entry = $ledger->post($branch, $year . '-12-31', 'YEAR-CLOSE-' . $branch . '-' . $year, 'Penutupan tahun buku ' . $year . ' ke laba ditahan', $lines);
                DB::table('ledger_entries')->where('id', $entry)->update(['is_closing' => true]);
            }
            DB::table('ledger_years')->insert(['id_cabang' => $branch, 'year' => $year, 'entry_id' => $entry, 'profit' => $profit, 'id_user' => auth()->id(), 'created_at' => now(), 'updated_at' => now()]);
            DB::table('ledger_periods')->where('id_cabang', $branch)->where('month', $year . '-12-01')->update(['closed' => true, 'updated_at' => now()]);
        });
    }

    public function money(int $branch, array $data): int
    {
        $ledger = app(LedgerService::class);
        $amount = (int) $data['amount'];
        $cash = $data['cash'];
        $this->cashAccount($branch, $cash);
        $type = $data['type'];
        $other = ['capital' => '3101', 'withdrawal' => '3102', 'distribution' => '3102', 'income' => '4103', 'expense' => '6101', 'opening' => '3101', 'tax' => '2201'][$type] ?? null;
        if ($type === 'transfer') {
            $this->cashAccount($branch, $data['destination']);
            if ($cash === $data['destination']) {
                $ledger->fail('Akun asal dan tujuan harus berbeda.');
            }
            $lines = [[$data['destination'], $amount, 0], [$cash, 0, $amount]];
        } else {
            if (! $other) {
                $ledger->fail('Jenis transaksi tidak valid.');
            }
            $incoming = in_array($type, ['capital', 'income', 'opening'], true);
            $lines = $incoming ? [[$cash, $amount, 0], [$other, 0, $amount]] : [[$other, $amount, 0], [$cash, 0, $amount]];
        }
        return $ledger->post($branch, $data['date'], 'CASH-' . Str::uuid(), $data['description'], $lines);
    }

    public function cashAccount(int $branch, string $code): void
    {
        $ledger = app(LedgerService::class);
        $ledger->initialize($branch);
        if (! DB::table('ledger_accounts')->where('id_cabang', $branch)->where('code', $code)->where('cash_account', true)->exists()) {
            $ledger->fail('Pilih akun kas atau rekening cabang ini.');
        }
    }

    public function loan(int $branch, array $data): int
    {
        return DB::transaction(function () use ($branch, $data) {
            $this->cashAccount($branch, $data['cash']);
            $id = DB::table('business_loans')->insertGetId(['id_cabang' => $branch, 'party' => $data['party'], 'direction' => $data['direction'], 'date' => $data['date'], 'due_date' => $data['due_date'], 'principal' => $data['amount'], 'outstanding' => $data['amount'], 'created_at' => now(), 'updated_at' => now()]);
            $a = (int) $data['amount'];
            $lines = $data['direction'] === 'borrowed' ? [[$data['cash'], $a, 0], ['2102', 0, $a]] : [['1202', $a, 0], [$data['cash'], 0, $a]];
            app(LedgerService::class)->post($branch, $data['date'], 'LOAN-' . $id, 'Pinjaman: ' . $data['party'], $lines);
            return $id;
        });
    }

    public function repay(int $branch, int $id, array $data): void
    {
        DB::transaction(function () use ($branch, $id, $data) {
            $ledger = app(LedgerService::class);
            $this->cashAccount($branch, $data['cash']);
            $loan = DB::table('business_loans')->where('id_cabang', $branch)->where('id', $id)->lockForUpdate()->first();
            abort_unless($loan, 404);
            $principal = (int) $data['principal'];
            $interest = (int) $data['interest'];
            if ($principal > $loan->outstanding || $data['date'] < $loan->date) {
                $ledger->fail('Pokok melebihi sisa pinjaman atau tanggal mendahului pinjaman.');
            }
            $lines = $loan->direction === 'borrowed' ? [['2102', $principal, 0], ['6104', $interest, 0], [$data['cash'], 0, $principal + $interest]] : [[$data['cash'], $principal + $interest, 0], ['1202', 0, $principal], ['4104', 0, $interest]];
            $ledger->post($branch, $data['date'], 'REPAY-' . Str::uuid(), 'Cicilan: ' . $loan->party, $lines);
            DB::table('business_loans')->where('id', $id)->update(['outstanding' => $loan->outstanding - $principal, 'updated_at' => now()]);
        });
    }

    public function asset(int $branch, array $data): int
    {
        return DB::transaction(function () use ($branch, $data) {
            $this->cashAccount($branch, $data['cash']);
            $id = DB::table('business_assets')->insertGetId(['id_cabang' => $branch, 'name' => $data['name'], 'purchased_at' => $data['date'], 'cost' => $data['amount'], 'residual' => $data['residual'], 'life_months' => $data['life_months'], 'created_at' => now(), 'updated_at' => now()]);
            app(LedgerService::class)->post($branch, $data['date'], 'ASSET-' . $id, 'Pembelian aset: ' . $data['name'], [['1601', (int) $data['amount'], 0], [$data['cash'], 0, (int) $data['amount']]]);
            return $id;
        });
    }

    public function dispose(int $branch, int $id, array $data): void
    {
        DB::transaction(function () use ($branch, $id, $data) {
            $ledger = app(LedgerService::class);
            $this->cashAccount($branch, $data['cash']);
            $asset = DB::table('business_assets')->where('id_cabang', $branch)->where('id', $id)->lockForUpdate()->first();
            abort_unless($asset, 404);
            if ($asset->disposed_at || $data['date'] < $asset->purchased_at) {
                $ledger->fail('Aset sudah dilepas atau tanggal pelepasan tidak valid.');
            }
            $amount = (int) $data['amount'];
            $book = (int) $asset->cost - (int) $asset->depreciated;
            $latestDepreciation = DB::table('ledger_entries')->where('reference', 'like', 'DEP-' . $id . '-%')->max('date');
            if ($latestDepreciation && $data['date'] < $latestDepreciation) {
                $ledger->fail('Tanggal pelepasan mendahului penyusutan terakhir.');
            }
            $lines = [[$data['cash'], $amount, 0], ['1602', (int) $asset->depreciated, 0], ['1601', 0, (int) $asset->cost]];
            $lines[] = $book > $amount ? ['6105', $book - $amount, 0] : ['4105', 0, $amount - $book];
            $ledger->post($branch, $data['date'], 'DISPOSE-' . $id, 'Pelepasan aset: ' . $asset->name, $lines);
            DB::table('business_assets')->where('id', $id)->update(['disposed_at' => $data['date'], 'updated_at' => now()]);
        });
    }

    public function defer(int $branch, array $data): int
    {
        return DB::transaction(function () use ($branch, $data) {
            $this->cashAccount($branch, $data['cash']);
            $id = DB::table('business_deferrals')->insertGetId(['id_cabang' => $branch, 'description' => $data['description'], 'type' => $data['type'], 'start_date' => $data['date'], 'months' => $data['months'], 'amount' => $data['amount'], 'created_at' => now(), 'updated_at' => now()]);
            $a = (int) $data['amount'];
            $lines = $data['type'] === 'expense' ? [['1501', $a, 0], [$data['cash'], 0, $a]] : [[$data['cash'], $a, 0], ['2301', 0, $a]];
            app(LedgerService::class)->post($branch, $data['date'], 'DEFER-' . $id, $data['description'], $lines);
            return $id;
        });
    }

    public function monthEnd(int $branch, string $month, bool $close): void
    {
        DB::transaction(function () use ($branch, $month, $close) {
            $ledger = app(LedgerService::class);
            $start = Carbon::parse($month)->startOfMonth();
            $end = $start->copy()->endOfMonth();
            if ($end->startOfDay()->gte(Carbon::now('Asia/Jakarta')->startOfDay())) {
                $ledger->fail('Proses akhir bulan hanya untuk bulan yang sudah berakhir.');
            }
            if (DB::table('ledger_periods')->where('id_cabang', $branch)->where('month', '<', $start->toDateString())->where('closed', false)->exists()) {
                $ledger->fail('Tutup periode terdahulu terlebih dahulu agar pengakuan biaya berurutan.');
            }
            $ledger->open($branch, $end->toDateString());
            foreach (DB::table('business_assets')->where('id_cabang', $branch)->whereNull('disposed_at')->where('purchased_at', '<=', $end->toDateString())->lockForUpdate()->get() as $asset) {
                $ref = 'DEP-' . $asset->id . '-' . $start->format('Ym');
                if (DB::table('ledger_entries')->where('reference', $ref)->exists()) {
                    continue;
                }
                $elapsed = Carbon::parse($asset->purchased_at)->startOfMonth()->diffInMonths($start) + 1;
                $target = (int) round(($asset->cost - $asset->residual) * min($elapsed, $asset->life_months) / $asset->life_months);
                $amount = $target - $asset->depreciated;
                if ($amount > 0) {
                    $ledger->post($branch, $end->toDateString(), $ref, 'Penyusutan: ' . $asset->name, [['6103', $amount, 0], ['1602', 0, $amount]]);
                    DB::table('business_assets')->where('id', $asset->id)->update(['depreciated' => $target, 'updated_at' => now()]);
                }
            }
            foreach (DB::table('business_deferrals')->where('id_cabang', $branch)->where('start_date', '<=', $end->toDateString())->lockForUpdate()->get() as $item) {
                $ref = 'AMORT-' . $item->id . '-' . $start->format('Ym');
                if (DB::table('ledger_entries')->where('reference', $ref)->exists()) {
                    continue;
                }
                $elapsed = Carbon::parse($item->start_date)->startOfMonth()->diffInMonths($start) + 1;
                $target = (int) round($item->amount * min($elapsed, $item->months) / $item->months);
                $amount = $target - $item->recognized;
                if ($amount > 0) {
                    $lines = $item->type === 'expense' ? [['6101', $amount, 0], ['1501', 0, $amount]] : [['2301', $amount, 0], ['4103', 0, $amount]];
                    $ledger->post($branch, $end->toDateString(), $ref, 'Pengakuan: ' . $item->description, $lines);
                    DB::table('business_deferrals')->where('id', $item->id)->update(['recognized' => $target, 'updated_at' => now()]);
                }
            }
            if ($close) {
                DB::table('ledger_periods')->where('id_cabang', $branch)->where('month', $start->toDateString())->update(['closed' => true, 'updated_at' => now()]);
            }
        });
    }
}
