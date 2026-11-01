<?php

declare(strict_types=1);

namespace Vitaliq\Support;

/**
 * Server-rendered SVG charts for app screens: no script, so they work under
 * the strict CSP and without JavaScript. Straight segments, 4-5 faint
 * horizontal gridlines, round ticks above the maximum, one hue on the main
 * series (CSS class .s1), grey for the rest. Every chart carries a role="img"
 * label; callers put the exact figures in a table beside or under it.
 */
final class SvgChart
{
    private const W = 640;
    private const H = 200;
    private const M = ['t' => 12, 'r' => 12, 'b' => 26, 'l' => 40];

    /**
     * Round step so that 4-5 ticks cover $max.
     *
     * @return array{0:float,1:float} [top, step]
     */
    public static function niceScale(float $max, int $ticks = 4): array
    {
        if ($max <= 0) {
            return [$ticks, 1.0];
        }
        $raw = $max / $ticks;
        $mag = 10 ** floor(log10($raw));
        $step = $mag;
        foreach ([1, 2, 2.5, 5, 10] as $m) {
            if ($m * $mag >= $raw) {
                $step = $m * $mag;
                break;
            }
        }
        $top = ceil($max / $step) * $step;
        if ($top <= $max) {
            $top += $step;
        }
        return [$top, $step];
    }

    private static function fmt(float $v): string
    {
        return abs($v - round($v)) < 1e-9 ? number_format($v) : rtrim(rtrim(number_format($v, 2), '0'), '.');
    }

    /**
     * Columns over time or categories.
     *
     * @param list<array{label:string,value:float|int,highlight?:bool}> $data
     */
    public static function bars(array $data, string $title, int $labelEvery = 0): string
    {
        $m = self::M;
        $pw = self::W - $m['l'] - $m['r'];
        $ph = self::H - $m['t'] - $m['b'];
        $max = max(0, ...array_map(static fn ($d) => (float) $d['value'], $data ?: [['value' => 0]]));
        [$top, $step] = self::niceScale($max);
        $n = max(1, count($data));
        $bw = $pw / $n;
        $labelEvery = $labelEvery > 0 ? $labelEvery : (int) max(1, ceil($n / 7));
        $out = self::open($title);
        $out .= self::grid($top, $step, $pw, $ph);
        foreach ($data as $i => $d) {
            $h = $top > 0 ? ((float) $d['value'] / $top) * $ph : 0;
            $x = $m['l'] + $i * $bw + $bw * 0.15;
            $y = $m['t'] + $ph - $h;
            $cls = !empty($d['highlight']) ? 's1' : 's2';
            if ($h > 0) {
                $out .= sprintf('<rect class="%s" x="%.1f" y="%.1f" width="%.1f" height="%.1f"><title>%s: %s</title></rect>', $cls, $x, $y, $bw * 0.7, $h, e($d['label']), self::fmt((float) $d['value']));
            }
            if ($i % $labelEvery === 0) {
                $out .= sprintf('<text class="tick" x="%.1f" y="%d" text-anchor="middle">%s</text>', $m['l'] + $i * $bw + $bw / 2, self::H - 8, e($d['label']));
            }
        }
        return $out . '</svg>';
    }

    /**
     * One line, with an optional uncertainty band and a forecast tail.
     * Points after $forecastFrom are dashed; band holds [low, high] per point.
     *
     * @param list<array{label:string,value:?float,low?:?float,high?:?float}> $data
     */
    public static function line(array $data, string $title, ?int $forecastFrom = null, ?float $reference = null, string $referenceLabel = ''): string
    {
        $m = self::M;
        $pw = self::W - $m['l'] - $m['r'];
        $ph = self::H - $m['t'] - $m['b'];
        $vals = [];
        foreach ($data as $d) {
            foreach (['value', 'high'] as $k) {
                if (isset($d[$k])) {
                    $vals[] = (float) $d[$k];
                }
            }
        }
        if ($reference !== null) {
            $vals[] = $reference;
        }
        [$top, $step] = self::niceScale($vals === [] ? 0 : max($vals));
        $n = count($data);
        $x = static fn (int $i): float => $m['l'] + ($n <= 1 ? $pw / 2 : $i * $pw / ($n - 1));
        $y = static fn (float $v): float => $m['t'] + $ph - ($top > 0 ? $v / $top * $ph : 0);
        $out = self::open($title) . self::grid($top, $step, $pw, $ph);

        $upper = $lower = [];
        foreach ($data as $i => $d) {
            if (isset($d['low'], $d['high'])) {
                $upper[] = sprintf('%.1f,%.1f', $x($i), $y((float) $d['high']));
                array_unshift($lower, sprintf('%.1f,%.1f', $x($i), $y(max(0, (float) $d['low']))));
            }
        }
        if ($upper !== []) {
            $out .= '<polygon class="band" points="' . implode(' ', array_merge($upper, $lower)) . '"/>';
        }
        if ($reference !== null) {
            $out .= sprintf('<line class="ref" x1="%d" x2="%d" y1="%.1f" y2="%.1f"/>', $m['l'], self::W - $m['r'], $y($reference), $y($reference));
            $out .= sprintf('<text class="tick" x="%d" y="%.1f" text-anchor="end">%s</text>', self::W - $m['r'], $y($reference) - 4, e($referenceLabel));
        }
        $solid = $dash = [];
        foreach ($data as $i => $d) {
            if ($d['value'] === null) {
                continue;
            }
            $pt = sprintf('%.1f,%.1f', $x($i), $y((float) $d['value']));
            if ($forecastFrom === null || $i <= $forecastFrom) {
                $solid[] = $pt;
            }
            if ($forecastFrom !== null && $i >= $forecastFrom) {
                $dash[] = $pt;
            }
        }
        if (count($solid) > 1) {
            $out .= '<polyline class="s1 line" points="' . implode(' ', $solid) . '"/>';
        }
        if (count($dash) > 1) {
            $out .= '<polyline class="s1 line dashed" points="' . implode(' ', $dash) . '"/>';
        }
        $every = (int) max(1, ceil($n / 7));
        foreach ($data as $i => $d) {
            if ($i % $every === 0 || $i === $n - 1 && $n <= 14) {
                $out .= sprintf('<text class="tick" x="%.1f" y="%d" text-anchor="%s">%s</text>', $x($i), self::H - 8, $i === 0 ? 'start' : ($i === $n - 1 ? 'end' : 'middle'), e($d['label']));
            }
        }
        return $out . '</svg>';
    }

    /**
     * An interval bar for a proportion: the 95% interval as a band, the
     * estimate as a tick, and an optional reference (the overall rate) as a
     * red hairline. Values are proportions; $max sets the right edge.
     */
    public static function ciBar(?float $p, ?float $low, ?float $high, float $max, ?float $reference = null): string
    {
        if ($p === null || $low === null || $high === null || $max <= 0) {
            return '<span class="faint">—</span>';
        }
        $x = static fn (float $v): float => max(0.0, min(100.0, $v / $max * 100));
        $out = '<svg class="ci-svg" viewBox="0 0 100 12" preserveAspectRatio="none" aria-hidden="true">';
        $out .= '<rect class="track" x="0" y="4" width="100" height="4" rx="2"/>';
        $out .= sprintf('<rect class="band" x="%.2f" y="3" width="%.2f" height="6" rx="2"/>', $x($low), max(0.8, $x($high) - $x($low)));
        if ($reference !== null) {
            $out .= sprintf('<rect class="refline" x="%.2f" y="0" width="0.6" height="12"/>', $x($reference));
        }
        return $out . sprintf('<rect class="est" x="%.2f" y="1" width="1.2" height="10"/></svg>', max(0.0, $x($p) - 0.6));
    }

    private static function open(string $title): string
    {
        return sprintf('<svg class="vq-chart" viewBox="0 0 %d %d" role="img" aria-label="%s">', self::W, self::H, e($title));
    }

    private static function grid(float $top, float $step, float $pw, float $ph): string
    {
        $m = self::M;
        $out = '';
        for ($v = 0.0; $v <= $top + 1e-9; $v += $step) {
            $yy = $m['t'] + $ph - ($top > 0 ? $v / $top * $ph : 0);
            $out .= sprintf('<line class="%s" x1="%d" x2="%.1f" y1="%.1f" y2="%.1f"/>', $v == 0.0 ? 'base' : 'gl', $m['l'], $m['l'] + $pw, $yy, $yy);
            $out .= sprintf('<text class="tick" x="%d" y="%.1f" text-anchor="end">%s</text>', $m['l'] - 6, $yy + 4, self::fmt($v));
        }
        return $out;
    }
}
