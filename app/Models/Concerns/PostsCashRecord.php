<?php

namespace App\Models\Concerns;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

trait PostsCashRecord
{
    public static function bootPostsCashRecord()
    {
        static::addGlobalScope('active', fn ($q) => $q->whereNull($q->getModel()->getTable() . '.cancelled_at'));
    }

    public function save(array $options = [])
    {
        return DB::transaction(function () use ($options) {
            $ledger = app(\App\Services\LedgerService::class);
            $service = $this->getTable() === 'servis';
            $expense = $this->getTable() === 'pengeluaran';
            $dateField = $service ? 'tanggal_selesai' : ($expense ? 'tanggal_pengeluaran' : 'tanggal_pendapatan');
            $amount = (int) ($service ? $this->biaya_servis : $this->nominal);
            $date = Carbon::parse($this->$dateField ?? now())->toDateString();
            $ledger->open((int) $this->id_cabang, $date);
            $oldEntry = $this->ledger_entry_id;
            if ($this->exists) {
                $current = DB::table($this->getTable())->where($this->getKeyName(), $this->getKey())->lockForUpdate()->first();
                if ($current && $current->cancelled_at) {
                    $ledger->fail('Transaksi sudah dibatalkan.');
                }
                $oldEntry = $current->ledger_entry_id ?? null;
            }
            if ($oldEntry) {
                $old = DB::table('ledger_entries')->where('id', $oldEntry)->first();
                $ledger->open((int) $this->id_cabang, $old->date);
                $ledger->reverse((int) $this->id_cabang, $oldEntry, $old->date);
            }
            $result = parent::save($options);
            $entry = null;
            if ($amount > 0 && (! $service || in_array($this->status, ['selesai', 'diambil'], true))) {
                $cash = $ledger->cashCode($this->metode_pembayaran ?? 'tunai');
                $code = $service ? '4102' : '4103';
                $lines = $expense ? [['6101', $amount, 0], [$cash, 0, $amount]] : [[$cash, $amount, 0], [$code, 0, $amount]];
                $entry = $ledger->post((int) $this->id_cabang, $date, strtoupper($this->getTable()) . '-' . $this->getKey() . '-' . Str::uuid(), $service ? 'Servis ' . $this->kode_servis : $this->deskripsi, $lines);
            }
            DB::table($this->getTable())->where($this->getKeyName(), $this->getKey())->update(['ledger_entry_id' => $entry]);
            $this->ledger_entry_id = $entry;
            $this->syncOriginalAttribute('ledger_entry_id');
            return $result;
        });
    }

    public function delete()
    {
        return DB::transaction(function () {
            $record = DB::table($this->getTable())->where($this->getKeyName(), $this->getKey())->lockForUpdate()->first();
            if (! $record || $record->cancelled_at) {
                app(\App\Services\LedgerService::class)->fail('Transaksi sudah dibatalkan.');
            }
            if ($record->ledger_entry_id) {
                app(\App\Services\LedgerService::class)->reverse((int) $this->id_cabang, $record->ledger_entry_id, now()->toDateString());
            }
            DB::table($this->getTable())->where($this->getKeyName(), $this->getKey())->update(['cancelled_at' => now()]);
            return true;
        });
    }
}
