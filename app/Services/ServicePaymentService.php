<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ServicePaymentService
{
    public function pay(int $branch, int $id, array $data): int
    {
        return DB::transaction(function () use ($branch, $id, $data) {
            $ledger = app(LedgerService::class);
            $service = DB::table('servis')->where('id_cabang', $branch)->where('id_servis', $id)->whereNull('cancelled_at')->lockForUpdate()->first();
            abort_unless($service, 404);
            if (! $service->ledger_entry_id || (int) $data['amount'] < 1 || (int) $data['amount'] > $service->sisa) {
                $ledger->fail('Servis belum ditagih atau nominal melebihi sisa tagihan.');
            }
            $invoice = DB::table('ledger_entries')->where('id', $service->ledger_entry_id)->first();
            if ($data['date'] < $invoice->date) {
                $ledger->fail('Tanggal pembayaran mendahului tagihan servis.');
            }
            app(BusinessFinanceService::class)->cashAccount($branch, $data['cash']);
            $amount = (int) $data['amount'];
            $entry = $ledger->post($branch, $data['date'], 'SERVIS-PAY-' . Str::uuid(), 'Pembayaran servis ' . $service->kode_servis . ': ' . $data['description'], [[$data['cash'], $amount, 0], ['1203', 0, $amount]]);
            $id = DB::table('servis_pembayaran')->insertGetId(['id_cabang' => $branch, 'id_servis' => $id, 'date' => $data['date'], 'nominal' => $amount, 'cash_code' => $data['cash'], 'description' => $data['description'], 'entry_id' => $entry, 'id_user' => auth()->id(), 'created_at' => now(), 'updated_at' => now()]);
            $this->sync($service->id_servis);
            return $id;
        });
    }

    public function cancel(int $branch, int $id, string $date): void
    {
        DB::transaction(function () use ($branch, $id, $date) {
            $payment = DB::table('servis_pembayaran')->where('id_cabang', $branch)->where('id', $id)->first();
            abort_unless($payment, 404);
            DB::table('servis')->where('id_servis', $payment->id_servis)->lockForUpdate()->first();
            $payment = DB::table('servis_pembayaran')->where('id', $id)->lockForUpdate()->first();
            if ($payment->cancelled_at) {
                app(LedgerService::class)->fail('Pembayaran sudah dibatalkan.');
            }
            app(LedgerService::class)->reverse($branch, $payment->entry_id, $date);
            DB::table('servis_pembayaran')->where('id', $id)->update(['cancelled_at' => now(), 'updated_at' => now()]);
            $this->sync($payment->id_servis);
        });
    }

    private function sync(int $id): void
    {
        $service = DB::table('servis')->where('id_servis', $id)->first();
        $tagihan = (int) $service->biaya_servis + (int) ($service->sparepart_total ?? 0);
        $paid = (int) DB::table('servis_pembayaran')->where('id_servis', $id)->whereNull('cancelled_at')->sum('nominal');
        DB::table('servis')->where('id_servis', $id)->update(['dibayar' => $paid, 'sisa' => max($tagihan - $paid, 0), 'status_pembayaran' => status_pembayaran($tagihan, $paid), 'updated_at' => now()]);
    }
}
