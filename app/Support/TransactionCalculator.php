<?php

namespace App\Support;

class TransactionCalculator
{
    public function calculateFromSubtotal(int $subtotal, float $diskonPersen = 0, float $ppnPersen = 0, int $nominalMasuk = 0): array
    {
        $subtotal = max($subtotal, 0);
        $diskonPersen = $this->sanitizePercent($diskonPersen);
        $ppnPersen = max((float) $ppnPersen, 0);
        $diskonNominal = (int) round($subtotal * $diskonPersen / 100);
        $dpp = max($subtotal - $diskonNominal, 0);
        $ppnNominal = (int) round($dpp * $ppnPersen / 100);
        $total = $dpp + $ppnNominal;
        $nominalMasuk = max($nominalMasuk, 0);

        return [
            'subtotal' => $subtotal,
            'diskon_persen' => $diskonPersen,
            'diskon_nominal' => $diskonNominal,
            'dpp' => $dpp,
            'ppn_persen' => $ppnPersen,
            'ppn_nominal' => $ppnNominal,
            'grand_total' => $total,
            'dibayar' => min($nominalMasuk, $total),
            'sisa' => max($total - min($nominalMasuk, $total), 0),
            'kembali' => max($nominalMasuk - $total, 0),
        ];
    }

    public function allocateTransactionLines(iterable $lines, int $discountNominal, int $ppnNominal): array
    {
        $items = [];
        foreach ($lines as $line) {
            $items[] = [
                'subtotal' => max((int) data_get($line, 'subtotal'), 0),
            ];
        }

        if (count($items) === 0) {
            return [];
        }

        $weights = array_map(function ($item) {
            return $item['subtotal'];
        }, $items);

        $discountAllocations = $this->allocateByWeights($weights, max($discountNominal, 0));
        $dppWeights = [];
        foreach ($items as $index => $item) {
            $dppWeights[$index] = max($item['subtotal'] - $discountAllocations[$index], 0);
        }

        $ppnAllocations = $this->allocateByWeights($dppWeights, max($ppnNominal, 0));

        $results = [];
        foreach ($items as $index => $item) {
            $dpp = max($item['subtotal'] - $discountAllocations[$index], 0);
            $results[$index] = [
                'subtotal' => $item['subtotal'],
                'diskon_nominal' => $discountAllocations[$index],
                'dpp' => $dpp,
                'ppn_nominal' => $ppnAllocations[$index],
                'grand_total' => $dpp + $ppnAllocations[$index],
            ];
        }

        return $results;
    }

    protected function allocateByWeights(array $weights, int $target): array
    {
        $target = max($target, 0);
        $cleanWeights = array_map(function ($weight) {
            return max((int) $weight, 0);
        }, $weights);
        $sumWeights = array_sum($cleanWeights);

        if ($target === 0 || $sumWeights === 0) {
            return array_fill(0, count($cleanWeights), 0);
        }

        $allocations = [];
        $fractions = [];
        $distributed = 0;

        foreach ($cleanWeights as $index => $weight) {
            $raw = ($target * $weight) / $sumWeights;
            $base = (int) floor($raw);
            $allocations[$index] = $base;
            $fractions[$index] = $raw - $base;
            $distributed += $base;
        }

        $remaining = $target - $distributed;
        arsort($fractions);

        foreach (array_keys($fractions) as $index) {
            if ($remaining <= 0) {
                break;
            }

            $allocations[$index]++;
            $remaining--;
        }

        ksort($allocations);

        return array_values($allocations);
    }

    protected function sanitizePercent(float $value, float $max = 100): float
    {
        return min(max($value, 0), $max);
    }
}
