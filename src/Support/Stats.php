<?php

declare(strict_types=1);

namespace Vitaliq\Support;

/**
 * Small, dependency-free statistics used by the surveillance screens.
 * Methods are standard and documented in docs/SURVEILLANCE_METHODS.md.
 */
final class Stats
{
    /** Two-sided 95% normal quantile. */
    public const Z95 = 1.959963984540054;

    /**
     * Wilson score interval for a proportion (95%).
     *
     * @return array{p:?float,low:?float,high:?float}
     */
    public static function wilson(int $successes, int $n, float $z = self::Z95): array
    {
        if ($n <= 0) {
            return ['p' => null, 'low' => null, 'high' => null];
        }
        $p = $successes / $n;
        $z2 = $z * $z;
        $den = 1 + $z2 / $n;
        $centre = ($p + $z2 / (2 * $n)) / $den;
        $half = $z * sqrt($p * (1 - $p) / $n + $z2 / (4 * $n * $n)) / $den;
        return ['p' => $p, 'low' => $successes === 0 ? 0.0 : max(0.0, $centre - $half), 'high' => $successes === $n ? 1.0 : min(1.0, $centre + $half)];
    }

    /**
     * Log-linear trend of daily counts: ln(y + 0.5) = a + b t, ordinary least
     * squares. Returns the daily growth rate with a 95% interval, the
     * doubling (or halving) time, and a projection with a 95% prediction
     * interval for $ahead days. Needs at least 7 points.
     *
     * @param list<int|float> $counts consecutive daily counts, oldest first
     * @return array{n:int,growth:float,growth_low:float,growth_high:float,doubling_days:?float,projection:list<array{t:int,value:float,low:float,high:float}>}|null
     */
    public static function logLinearTrend(array $counts, int $ahead = 7): ?array
    {
        $n = count($counts);
        if ($n < 7) {
            return null;
        }
        $ys = array_map(static fn ($c) => log(max(0, (float) $c) + 0.5), $counts);
        $ts = range(0, $n - 1);
        $tMean = array_sum($ts) / $n;
        $yMean = array_sum($ys) / $n;
        $sxx = $sxy = 0.0;
        foreach ($ts as $i => $t) {
            $sxx += ($t - $tMean) ** 2;
            $sxy += ($t - $tMean) * ($ys[$i] - $yMean);
        }
        $b = $sxy / $sxx;
        $a = $yMean - $b * $tMean;
        $sse = 0.0;
        foreach ($ts as $i => $t) {
            $sse += ($ys[$i] - ($a + $b * $t)) ** 2;
        }
        $s = sqrt($sse / ($n - 2));
        $seB = $s / sqrt($sxx);
        $tq = self::tQuantile975($n - 2);
        $projection = [];
        for ($k = 1; $k <= $ahead; $k++) {
            $t = $n - 1 + $k;
            $fit = $a + $b * $t;
            $sePred = $s * sqrt(1 + 1 / $n + (($t - $tMean) ** 2) / $sxx);
            $projection[] = [
                't' => $k,
                'value' => max(0.0, exp($fit) - 0.5),
                'low' => max(0.0, exp($fit - $tq * $sePred) - 0.5),
                'high' => max(0.0, exp($fit + $tq * $sePred) - 0.5),
            ];
        }
        $growth = exp($b) - 1;
        return [
            'n' => $n,
            'growth' => $growth,
            'growth_low' => exp($b - $tq * $seB) - 1,
            'growth_high' => exp($b + $tq * $seB) - 1,
            'doubling_days' => abs($b) < 1e-9 ? null : log(2) / $b,
            'projection' => $projection,
        ];
    }

    /** Student t 97.5% quantile (two-sided 95%) for small df, normal beyond 30. */
    public static function tQuantile975(int $df): float
    {
        $table = [1 => 12.706, 2 => 4.303, 3 => 3.182, 4 => 2.776, 5 => 2.571, 6 => 2.447, 7 => 2.365, 8 => 2.306, 9 => 2.262, 10 => 2.228,
            11 => 2.201, 12 => 2.179, 13 => 2.160, 14 => 2.145, 15 => 2.131, 16 => 2.120, 17 => 2.110, 18 => 2.101, 19 => 2.093, 20 => 2.086,
            21 => 2.080, 22 => 2.074, 23 => 2.069, 24 => 2.064, 25 => 2.060, 26 => 2.056, 27 => 2.052, 28 => 2.048, 29 => 2.045, 30 => 2.042];
        return $df <= 0 ? INF : ($table[$df] ?? self::Z95);
    }

    /**
     * Mean and sample standard deviation.
     *
     * @param list<int|float> $xs
     * @return array{mean:float,sd:float}
     */
    public static function meanSd(array $xs): array
    {
        $n = count($xs);
        if ($n === 0) {
            return ['mean' => 0.0, 'sd' => 0.0];
        }
        $mean = array_sum($xs) / $n;
        $var = $n > 1 ? array_sum(array_map(static fn ($x) => ($x - $mean) ** 2, $xs)) / ($n - 1) : 0.0;
        return ['mean' => $mean, 'sd' => sqrt($var)];
    }

    /**
     * Trailing moving average; the first ($window - 1) entries are null.
     *
     * @param list<int|float> $xs
     * @return list<?float>
     */
    public static function movingAverage(array $xs, int $window = 7): array
    {
        $out = [];
        $sum = 0.0;
        foreach ($xs as $i => $x) {
            $sum += $x;
            if ($i >= $window) {
                $sum -= $xs[$i - $window];
            }
            $out[] = $i >= $window - 1 ? $sum / $window : null;
        }
        return $out;
    }
}
