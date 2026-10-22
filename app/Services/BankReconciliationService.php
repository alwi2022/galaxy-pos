<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class BankReconciliationService
{
    public function import(int $branch, string $cash, string $path): array
    {
        app(BusinessFinanceService::class)->cashAccount($branch, $cash);
        $stream = fopen($path, 'r');
        $rows = [];
        try {
            $header = fgetcsv($stream);
            $header = array_map(fn ($h) => trim($h, "\xEF\xBB\xBF \t\r\n"), $header ?: []);
            if ($header !== ['date', 'reference', 'description', 'amount']) {
                app(LedgerService::class)->fail('Kolom CSV harus date,reference,description,amount. Nominal positif berarti masuk, negatif berarti keluar.');
            }
            while (($row = fgetcsv($stream)) !== false) {
                if ($row === [null]) { continue; }
                if (count($row) !== 4 || count($rows) >= 5000) {
                    app(LedgerService::class)->fail('CSV tidak valid atau melebihi 5.000 baris.');
                }
                $record = array_combine($header, $row);
                Validator::make($record, ['date' => 'required|date_format:Y-m-d|before_or_equal:today', 'reference' => 'required|string|max:150', 'description' => 'required|string|max:400', 'amount' => 'required|integer|not_in:0|between:-2000000000,2000000000'])->validate();
                $rows[] = $record;
            }
        } finally {
            fclose($stream);
        }
        if (! $rows) { app(LedgerService::class)->fail('CSV tidak memiliki transaksi.'); }
        return DB::transaction(function () use ($branch, $cash, $rows) {
            $account = DB::table('ledger_accounts')->where('id_cabang', $branch)->where('code', $cash)->lockForUpdate()->first();
            $added = $skipped = 0;
            foreach ($rows as $row) {
                $fingerprint = hash('sha256', $row['date'] . '|' . $row['reference']);
                $existing = DB::table('bank_statement_lines')->where('account_id', $account->id)->where('fingerprint', $fingerprint)->first();
                if ($existing) {
                    if ((int) $existing->amount !== (int) $row['amount']) {
                        app(LedgerService::class)->fail('Referensi bank yang sama memiliki nominal berbeda: ' . $row['reference']);
                    }
                    $skipped++;
                    continue;
                }
                DB::table('bank_statement_lines')->insert(['id_cabang' => $branch, 'account_id' => $account->id, 'date' => $row['date'], 'reference' => $row['reference'], 'description' => $row['description'], 'amount' => (int) $row['amount'], 'fingerprint' => $fingerprint, 'created_at' => now(), 'updated_at' => now()]);
                $added++;
            }
            return compact('added', 'skipped');
        });
    }

    public function match(int $branch, int $statementId, int $lineId): void
    {
        DB::transaction(function () use ($branch, $statementId, $lineId) {
            $statement = DB::table('bank_statement_lines')->where('id_cabang', $branch)->where('id', $statementId)->lockForUpdate()->first();
            abort_unless($statement, 404);
            $line = DB::table('ledger_lines as l')->join('ledger_entries as e', 'e.id', '=', 'l.entry_id')->where('e.id_cabang', $branch)->where('l.id', $lineId)->select('l.*', 'e.date')->lockForUpdate()->first();
            abort_unless($line, 404);
            if ($statement->ledger_line_id || (int) $line->account_id !== (int) $statement->account_id || (int) $line->debit - (int) $line->credit !== (int) $statement->amount || DB::table('bank_statement_lines')->where('ledger_line_id', $lineId)->exists()) {
                app(LedgerService::class)->fail('Akun/nominal tidak cocok atau salah satu baris sudah dipasangkan.');
            }
            if (abs(\Carbon\Carbon::parse($line->date)->diffInDays(\Carbon\Carbon::parse($statement->date), false)) > 7) {
                app(LedgerService::class)->fail('Tanggal bank dan jurnal berbeda lebih dari tujuh hari.');
            }
            DB::table('bank_statement_lines')->where('id', $statementId)->update(['ledger_line_id' => $lineId, 'matched_by' => auth()->id(), 'matched_at' => now(), 'updated_at' => now()]);
            $this->event($statementId, $lineId, 'match');
        });
    }

    public function unmatch(int $branch, int $id): void
    {
        DB::transaction(function () use ($branch, $id) {
            $statement = DB::table('bank_statement_lines')->where('id_cabang', $branch)->where('id', $id)->lockForUpdate()->first();
            abort_unless($statement, 404);
            if (! $statement->ledger_line_id) { app(LedgerService::class)->fail('Baris belum dipasangkan.'); }
            $this->event($id, $statement->ledger_line_id, 'unmatch');
            DB::table('bank_statement_lines')->where('id', $id)->update(['ledger_line_id' => null, 'matched_by' => null, 'matched_at' => null, 'updated_at' => now()]);
        });
    }

    public function adjust(int $branch, int $id, string $counterpart): void
    {
        DB::transaction(function () use ($branch, $id, $counterpart) {
            $statement = DB::table('bank_statement_lines')->where('id_cabang', $branch)->where('id', $id)->lockForUpdate()->first();
            abort_unless($statement, 404);
            if ($statement->ledger_line_id || ! in_array($counterpart, ['6101', '4103', '4104'], true)) {
                app(LedgerService::class)->fail('Pilih akun biaya/pendapatan bank untuk baris yang belum dipasangkan.');
            }
            $cash = DB::table('ledger_accounts')->where('id', $statement->account_id)->value('code');
            $amount = abs((int) $statement->amount);
            $lines = $statement->amount > 0 ? [[$cash, $amount, 0], [$counterpart, 0, $amount]] : [[$counterpart, $amount, 0], [$cash, 0, $amount]];
            $entry = app(LedgerService::class)->post($branch, $statement->date, 'BANK-ADJUST-' . $id, 'Rekening koran: ' . $statement->description, $lines);
            $line = DB::table('ledger_lines')->where('entry_id', $entry)->where('account_id', $statement->account_id)->value('id');
            $this->match($branch, $id, $line);
        });
    }

    private function event(int $id, int $lineId, string $action): void
    {
        DB::table('bank_match_events')->insert(['statement_line_id' => $id, 'ledger_line_id' => $lineId, 'action' => $action, 'id_user' => auth()->id(), 'created_at' => now(), 'updated_at' => now()]);
    }
}
