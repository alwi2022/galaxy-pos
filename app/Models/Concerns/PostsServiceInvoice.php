<?php

namespace App\Models\Concerns;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

trait PostsServiceInvoice
{
    public static function bootPostsServiceInvoice()
    {
        static::addGlobalScope('active', fn ($q) => $q->whereNull('servis.cancelled_at'));
    }

    public function save(array $options = [])
    {
        return DB::transaction(function () use ($options) {
            $ledger = app(\App\Services\LedgerService::class);
            $current = $this->exists ? DB::table('servis')->where('id_servis', $this->getKey())->lockForUpdate()->first() : null;
            if ($current && $current->cancelled_at) {
                $ledger->fail('Servis sudah dibatalkan.');
            }
            $billable = in_array($this->status, ['selesai', 'diambil'], true) && $this->biaya_servis > 0;
            $entryId = $current->ledger_entry_id ?? null;
            $changed = $current && ((int) $current->biaya_servis !== (int) $this->biaya_servis || ! $billable);
            if ($entryId && $changed) {
                if (DB::table('servis_pembayaran')->where('id_servis', $this->getKey())->whereNull('cancelled_at')->exists()) {
                    $ledger->fail('Batalkan pembayaran servis terlebih dahulu sebelum mengubah tagihan.');
                }
                $original = DB::table('ledger_entries')->where('id', $entryId)->first();
                $ledger->open((int) $this->id_cabang, $original->date);
                $ledger->reverse((int) $this->id_cabang, $entryId, $original->date);
                $entryId = null;
            }
            if ($billable && ! $this->tanggal_selesai) {
                $this->tanggal_selesai = now();
            }
            $result = parent::save($options);
            if ($billable && ! $entryId) {
                $entryId = $ledger->post((int) $this->id_cabang, Carbon::parse($this->tanggal_selesai)->toDateString(), 'SERVIS-INV-' . $this->getKey() . '-' . Str::uuid(), 'Tagihan servis ' . $this->kode_servis, [['1203', (int) $this->biaya_servis, 0], ['4102', 0, (int) $this->biaya_servis]]);
            }
            $paid = (int) DB::table('servis_pembayaran')->where('id_servis', $this->getKey())->whereNull('cancelled_at')->sum('nominal');
            $total = $entryId ? (int) $this->biaya_servis : 0;
            $values = ['ledger_entry_id' => $entryId, 'dibayar' => $paid, 'sisa' => max($total - $paid, 0), 'status_pembayaran' => $total > 0 ? status_pembayaran($total, $paid) : 'belum_bayar'];
            DB::table('servis')->where('id_servis', $this->getKey())->update($values);
            $this->forceFill($values)->syncOriginal();
            return $result;
        });
    }

    public function delete()
    {
        return DB::transaction(function () {
            $ledger = app(\App\Services\LedgerService::class);
            $current = DB::table('servis')->where('id_servis', $this->getKey())->lockForUpdate()->first();
            if (! $current || $current->cancelled_at) {
                $ledger->fail('Servis sudah dibatalkan.');
            }
            if (DB::table('servis_pembayaran')->where('id_servis', $this->getKey())->whereNull('cancelled_at')->exists()) {
                $ledger->fail('Batalkan pembayaran sebelum membatalkan servis.');
            }
            if ($current->ledger_entry_id) {
                $ledger->reverse((int) $current->id_cabang, $current->ledger_entry_id, now()->toDateString());
            }
            DB::table('servis')->where('id_servis', $this->getKey())->update(['cancelled_at' => now(), 'sisa' => 0]);
            return true;
        });
    }
}
