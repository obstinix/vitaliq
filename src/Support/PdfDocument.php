<?php

declare(strict_types=1);

namespace Vitaliq\Support;

/**
 * A minimal PDF 1.4 writer for tabular reports (feature 29): A4 pages,
 * the standard Helvetica fonts (no embedding), headings, wrapped
 * paragraphs, ruled tables with right-aligned number columns, simple
 * column charts and automatic page breaks. Text is converted to
 * Windows-1252; characters outside it print as "?".
 */
final class PdfDocument
{
    private const W = 595.28;
    private const H = 841.89;
    private const MARGIN = 48.0;

    /** @var list<string> page content streams */
    private array $pages = [];
    private string $current = '';
    private float $y;
    private int $pageNo = 0;

    public function __construct(private readonly string $title, private readonly string $footer = '')
    {
        $this->newPage();
    }

    private function newPage(): void
    {
        if ($this->current !== '') {
            $this->pages[] = $this->current;
        }
        $this->pageNo++;
        $this->current = '';
        $this->y = self::H - self::MARGIN;
        $foot = trim($this->footer . '   Page ' . $this->pageNo);
        $this->raw(sprintf("0.36 0.42 0.48 rg BT /F1 8 Tf %.2f %.2f Td (%s) Tj ET 0 0 0 rg\n", self::MARGIN, 28, self::esc($foot)));
    }

    private function raw(string $ops): void
    {
        $this->current .= $ops;
    }

    private function need(float $height): void
    {
        if ($this->y - $height < self::MARGIN + 10) {
            $this->newPage();
        }
    }

    public static function esc(string $text): string
    {
        $enc = @iconv('UTF-8', 'Windows-1252//TRANSLIT', $text);
        if ($enc === false) {
            $enc = (string) preg_replace('/[^\x20-\x7E]/', '?', $text);
        }
        return strtr($enc, ['\\' => '\\\\', '(' => '\\(', ')' => '\\)', "\r" => '', "\n" => ' ']);
    }

    /** Approximate Helvetica width (average glyph 0.5 em, digits 0.556 em). */
    private static function width(string $text, float $size): float
    {
        return mb_strlen($text) * $size * 0.52;
    }

    private function textAt(float $x, float $y, string $text, float $size, bool $bold = false): void
    {
        $this->raw(sprintf("BT /%s %.1f Tf %.2f %.2f Td (%s) Tj ET\n", $bold ? 'F2' : 'F1', $size, $x, $y, self::esc($text)));
    }

    public function heading(string $text, float $size = 16): self
    {
        $this->need($size * 2);
        $this->y -= $size;
        $this->textAt(self::MARGIN, $this->y, $text, $size, true);
        $this->y -= $size * 0.6;
        return $this;
    }

    public function paragraph(string $text, float $size = 10, bool $muted = false): self
    {
        $maxW = self::W - 2 * self::MARGIN;
        $line = '';
        $lines = [];
        foreach (preg_split('/\s+/', trim($text)) ?: [] as $word) {
            $try = $line === '' ? $word : $line . ' ' . $word;
            if (self::width($try, $size) > $maxW && $line !== '') {
                $lines[] = $line;
                $line = $word;
            } else {
                $line = $try;
            }
        }
        if ($line !== '') {
            $lines[] = $line;
        }
        if ($muted) {
            $this->raw("0.30 0.36 0.42 rg\n");
        }
        foreach ($lines as $l) {
            $this->need($size * 1.5);
            $this->y -= $size * 1.4;
            $this->textAt(self::MARGIN, $this->y, $l, $size);
        }
        if ($muted) {
            $this->raw("0 0 0 rg\n");
        }
        $this->y -= $size * 0.6;
        return $this;
    }

    /**
     * @param list<string> $headers
     * @param list<list<string>> $rows
     * @param list<float> $widths fractions of the text width, summing to 1
     * @param list<int> $right indexes of right-aligned (numeric) columns
     */
    public function table(array $headers, array $rows, array $widths, array $right = []): self
    {
        $total = self::W - 2 * self::MARGIN;
        $cols = array_map(static fn ($w) => $w * $total, $widths);
        $size = 9.0;
        $rowH = 16.0;
        $draw = function (array $cells, bool $bold) use ($cols, $right, $size, $rowH): void {
            $this->need($rowH + 4);
            $this->y -= $rowH;
            $x = self::MARGIN;
            foreach ($cells as $i => $cell) {
                $cell = (string) $cell;
                $max = $cols[$i] - 6;
                while ($cell !== '' && self::width($cell, $size) > $max) {
                    $cell = mb_substr($cell, 0, -2) . '…';
                    if (mb_strlen($cell) <= 2) {
                        break;
                    }
                }
                $tx = in_array($i, $right, true) ? $x + $cols[$i] - 3 - self::width($cell, $size) : $x + 3;
                $this->textAt($tx, $this->y + 4.5, $cell, $size, $bold);
                $x += $cols[$i];
            }
            $this->raw(sprintf("%s %.2f %.2f m %.2f %.2f l S\n", $bold ? '0.55 G 0.8 w' : '0.88 G 0.5 w', self::MARGIN, $this->y, self::W - self::MARGIN, $this->y));
        };
        $draw($headers, true);
        foreach ($rows as $r) {
            $draw($r, false);
        }
        $this->raw("0 G 1 w\n");
        $this->y -= 10;
        return $this;
    }

    /**
     * A column chart, one value per label, with a baseline and the maximum labelled.
     *
     * @param list<array{label:string,value:float|int}> $data
     */
    public function columns(array $data, string $caption, float $height = 110): self
    {
        $this->need($height + 40);
        $total = self::W - 2 * self::MARGIN;
        $max = max(1, ...array_map(static fn ($d) => (float) $d['value'], $data ?: [['value' => 1]]));
        $n = max(1, count($data));
        $bw = $total / $n;
        $base = $this->y - $height;
        $this->raw("0.106 0.435 0.451 rg\n");
        foreach ($data as $i => $d) {
            $h = (float) $d['value'] / $max * ($height - 14);
            if ($h > 0) {
                $this->raw(sprintf("%.2f %.2f %.2f %.2f re f\n", self::MARGIN + $i * $bw + $bw * 0.15, $base, $bw * 0.7, $h));
            }
        }
        $this->raw("0 0 0 rg\n");
        $this->raw(sprintf("0.6 G 0.5 w %.2f %.2f m %.2f %.2f l S 0 G 1 w\n", self::MARGIN, $base, self::W - self::MARGIN, $base));
        $this->textAt(self::MARGIN, $this->y - 8, 'max ' . number_format($max), 7.5);
        $step = (int) max(1, ceil($n / 8));
        foreach ($data as $i => $d) {
            if ($i % $step === 0) {
                $this->textAt(self::MARGIN + $i * $bw, $base - 10, $d['label'], 7);
            }
        }
        $this->y = $base - 16;
        return $this->paragraph($caption, 8.5, true);
    }

    public function output(): string
    {
        $pages = $this->pages;
        $pages[] = $this->current;
        $objects = [];
        $objects[1] = '<< /Type /Catalog /Pages 2 0 R >>';
        $kids = [];
        $next = 5;
        $pageObjs = [];
        foreach ($pages as $content) {
            $pageObjs[] = [$next, $next + 1, $content];
            $kids[] = $next . ' 0 R';
            $next += 2;
        }
        $objects[2] = '<< /Type /Pages /Kids [' . implode(' ', $kids) . '] /Count ' . count($pages) . ' >>';
        $objects[3] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>';
        $objects[4] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>';
        foreach ($pageObjs as [$pageId, $contentId, $content]) {
            $objects[$pageId] = sprintf('<< /Type /Page /Parent 2 0 R /MediaBox [0 0 %.2f %.2f] /Resources << /Font << /F1 3 0 R /F2 4 0 R >> >> /Contents %d 0 R >>', self::W, self::H, $contentId);
            $objects[$contentId] = '<< /Length ' . strlen($content) . " >>\nstream\n" . $content . "endstream";
        }
        $infoId = $next;
        $objects[$infoId] = '<< /Title (' . self::esc($this->title) . ') /Producer (Vitaliq) >>';
        ksort($objects);
        $out = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
        $offsets = [];
        foreach ($objects as $id => $body) {
            $offsets[$id] = strlen($out);
            $out .= $id . " 0 obj\n" . $body . "\nendobj\n";
        }
        $xref = strlen($out);
        $out .= 'xref' . "\n0 " . ($infoId + 1) . "\n0000000000 65535 f \n";
        for ($i = 1; $i <= $infoId; $i++) {
            $out .= sprintf("%010d 00000 n \n", $offsets[$i]);
        }
        $out .= 'trailer << /Size ' . ($infoId + 1) . ' /Root 1 0 R /Info ' . $infoId . " 0 R >>\nstartxref\n" . $xref . "\n%%EOF\n";
        return $out;
    }
}
