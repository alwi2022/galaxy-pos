<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    use HasFactory;

    protected $table = 'setting';
    protected $primaryKey = 'id_setting';
    protected $guarded = [];

    public static function defaultPpnPersen(): float
    {
        $setting = static::query()->first();

        return static::normalizePpnPersen($setting->ppn_default ?? 11);
    }

    public static function resolvePpnPersen($value = null): float
    {
        if ($value !== null && (float) $value > 0) {
            return static::normalizePpnPersen($value);
        }

        return static::defaultPpnPersen();
    }

    protected static function normalizePpnPersen($value): float
    {
        return min(max((float) $value, 0), 100);
    }
}
