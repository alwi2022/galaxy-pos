<?php

namespace App\Http\Controllers;

use App\Services\BusinessFinanceService;
use App\Services\BusinessTransactionService;
use App\Services\LedgerService;
use App\Services\StockLedgerService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BusinessController extends Controller
{
    private array $tableTotals = [];

    public function index(Request $request, string $module = 'stok')
    {
        abort_unless(array_key_exists($module, config('business')), 404);
        $request->attributes->set('business_module', $module);
        if ($request->has('data_table')) {
            $definition = config('business_tables.' . $request->input('data_table'));
            abort_unless($definition && in_array($module, (array)$definition['module'], true), 404);
            $request->validate(['draw'=>'required|integer|min:0','offset'=>'required|integer|min:0|max:2147483647','length'=>'required|in:10,25,50,100','search.value'=>'nullable|string|max:100','order.0.column'=>'nullable|integer|min:0','order.0.dir'=>'nullable|in:asc,desc']);
            $request->merge(['q'=>$request->input('search.value',''), 'per_page'=>$request->input('length')]);
        }
        $request->validate(['start' => 'nullable|date_format:Y-m-d', 'end' => 'nullable|date_format:Y-m-d|after_or_equal:start', 'account' => 'nullable|string|max:20', 'product' => 'nullable|integer', 'q' => 'nullable|string|max:100', 'per_page' => 'nullable|in:10,25,50,100', 'status' => ['nullable', \Illuminate\Validation\Rule::in(array_keys(config('business.' . $module . '.statuses')))], 'month' => 'nullable|date_format:Y-m']);
        $q = trim((string) $request->q);
        $perPage = (int) $request->input('per_page', 10);
        $status = $request->input('status');
        $branch = (int) auth()->user()->id_cabang;
        $ledger = app(LedgerService::class);
        $ledger->initialize($branch);
        $start = $request->input('start', in_array($module, ['aset','pinjaman','akrual'], true) ? now()->startOfYear()->toDateString() : now()->startOfMonth()->toDateString());
        $end = $request->input('end', now()->toDateString());
        $accounts = DB::table('ledger_accounts')->where('id_cabang', $branch)->orderBy('code')->get();
        $balances = $request->has('data_table') ? collect() : $ledger->balances($branch, $end);
        $periodBalances = $request->has('data_table') ? collect() : $ledger->balances($branch, $end, $start, true);
        $profit = -(int) $periodBalances->whereIn('type', ['income', 'expense'])->sum('balance');
        $cumulativeProfit = -(int) $balances->whereIn('type', ['income', 'expense'])->sum('balance');
        $products = $module === 'stok' && !$request->has('data_table') ? DB::table('produk')->where('id_cabang', $branch)->orderBy('nama_produk')->get() : collect();
        $movements = $this->listing(DB::table('stock_movements as m')->join('produk as p', 'p.id_produk', '=', 'm.id_produk')->where('m.id_cabang', $branch)->whereBetween('m.occurred_at', [$start . ' 00:00:00', $end . ' 23:59:59'])->when($request->product, fn ($query) => $query->where('m.id_produk', $request->product))->select('m.*', 'p.nama_produk')->orderByDesc('m.id'), ['p.nama_produk', 'm.reason', 'm.reference'], $q, $perPage, 'stock_page');
        $returns = $this->listing(DB::table('stock_returns as r')->join('produk as p', 'p.id_produk', '=', 'r.id_produk')->where('r.id_cabang', $branch)->whereBetween('r.date', [$start, $end])->when($module === 'retur' && $status, fn ($query) => $query->where('r.type', $status))->select('r.*', 'p.nama_produk')->orderByDesc('r.id'), ['p.nama_produk', 'r.reason', 'r.type'], $q, $perPage, 'return_page');
        $entries = $this->listing(DB::table('ledger_lines as l')->join('ledger_entries as e', 'e.id', '=', 'l.entry_id')->join('ledger_accounts as a', 'a.id', '=', 'l.account_id')->where('e.id_cabang', $branch)->whereBetween('e.date', [$start, $end])->when($request->account, fn ($query) => $query->where('a.code', $request->account))->select('e.id', 'e.date', 'e.reference', 'e.description', 'a.code', 'a.name', 'a.cash_account', 'l.debit', 'l.credit')->orderByDesc('e.date')->orderByDesc('e.id')->orderBy('l.id'), ['e.reference', 'e.description', 'a.name', 'a.code'], $q, $perPage, 'journal_page');
        $assets = DB::table('business_assets')->where('id_cabang', $branch)->orderByDesc('id')->get();
        $loans = DB::table('business_loans')->where('id_cabang', $branch)->orderByDesc('id')->get();
        $deferrals = DB::table('business_deferrals')->where('id_cabang', $branch)->orderByDesc('id')->get();
        $assetRows = $this->listing(DB::table('business_assets')->where('id_cabang', $branch)->whereBetween('purchased_at', [$start, $end])->when($module === 'aset' && $status === 'active', fn ($query) => $query->whereNull('disposed_at'))->when($module === 'aset' && $status === 'disposed', fn ($query) => $query->whereNotNull('disposed_at'))->orderByDesc('id'), ['name'], $q, $perPage, 'asset_page');
        $loanRows = $this->listing(DB::table('business_loans')->where('id_cabang', $branch)->whereBetween('date', [$start, $end])->when($module === 'pinjaman' && $status === 'open', fn ($query) => $query->where('outstanding', '>', 0))->when($module === 'pinjaman' && $status === 'paid', fn ($query) => $query->where('outstanding', 0))->orderByDesc('id'), ['party', 'direction'], $q, $perPage, 'loan_page');
        $deferralRows = $this->listing(DB::table('business_deferrals')->where('id_cabang', $branch)->whereBetween('start_date', [$start, $end])->when($module === 'akrual' && $status === 'open', fn ($query) => $query->whereColumn('recognized', '<', 'amount'))->when($module === 'akrual' && $status === 'paid', fn ($query) => $query->whereColumn('recognized', 'amount'))->orderByDesc('id'), ['description', 'type'], $q, $perPage, 'deferral_page');
        $serviceOptions = $module === 'piutang-servis' && !$request->has('data_table') ? DB::table('servis')->where('id_cabang', $branch)->whereNull('cancelled_at')->whereNotNull('ledger_entry_id')->orderByDesc('id_servis')->get() : collect();
        $serviceRows = $this->listing(DB::table('servis')->where('id_cabang', $branch)->whereNull('cancelled_at')->whereNotNull('ledger_entry_id')->whereBetween('tanggal_selesai', [$start . ' 00:00:00', $end . ' 23:59:59'])->when($module === 'piutang-servis' && $status, fn ($query) => $query->where('status_pembayaran', $status))->orderByDesc('id_servis'), ['kode_servis', 'nama_pelanggan', 'tipe_barang'], $q, $perPage, 'service_page');
        $servicePayments = $this->listing(DB::table('servis_pembayaran as p')->join('servis as s', 's.id_servis', '=', 'p.id_servis')->where('p.id_cabang', $branch)->whereBetween('p.date', [$start, $end])->select('p.*', 's.kode_servis', 's.nama_pelanggan')->orderByDesc('p.id'), ['s.kode_servis', 's.nama_pelanggan', 'p.description'], $q, $perPage, 'service_payment_page');
        $periods = DB::table('ledger_periods')->where('id_cabang', $branch)->orderByDesc('month')->get();
        $reconciliations = $this->listing(DB::table('cash_reconciliations as r')->join('ledger_accounts as a', 'a.id', '=', 'r.account_id')->where('r.id_cabang', $branch)->whereBetween('r.date', [$start,$end])->select('r.*', 'a.name')->selectRaw('r.statement_balance-r.system_balance as difference')->orderByDesc('r.id'), ['r.note','a.name'], $q, $perPage, 'reconcile_page');
        $bankRows = $this->listing(DB::table('bank_statement_lines as b')->join('ledger_accounts as a', 'a.id', '=', 'b.account_id')->where('b.id_cabang', $branch)->whereBetween('b.date', [$start, $end])->when($request->account, fn ($query) => $query->where('a.code', $request->account))->when($module === 'rekonsiliasi' && $status === 'matched', fn ($query) => $query->whereNotNull('b.ledger_line_id'))->when($module === 'rekonsiliasi' && $status === 'unmatched', fn ($query) => $query->whereNull('b.ledger_line_id'))->select('b.*', 'a.name', 'a.code')->orderByDesc('b.date')->orderByDesc('b.id'), ['b.reference', 'b.description', 'a.name'], $q, $perPage, 'bank_page');
        $bankCandidates = $module === 'rekonsiliasi' && $request->has('data_table') ? DB::table('ledger_lines as l')->join('ledger_entries as e', 'e.id', '=', 'l.entry_id')->join('ledger_accounts as a', 'a.id', '=', 'l.account_id')->where('e.id_cabang', $branch)->where('a.cash_account', true)->whereBetween('e.date', [\Carbon\Carbon::parse($start)->subDays(7)->toDateString(), \Carbon\Carbon::parse($end)->addDays(7)->toDateString()])->whereNotIn('l.id', DB::table('bank_statement_lines')->whereNotNull('ledger_line_id')->select('ledger_line_id'))->select('l.*', 'e.reference', 'e.date', 'a.name')->orderByDesc('e.date')->get() : collect();
        $taxMonth = $request->input('month', substr($end, 0, 7));
        $taxProfile = DB::table('tax_profiles')->where('id_cabang', $branch)->first();
        $taxEvents = $module === 'pajak' && !$request->has('data_table') ? app(\App\Services\TaxReportingService::class)->events($branch, $taxMonth . '-01') : [];
        $taxInvoices = $this->listing(DB::table('tax_invoices')->where('id_cabang', $branch)->whereBetween('date', [$taxMonth . '-01', \Carbon\Carbon::parse($taxMonth . '-01')->endOfMonth()->toDateString()])->orderByDesc('id'), ['invoice_number', 'party_name', 'party_npwp'], $q, $perPage, 'invoice_page');
        $taxReturns = $this->listing(DB::table('tax_returns')->where('id_cabang', $branch)->where('month', $taxMonth . '-01')->when($module === 'pajak' && $status, fn ($query) => $query->where('status', $status))->orderByDesc('revision'), ['receipt', 'status'], $q, $perPage, 'tax_page');
        $taxReturnOptions = DB::table('tax_returns')->where('id_cabang', $branch)->orderByDesc('month')->orderByDesc('revision')->get();
        $taxPayments = $this->listing(DB::table('tax_payments as p')->join('tax_returns as r', 'r.id', '=', 'p.return_id')->where('p.id_cabang', $branch)->where('r.month', $taxMonth . '-01')->select('p.*', 'r.month', 'r.revision')->orderByDesc('p.id'), ['p.receipt','p.cash_code'], $q, $perPage, 'tax_payment_page');
        $years = DB::table('ledger_years')->where('id_cabang', $branch)->orderByDesc('year')->get();
        $cashEntryIds = DB::table('ledger_lines as c')->join('ledger_accounts as ca', 'ca.id', '=', 'c.account_id')->where('ca.id_cabang', $branch)->where('ca.cash_account', true)->select('c.entry_id');
        $counterparts = !$request->has('data_table') && $request->attributes->get('financial_report') === 'cash' ? DB::table('ledger_lines as l')->join('ledger_entries as e', 'e.id', '=', 'l.entry_id')->join('ledger_accounts as a', 'a.id', '=', 'l.account_id')->whereIn('e.id', $cashEntryIds)->where('e.id_cabang', $branch)->whereBetween('e.date', [$start, $end])->where('a.cash_account', false)->select('a.code', 'l.debit', 'l.credit')->get() : collect();
        $cashFlows = ['Operasional' => 0, 'Investasi' => 0, 'Pendanaan' => 0];
        foreach ($counterparts as $line) {
            $category = in_array($line->code, ['1601', '1602', '1202'], true) ? 'Investasi' : (in_array($line->code, ['3101', '3102', '2102'], true) ? 'Pendanaan' : 'Operasional');
            $cashFlows[$category] += (int) $line->credit - (int) $line->debit;
        }
        $sales = $module === 'retur' && !$request->has('data_table') ? DB::table('penjualan')->where('id_cabang', $branch)->whereNotNull('posted_at')->whereNull('cancelled_at')->orderByDesc('id_penjualan')->get() : collect();
        $purchases = $module === 'retur' && !$request->has('data_table') ? DB::table('pembelian')->where('id_cabang', $branch)->whereNotNull('posted_at')->whereNull('cancelled_at')->orderByDesc('id_pembelian')->get() : collect();
        $data = compact('module', 'start', 'end', 'accounts', 'balances', 'periodBalances', 'profit', 'cumulativeProfit', 'products', 'movements', 'returns', 'entries', 'assets', 'loans', 'deferrals', 'periods', 'sales', 'purchases', 'reconciliations', 'cashFlows', 'assetRows', 'loanRows', 'deferralRows', 'serviceOptions', 'serviceRows', 'servicePayments', 'bankRows', 'bankCandidates', 'taxMonth', 'taxProfile', 'taxEvents', 'taxInvoices', 'taxReturns', 'taxReturnOptions', 'taxPayments', 'years');
        if ($request->has('data_table')) {
            $records = $data[$definition['records']];
            $rows = [];
            foreach ($records as $offset => $row) {
                $cells = [$records->firstItem() + $offset];
                foreach ($definition['columns'] as $column) {
                    $cells[] = view('business.table-cell', array_merge($data, ['row'=>$row,'column'=>$column,'rowAction'=>$definition['rowAction']]))->render();
                }
                $rows[] = $cells;
            }
            return response()->json(['draw'=>(int)$request->draw,'recordsTotal'=>$this->tableTotals[$request->data_table] ?? $records->total(),'recordsFiltered'=>$records->total(),'data'=>$rows,'dialogs'=>$module==='rekonsiliasi' ? view('business.bank-dialogs',$data)->render() : '']);
        }
        $financialReport = $request->attributes->get('financial_report');
        return view('business.index', array_merge($data, compact('financialReport')));
    }

    public function financialReport(Request $request, string $report)
    {
        abort_unless(in_array($report, ['profit','balance','cash','tax'], true), 404);
        $request->attributes->set('financial_report', $report);
        return $this->index($request, $report === 'tax' ? 'pajak' : 'akuntansi');
    }

    public function details(string $type, int $id)
    {
        abort_unless(in_array($type, ['penjualan', 'pembelian'], true), 404);
        $header = DB::table($type)->where('id_' . $type, $id)->where('id_cabang', auth()->user()->id_cabang)->whereNotNull('posted_at')->whereNull('cancelled_at')->first();
        abort_unless($header, 404);
        $rows = DB::table($type . '_detail as d')->join('produk as p', 'p.id_produk', '=', 'd.id_produk')->where('d.id_' . $type, $id)->select('d.*', 'p.nama_produk')->get();
        foreach ($rows as $row) {
            $row->returned = (int) DB::table('stock_returns')->where('type', $type)->where('detail_id', $row->{'id_' . $type . '_detail'})->sum('quantity');
        }
        return response()->json($rows);
    }

    public function store(Request $request, string $action)
    {
        $branch = (int) auth()->user()->id_cabang;
        $dateRules = 'required|date_format:Y-m-d|before_or_equal:today';
        $finance = app(BusinessFinanceService::class);
        switch ($action) {
            case 'service-pay':
                $d = $request->validate(['date' => $dateRules, 'id' => 'required|integer', 'cash' => 'required|string', 'amount' => 'required|integer|min:1|max:2000000000', 'description' => 'nullable|string|max:350']);
                if (blank($d['description'] ?? null)) {
                    $service = DB::table('servis')->where('id_cabang', $branch)->where('id_servis', (int) $d['id'])->first();
                    $d['description'] = 'Pembayaran servis ' . ($service->kode_servis ?? $d['id']);
                }
                app(\App\Services\ServicePaymentService::class)->pay($branch, (int) $d['id'], $d);
                break;
            case 'service-cancel-payment':
                $d = $request->validate(['date' => $dateRules, 'id' => 'required|integer']);
                app(\App\Services\ServicePaymentService::class)->cancel($branch, (int) $d['id'], $d['date']);
                break;
            case 'year-close':
                $d = $request->validate(['year' => 'required|integer|min:2000|max:2100']);
                $finance->closeYear($branch, (int) $d['year']);
                break;
            case 'bank-import':
                $d = $request->validate(['cash' => 'required|string', 'file' => 'required|file|max:5120']);
                $result = app(\App\Services\BankReconciliationService::class)->import($branch, $d['cash'], $request->file('file')->getRealPath());
                return back()->with('success', $result['added'] . ' baris diimpor; ' . $result['skipped'] . ' duplikat dilewati.');
            case 'bank-match':
                $d = $request->validate(['id' => 'required|integer', 'line_id' => 'required|integer']);
                app(\App\Services\BankReconciliationService::class)->match($branch, (int) $d['id'], (int) $d['line_id']);
                break;
            case 'bank-unmatch':
                $d = $request->validate(['id' => 'required|integer']);
                app(\App\Services\BankReconciliationService::class)->unmatch($branch, (int) $d['id']);
                break;
            case 'bank-adjust':
                $d = $request->validate(['id' => 'required|integer', 'counterpart' => 'required|in:6101,4103,4104']);
                app(\App\Services\BankReconciliationService::class)->adjust($branch, (int) $d['id'], $d['counterpart']);
                break;
            case 'tax-profile':
                $d = $request->validate(['name' => 'required|string|max:200', 'npwp' => 'required|regex:/^\d{16}$/', 'nitku' => 'required|regex:/^\d{22}$/', 'address' => 'required|string|max:500', 'pkp' => 'required|boolean']);
                DB::table('tax_profiles')->updateOrInsert(['id_cabang' => $branch], $d + ['created_at' => now(), 'updated_at' => now()]);
                break;
            case 'tax-invoice':
                $d = $request->validate(['source_type' => 'required|in:penjualan,pembelian', 'source_id' => 'required|integer', 'date' => $dateRules, 'invoice_number' => 'required|string|max:100', 'party_name' => 'required|string|max:200', 'party_npwp' => 'required|regex:/^\d{16}$/', 'creditable' => 'required|boolean']);
                $source = DB::table($d['source_type'])->where('id_cabang', $branch)->where('id_' . $d['source_type'], $d['source_id'])->whereNotNull('posted_at')->first();
                abort_unless($source, 404);
                if ($d['date'] !== substr($source->posted_at, 0, 10)) { app(LedgerService::class)->fail('Tanggal faktur harus sesuai tanggal transaksi sumber.'); }
                if (DB::table('tax_returns')->where('id_cabang', $branch)->where('status', 'filed')->where('month', '<=', $d['date'])->where('month', '>=', substr($d['date'], 0, 7) . '-01')->exists()) {
                    app(LedgerService::class)->fail('Metadata faktur masa yang sudah dilaporkan dikunci. Buat koreksi melalui proses perpajakan yang sesuai.');
                }
                if (DB::table('tax_invoices')->where('id_cabang', $branch)->where('source_type', $d['source_type'])->where('invoice_number', $d['invoice_number'])->where('source_id', '!=', $d['source_id'])->exists()) { app(LedgerService::class)->fail('Nomor faktur sudah digunakan transaksi lain.'); }
                DB::table('tax_invoices')->updateOrInsert(['id_cabang' => $branch, 'source_type' => $d['source_type'], 'source_id' => $d['source_id']], $d + ['created_at' => now(), 'updated_at' => now()]);
                break;
            case 'tax-prepare':
                $d = $request->validate(['month' => 'required|date_format:Y-m']);
                app(\App\Services\TaxReportingService::class)->prepare($branch, $d['month'] . '-01');
                break;
            case 'tax-pay':
                $d = $request->validate(['date' => $dateRules, 'id' => 'required|integer', 'cash' => 'required|string', 'amount' => 'required|integer|min:1|max:2000000000', 'receipt' => 'required|string|max:100']);
                app(\App\Services\TaxReportingService::class)->payment($branch, (int) $d['id'], $d);
                break;
            case 'tax-filed':
                $d = $request->validate(['date' => $dateRules, 'id' => 'required|integer', 'receipt' => 'required|string|max:100']);
                app(\App\Services\TaxReportingService::class)->filed($branch, (int) $d['id'], $d['date'], $d['receipt']);
                break;
            case 'stock':
                $d = $request->validate(['date' => $dateRules, 'product' => 'required|integer', 'quantity' => 'required|integer|min:0|max:1000000', 'type' => 'required|in:internal,damaged,opname', 'reason' => 'required|string|max:400']);
                if ($d['type'] !== 'opname' && $d['quantity'] === '0') {
                    app(LedgerService::class)->fail('Jumlah barang harus lebih dari nol.');
                }
                app(StockLedgerService::class)->adjustment($branch, (int) $d['product'], (int) $d['quantity'], $d['type'], $d['reason'], $d['date'] . ' ' . now()->format('H:i:s'));
                break;
            case 'return':
                $d = $request->validate(['date' => $dateRules, 'type' => 'required|in:penjualan,pembelian', 'transaction_id' => 'required|integer', 'detail_id' => 'required|integer', 'quantity' => 'required|integer|min:1', 'restock' => 'nullable|boolean', 'method' => 'required|in:tunai,transfer_bank,qris', 'reason' => 'required|string|max:400']);
                app(BusinessTransactionService::class)->returnItem($branch, $d);
                break;
            case 'money':
                $d = $request->validate(['date' => $dateRules, 'type' => 'required|in:opening,capital,withdrawal,distribution,income,expense,transfer,tax', 'cash' => 'required|string', 'destination' => 'required_if:type,transfer|nullable|string', 'amount' => 'required|integer|min:1|max:2000000000', 'description' => 'required|string|max:400']);
                $finance->money($branch, $d);
                break;
            case 'reconcile':
                $d = $request->validate(['date' => $dateRules, 'cash' => 'required|string', 'amount' => 'required|integer|min:0|max:2000000000', 'description' => 'required|string|max:400']);
                $finance->cashAccount($branch, $d['cash']);
                $balance = app(LedgerService::class)->balances($branch, $d['date'])->firstWhere('code', $d['cash']);
                DB::table('cash_reconciliations')->insert(['id_cabang' => $branch, 'account_id' => $balance->id, 'date' => $d['date'], 'statement_balance' => $d['amount'], 'system_balance' => $balance->balance, 'note' => $d['description'], 'id_user' => auth()->id(), 'created_at' => now(), 'updated_at' => now()]);
                break;
            case 'account':
                $d = $request->validate(['name' => 'required|string|max:150']);
                DB::transaction(function () use ($branch, $d) {
                    $row = DB::table('cabang')->where('id_cabang', $branch)->lockForUpdate()->first();
                    abort_unless($row, 404);
                    $max = DB::table('ledger_accounts')->where('id_cabang', $branch)->where('code', 'like', '11%')->max('code');
                    $code = (string) max(1110, (int) $max + 1);
                    DB::table('ledger_accounts')->insert(['id_cabang' => $branch, 'code' => $code, 'name' => $d['name'], 'type' => 'asset', 'cash_account' => true, 'created_at' => now(), 'updated_at' => now()]);
                });
                break;
            case 'loan':
                $d = $request->validate(['date' => $dateRules, 'party' => 'required|string|max:150', 'direction' => 'required|in:borrowed,lent', 'due_date' => 'required|date_format:Y-m-d|after_or_equal:date', 'cash' => 'required|string', 'amount' => 'required|integer|min:1|max:2000000000']);
                $finance->loan($branch, $d);
                break;
            case 'repay':
                $d = $request->validate(['date' => $dateRules, 'id' => 'required|integer', 'principal' => 'required|integer|min:0|max:2000000000', 'interest' => 'required|integer|min:0|max:2000000000', 'cash' => 'required|string']);
                $finance->repay($branch, (int) $d['id'], $d);
                break;
            case 'asset':
                $d = $request->validate(['date' => $dateRules, 'name' => 'required|string|max:150', 'amount' => 'required|integer|min:1|max:2000000000', 'residual' => 'required|integer|min:0|lte:amount', 'life_months' => 'required|integer|min:1|max:1200', 'cash' => 'required|string']);
                $finance->asset($branch, $d);
                break;
            case 'dispose':
                $d = $request->validate(['date' => $dateRules, 'id' => 'required|integer', 'amount' => 'required|integer|min:0|max:2000000000', 'cash' => 'required|string']);
                $finance->dispose($branch, (int) $d['id'], $d);
                break;
            case 'defer':
                $d = $request->validate(['date' => $dateRules, 'type' => 'required|in:expense,income', 'description' => 'required|string|max:150', 'amount' => 'required|integer|min:1|max:2000000000', 'months' => 'required|integer|min:1|max:1200', 'cash' => 'required|string']);
                $finance->defer($branch, $d);
                break;
            case 'close':
                $d = $request->validate(['month' => 'required|date_format:Y-m', 'close' => 'nullable|boolean']);
                $finance->monthEnd($branch, $d['month'] . '-01', (bool) ($d['close'] ?? false));
                break;
            case 'journal':
                $d = $request->validate(['date' => $dateRules, 'description' => 'required|string|max:400', 'debit_account' => 'required|string', 'credit_account' => 'required|string|different:debit_account', 'amount' => 'required|integer|min:1|max:2000000000']);
                $restricted = ['1101', '1102', '1103', '1201', '1202', '1203', '1301', '1601', '1602', '2101', '2102', '1501', '2301', '3103'];
                $cashSelected = DB::table('ledger_accounts')->where('id_cabang', $branch)->where('cash_account', true)->whereIn('code', [$d['debit_account'], $d['credit_account']])->exists();
                if ($cashSelected || in_array($d['debit_account'], $restricted) || in_array($d['credit_account'], $restricted)) {
                    app(LedgerService::class)->fail('Akun stok, aset, kas, utang/piutang, dan akrual harus melalui modul sumber.');
                }
                app(LedgerService::class)->post($branch, $d['date'], 'MANUAL-' . \Illuminate\Support\Str::uuid(), $d['description'], [[$d['debit_account'], (int) $d['amount'], 0], [$d['credit_account'], 0, (int) $d['amount']]]);
                break;
            case 'reverse':
                $d = $request->validate(['date' => $dateRules, 'id' => 'required|integer']);
                $entry = DB::table('ledger_entries')->where('id_cabang', $branch)->where('id', $d['id'])->first();
                abort_unless($entry, 404);
                if (! str_starts_with($entry->reference, 'MANUAL-') && ! str_starts_with($entry->reference, 'CASH-')) {
                    app(LedgerService::class)->fail('Batalkan transaksi melalui modul sumbernya.');
                }
                app(LedgerService::class)->reverse($branch, (int) $d['id'], $d['date']);
                break;
            default:
                abort(404);
        }
        return back()->with('success', 'Transaksi berhasil dicatat.');
    }

    public function export(Request $request)
    {
        $d = $request->validate(['start' => 'nullable|date_format:Y-m-d', 'end' => 'nullable|date_format:Y-m-d|after_or_equal:start']);
        $d['start'] = $d['start'] ?? now()->startOfMonth()->toDateString();
        $d['end'] = $d['end'] ?? now()->endOfMonth()->toDateString();
        $rows = DB::table('ledger_lines as l')->join('ledger_entries as e', 'e.id', '=', 'l.entry_id')->join('ledger_accounts as a', 'a.id', '=', 'l.account_id')->where('e.id_cabang', auth()->user()->id_cabang)->whereBetween('e.date', [$d['start'], $d['end']])->orderBy('e.date')->orderBy('e.id')->select('e.date', 'e.reference', 'e.description', 'a.code', 'a.name', 'l.debit', 'l.credit')->get();
        return response()->streamDownload(function () use ($rows) {
            $stream = fopen('php://output', 'w');
            fputcsv($stream, ['Tanggal', 'Referensi', 'Keterangan', 'Kode Akun', 'Akun', 'Debit', 'Kredit']);
            foreach ($rows as $row) {
                $values = array_map(fn ($value) => is_string($value) && preg_match('/^[=+@\-]/', $value) ? "'" . $value : $value, (array) $row);
                fputcsv($stream, $values);
            }
            fclose($stream);
        }, 'jurnal-' . $d['start'] . '-' . $d['end'] . '.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function listing($query, array $columns, string $search, int $perPage, string $page)
    {
        $definition = config('business_tables.' . $page);
        if (!in_array(request()->attributes->get('business_module'), (array)$definition['module'], true)) {
            return new \Illuminate\Pagination\LengthAwarePaginator([], 0, $perPage, 1);
        }
        if (request()->has('data_table')) {
            if (request('data_table') !== $page) {
                return new \Illuminate\Pagination\LengthAwarePaginator([], 0, $perPage, 1);
            }
            $this->tableTotals[$page] = (clone $query)->count();
            $definition = config('business_tables.' . $page);
            $sort = $definition['columns'][(int)request('order.0.column', 0)-1]['sort'] ?? null;
            if ($sort) {
                $originalOrder = $query->orders;
                $query->reorder($sort, request('order.0.dir','asc'));
                foreach ($originalOrder ?? [] as $order) {
                    if ($order['column'] !== $sort) $query->orderBy($order['column'], $order['direction']);
                }
            }
            request()->merge([$page => intdiv((int)request('offset'), $perPage)+1]);
        }
        if ($search !== '') {
            $query->where(function ($nested) use ($columns, $search) {
                foreach ($columns as $column) { $nested->orWhere($column, 'like', '%' . $search . '%'); }
            });
        }
        return $query->paginate($perPage, ['*'], $page)->withQueryString();
    }

    public function bankTemplate()
    {
        return response("date,reference,description,amount\n2026-10-01,CONTOH-001,Contoh uang masuk,100000\n2026-10-02,CONTOH-002,Contoh biaya bank,-6500\n", 200, ['Content-Type' => 'text/csv; charset=UTF-8', 'Content-Disposition' => 'attachment; filename="template-rekening-koran.csv"']);
    }

    public function taxExport(int $id, string $format)
    {
        $branch = (int) auth()->user()->id_cabang;
        $return = DB::table('tax_returns')->where('id_cabang', $branch)->where('id', $id)->first();
        abort_unless($return, 404);
        if ($format === 'xml') {
            return response(app(\App\Services\TaxReportingService::class)->retailXml($branch, $id), 200, ['Content-Type' => 'application/xml', 'Content-Disposition' => 'attachment; filename="ppn-retail-' . $return->month . '-r' . $return->revision . '.xml"']);
        }
        $events = json_decode($return->snapshot, true, 512, JSON_THROW_ON_ERROR);
        $profile = DB::table('tax_profiles')->where('id_cabang', $branch)->first();
        if ($format === 'pdf') {
            return \PDF::loadView('business.tax-print', compact('return', 'events', 'profile'))->setPaper('a4', 'landscape')->download('kertas-kerja-ppn-' . $return->month . '.pdf');
        }
        abort_unless($format === 'csv', 404);
        return response()->streamDownload(function () use ($events) {
            $stream = fopen('php://output', 'w');
            fputcsv($stream, ['Tanggal', 'Referensi', 'Sumber', 'ID Transaksi', 'Nomor Faktur', 'NPWP', 'Nama', 'DPP', 'PPN', 'Masukan Dikreditkan']);
            foreach ($events as $event) {
                $values = [$event['date'], $event['reference'], $event['source_type'], $event['source_id'], $event['invoice_number'], $event['party_npwp'], $event['party_name'], $event['dpp'], $event['tax'], $event['creditable'] ? 'Ya' : 'Tidak'];
                fputcsv($stream, array_map(fn ($v) => is_string($v) && preg_match('/^[=+@\-]/', $v) ? "'" . $v : $v, $values));
            }
            fclose($stream);
        }, 'kertas-kerja-ppn-' . $return->month . '.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
