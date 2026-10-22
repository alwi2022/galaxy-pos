<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class TaxReportingService
{
    public function events(int $branch, string $month): array
    {
        $start = Carbon::parse($month)->startOfMonth()->toDateString();
        $end = Carbon::parse($month)->endOfMonth()->toDateString();
        $lines = DB::table('ledger_lines as l')->join('ledger_entries as e', 'e.id', '=', 'l.entry_id')->join('ledger_accounts as a', 'a.id', '=', 'l.account_id')->leftJoin('ledger_entries as original', 'original.id', '=', 'e.reversal_of')->where('e.id_cabang', $branch)->whereBetween('e.date', [$start, $end])->where('e.is_closing', false)->whereIn('a.code', ['1401', '2201', '4101', '1301', '6102'])->select('e.id', 'e.date', 'e.reference', 'a.code', 'l.debit', 'l.credit')->selectRaw('COALESCE(original.reference,e.reference) as source')->get()->groupBy('id');
        $returns = DB::table('stock_returns')->where('id_cabang', $branch)->get()->keyBy('id');
        $documents = DB::table('tax_invoices')->where('id_cabang', $branch)->get()->keyBy(fn ($d) => $d->source_type . ':' . $d->source_id);
        $result = [];
        foreach ($lines as $entryLines) {
            $first = $entryLines->first();
            $type = null;
            $sourceId = null;
            if (preg_match('/^(PENJUALAN|PEMBELIAN)-(\d+)$/', $first->source, $matches)) {
                $type = strtolower($matches[1]);
                $sourceId = (int) $matches[2];
            } elseif (preg_match('/^RET-(\d+)$/', $first->source, $matches) && isset($returns[(int) $matches[1]])) {
                $returned = $returns[(int) $matches[1]];
                $type = $returned->type;
                $sourceId = $returned->transaction_id;
            }
            if (! $type) { continue; }
            $sale = $type === 'penjualan';
            $tax = $base = 0;
            foreach ($entryLines as $line) {
                $balance = (int) $line->debit - (int) $line->credit;
                if ($line->code === ($sale ? '2201' : '1401')) { $tax += $sale ? -$balance : $balance; }
                if ($sale && $line->code === '4101') { $base -= $balance; }
                if (! $sale && in_array($line->code, ['1301', '6102'], true)) { $base += $balance; }
            }
            if ($tax === 0 && $base === 0) { continue; }
            $doc = $documents[$type . ':' . $sourceId] ?? null;
            $result[] = ['entry_id' => $first->id, 'date' => $first->date, 'reference' => $first->reference, 'source_type' => $type, 'source_id' => $sourceId, 'dpp' => $base, 'tax' => $tax, 'invoice_number' => $doc->invoice_number ?? '', 'party_name' => $doc->party_name ?? '', 'party_npwp' => $doc->party_npwp ?? '', 'creditable' => ! $sale && $doc && (bool) $doc->creditable];
        }
        return $result;
    }

    public function prepare(int $branch, string $month): int
    {
        return DB::transaction(function () use ($branch, $month) {
            $ledger = app(LedgerService::class);
            $ledger->initialize($branch);
            DB::table('cabang')->where('id_cabang', $branch)->lockForUpdate()->first();
            $start = Carbon::parse($month)->startOfMonth();
            if ($start->gt(now()->startOfMonth())) { $ledger->fail('Masa pajak belum dimulai.'); }
            $events = $this->events($branch, $month);
            $output = $input = 0;
            foreach ($events as $event) {
                if ($event['source_type'] === 'penjualan') { $output += $event['tax']; }
                elseif ($event['creditable']) { $input += $event['tax']; }
            }
            $previous = DB::table('tax_returns')->where('id_cabang', $branch)->where('month', $start->copy()->subMonth()->toDateString())->where('status', 'filed')->orderByDesc('revision')->first();
            $carry = (int) ($previous->carry_out ?? 0);
            $net = $output - $input - $carry;
            $revision = (int) DB::table('tax_returns')->where('id_cabang', $branch)->where('month', $start->toDateString())->max('revision') + 1;
            return DB::table('tax_returns')->insertGetId(['id_cabang' => $branch, 'month' => $start->toDateString(), 'revision' => $revision, 'output_tax' => $output, 'input_tax' => $input, 'carry_in' => $carry, 'payable' => max($net, 0), 'carry_out' => max(-$net, 0), 'status' => 'draft', 'snapshot' => json_encode($events, JSON_THROW_ON_ERROR), 'id_user' => auth()->id(), 'created_at' => now(), 'updated_at' => now()]);
        });
    }

    public function payment(int $branch, int $id, array $data): void
    {
        DB::transaction(function () use ($branch, $id, $data) {
            $return = $this->latest($branch, $id);
            $ledger = app(LedgerService::class);
            $this->assertFresh($branch, $return);
            if ($data['date'] < $return->month) { $ledger->fail('Tanggal setor mendahului masa pajak.'); }
            if (DB::table('tax_payments')->where('id_cabang',$branch)->where('receipt',$data['receipt'])->exists()) { $ledger->fail('Referensi setoran sudah dicatat.'); }
            $paid = (int) DB::table('tax_payments as p')->join('tax_returns as r', 'r.id', '=', 'p.return_id')->where('r.id_cabang', $branch)->where('r.month', $return->month)->sum('p.amount');
            if ((int) $data['amount'] < 1 || (int) $data['amount'] > $return->payable - $paid) { $ledger->fail('Setoran melebihi kekurangan bayar masa pajak.'); }
            app(BusinessFinanceService::class)->cashAccount($branch, $data['cash']);
            $this->offset($branch, $return, $data['date']);
            $entry = $ledger->post($branch, $data['date'], 'TAX-PAY-' . \Illuminate\Support\Str::uuid(), 'Setoran PPN ' . substr($return->month, 0, 7) . ' · ' . $data['receipt'], [['2201', (int) $data['amount'], 0], [$data['cash'], 0, (int) $data['amount']]]);
            DB::table('tax_payments')->insert(['id_cabang' => $branch, 'return_id' => $id, 'date' => $data['date'], 'amount' => $data['amount'], 'cash_code' => $data['cash'], 'receipt' => $data['receipt'], 'entry_id' => $entry, 'created_at' => now(), 'updated_at' => now()]);
        });
    }

    public function filed(int $branch, int $id, string $date, string $receipt): void
    {
        DB::transaction(function () use ($branch, $id, $date, $receipt) {
            $return = $this->latest($branch, $id);
            $ledger = app(LedgerService::class);
            $this->assertFresh($branch, $return);
            $profile=DB::table('tax_profiles')->where('id_cabang',$branch)->first();
            if (!$profile || !$profile->pkp) { $ledger->fail('Lengkapi profil PKP sebelum mencatat bukti lapor PPN.'); }
            if (DB::table('tax_returns')->where('id_cabang',$branch)->where('receipt',$receipt)->exists()) { $ledger->fail('Nomor bukti lapor sudah digunakan.'); }
            if ($return->status === 'filed' || Carbon::parse($return->month)->endOfMonth()->startOfDay()->gte(Carbon::parse($date))) {
                $ledger->fail('Masa pajak belum berakhir atau bukti lapor sudah dicatat.');
            }
            $paid = (int) DB::table('tax_payments as p')->join('tax_returns as r', 'r.id', '=', 'p.return_id')->where('r.id_cabang', $branch)->where('r.month', $return->month)->sum('p.amount');
            if ($paid < $return->payable) { $ledger->fail('Selesaikan pencatatan setoran sebelum mencatat bukti lapor.'); }
            $this->offset($branch, $return, $date);
            DB::table('tax_returns')->where('id', $id)->update(['status' => 'filed', 'receipt' => $receipt, 'filed_at' => $date, 'updated_at' => now()]);
        });
    }

    public function latest(int $branch, int $id): object
    {
        DB::table('cabang')->where('id_cabang', $branch)->lockForUpdate()->first();
        $return = DB::table('tax_returns')->where('id_cabang', $branch)->where('id', $id)->lockForUpdate()->first();
        abort_unless($return, 404);
        $latest = DB::table('tax_returns')->where('id_cabang', $branch)->where('month', $return->month)->max('revision');
        if ($return->revision != $latest) { app(LedgerService::class)->fail('Gunakan revisi terbaru masa pajak ini.'); }
        return $return;
    }

    private function offset(int $branch, object $return, string $date): void
    {
        if ($return->offset_entry_id) { return; }
        $settled = (int) DB::table('tax_returns')->where('id_cabang', $branch)->where('month', $return->month)->sum('offset_amount');
        $delta = (int) $return->input_tax - $settled;
        if ($delta === 0) { return; }
        $lines = $delta > 0 ? [['2201', $delta, 0], ['1401', 0, $delta]] : [['1401', -$delta, 0], ['2201', 0, -$delta]];
        $entry = app(LedgerService::class)->post($branch, $date, 'TAX-OFFSET-' . $return->id, 'Pengkreditan PPN ' . substr($return->month, 0, 7), $lines);
        DB::table('tax_returns')->where('id', $return->id)->update(['offset_amount' => $delta, 'offset_entry_id' => $entry]);
        $return->offset_entry_id = $entry;
    }

    private function assertFresh(int $branch, object $return): void
    {
        $saved=collect(json_decode($return->snapshot,true,512,JSON_THROW_ON_ERROR))->sortBy('entry_id')->values()->all();
        $current=collect($this->events($branch,$return->month))->sortBy('entry_id')->values()->all();
        $previous=DB::table('tax_returns')->where('id_cabang',$branch)->where('month',Carbon::parse($return->month)->subMonth()->startOfMonth()->toDateString())->where('status','filed')->orderByDesc('revision')->first();
        if ($saved!==$current || (int)$return->carry_in!==(int)($previous->carry_out??0)) {
            app(LedgerService::class)->fail('Transaksi, faktur, atau kompensasi berubah setelah draft dibuat. Buat revisi terbaru sebelum melanjutkan.');
        }
    }

    public function retailXml(int $branch, int $id): string
    {
        $return = DB::table('tax_returns')->where('id_cabang', $branch)->where('id', $id)->first();
        abort_unless($return, 404);
        $profile = DB::table('tax_profiles')->where('id_cabang', $branch)->first();
        if (! $profile || ! $profile->pkp || ! preg_match('/^\d{16}$/', $profile->npwp)) { app(LedgerService::class)->fail('Lengkapi profil PKP dan NPWP 16 digit sebelum ekspor XML.'); }
        $events = array_values(array_filter(json_decode($return->snapshot, true, 512, JSON_THROW_ON_ERROR), fn ($e) => $e['source_type'] === 'penjualan'));
        if (! $events) { app(LedgerService::class)->fail('Tidak ada faktur keluaran pada snapshot masa ini.'); }
        $xml = new \DOMDocument('1.0', 'UTF-8');
        $xml->formatOutput = true;
        $root = $xml->createElement('RetailInvoiceBulk');
        $root->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:xsi', 'http://www.w3.org/2001/XMLSchema-instance');
        $root->setAttributeNS('http://www.w3.org/2001/XMLSchema-instance', 'xsi:noNamespaceSchemaLocation', 'schema.xsd');
        $xml->appendChild($root);
        $add = function ($parent, $name, $value) use ($xml) { $node = $xml->createElement($name); $node->appendChild($xml->createTextNode((string) $value)); $parent->appendChild($node); return $node; };
        $add($root, 'TIN', $profile->npwp);
        $add($root, 'TaxPeriodMonth', Carbon::parse($return->month)->month);
        $add($root, 'TaxPeriodYear', Carbon::parse($return->month)->year);
        $list = $xml->createElement('ListOfRetailInvoice');
        $root->appendChild($list);
        foreach ($events as $event) {
            if ($event['tax'] < 0 || $event['dpp'] < 0 || ! $event['invoice_number'] || ! preg_match('/^\d{16}$/', $event['party_npwp'])) {
                app(LedgerService::class)->fail('XML retail membutuhkan faktur normal dengan identitas lengkap. Masa yang memuat retur/koreksi harus ditangani melalui lampiran Coretax yang sesuai; gunakan ekspor kertas kerja.');
            }
            $row = $xml->createElement('RetailInvoice');
            $list->appendChild($row);
            foreach (['TrxCode' => 'Normal', 'BuyerName' => $event['party_name'], 'BuyerIdOpt' => 'NPWP', 'BuyerIdNumber' => $event['party_npwp'], 'GoodServiceOpt' => 'A', 'SerialNo' => $event['invoice_number'], 'TransactionDate' => $event['date'], 'TaxBaseSellingPrice' => $event['dpp'], 'OtherTaxBaseSellingPrice' => $event['dpp'], 'VAT' => $event['tax'], 'STLG' => 0, 'Info' => ''] as $key => $value) { $add($row, $key, $value); }
        }
        return $xml->saveXML();
    }
}
