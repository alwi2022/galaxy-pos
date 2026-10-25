<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class PaymentMethodService
{
    public const DEFAULTS = [
        'tunai' => ['Tunai', '1101', 'tunai'],
        'transfer_bank' => ['Bank / Transfer', '1102', 'bank'],
        'qris' => ['QRIS', '1103', 'qris'],
    ];

    public const TYPES = ['tunai' => 'Tunai', 'bank' => 'Bank', 'qris' => 'QRIS'];

    private array $cache = [];

    public function all(int $branch)
    {
        if (! isset($this->cache[$branch])) {
            if (DB::table('payment_methods')->where('id_cabang', $branch)->whereIn('code', array_keys(self::DEFAULTS))->count() < count(self::DEFAULTS)) {
                foreach (self::DEFAULTS as $code => [$name, $account, $type]) {
                    DB::table('payment_methods')->insertOrIgnore(['id_cabang' => $branch, 'code' => $code, 'type' => $type, 'name' => $name, 'account_code' => $account, 'active' => true, 'created_at' => now(), 'updated_at' => now()]);
                }
            }
            $this->cache[$branch] = DB::table('payment_methods')->where('id_cabang', $branch)->orderBy('id')->get();
        }
        return $this->cache[$branch];
    }

    public function options(int $branch, bool $activeOnly = true): array
    {
        return $this->all($branch)->when($activeOnly, fn ($items) => $items->where('active', true))->pluck('name', 'code')->all();
    }

    public function cashCode(int $branch, string $method): string
    {
        $row = $this->all($branch)->firstWhere('code', $method);
        return $row->account_code ?? (self::DEFAULTS[$method][1] ?? '1101');
    }

    public function storeBank(int $branch, array $data): void
    {
        DB::transaction(function () use ($branch, $data) {
            abort_unless(DB::table('cabang')->where('id_cabang', $branch)->lockForUpdate()->exists(), 404);
            $this->all($branch);
            $max = DB::table('ledger_accounts')->where('id_cabang', $branch)->where('code', 'like', '11%')->max('code');
            $code = (string) max(1110, (int) $max + 1);
            $name = $this->label($data);
            DB::table('ledger_accounts')->insert(['id_cabang' => $branch, 'code' => $code, 'name' => $name, 'type' => 'asset', 'cash_account' => true, 'created_at' => now(), 'updated_at' => now()]);
            DB::table('payment_methods')->insert($this->details($data) + ['id_cabang' => $branch, 'code' => 'bank_' . $code, 'type' => 'bank', 'name' => $name, 'account_code' => $code, 'active' => true, 'created_at' => now(), 'updated_at' => now()]);
        });
        unset($this->cache[$branch]);
    }

    public function updateBank(int $branch, int $id, array $data): void
    {
        $row = DB::table('payment_methods')->where('id_cabang', $branch)->where('id', $id)->where('type', 'bank')->first();
        abort_unless($row, 404);
        $name = $this->label($data);
        DB::transaction(function () use ($branch, $row, $data, $name) {
            DB::table('payment_methods')->where('id', $row->id)->update($this->details($data) + ['name' => $name, 'active' => (bool) $data['active'], 'updated_at' => now()]);
            DB::table('ledger_accounts')->where('id_cabang', $branch)->where('code', $row->account_code)->update(['name' => $name, 'updated_at' => now()]);
        });
        unset($this->cache[$branch]);
    }

    private function details(array $data): array
    {
        return collect($data)->only(['bank_name', 'account_number', 'account_holder', 'address', 'city', 'phone'])->map(fn ($v) => blank($v) ? null : trim($v))->all();
    }

    private function label(array $data): string
    {
        $label = trim($data['bank_name'] . ' ' . ($data['account_number'] ?? ''));
        return blank($data['account_holder'] ?? null) ? $label : $label . ' a/n ' . trim($data['account_holder']);
    }
}
