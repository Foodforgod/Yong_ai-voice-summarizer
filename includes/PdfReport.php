<?php
declare(strict_types=1);

class PdfReport
{
    /**
     * Generates a valid A4 PDF report and writes it to $destPath.
     *
     * @param array<string, mixed> $meta
     * @param array<string, mixed> $summary
     */
    public static function generate(string $destPath, array $meta, array $summary): bool
    {
        $pdfBytes = self::buildPdf($meta, $summary);
        $written = @file_put_contents($destPath, $pdfBytes, LOCK_EX);
        return $written !== false && $written > 0;
    }

    /**
     * @param array<string, mixed> $meta
     * @param array<string, mixed> $summary
     */
    private static function buildPdf(array $meta, array $summary): string
    {
        // A4 in points: 595.28 x 841.89
        $pageWidth  = 595.28;
        $pageHeight = 841.89;
        $marginX    = 46.0;
        $topY       = 795.0;
        $bottomY    = 52.0;
        $maxWidth   = $pageWidth - ($marginX * 2);

        $pages = [];
        $buf   = '';
        $y     = $topY;

        $startPage = static function () use (&$buf, &$y, $topY): void {
            $buf = '';
            $y   = $topY;
        };

        $endPage = static function () use (&$pages, &$buf): void {
            $pages[] = $buf;
            $buf = '';
        };

        $ensureSpace = static function (float $neededHeight) use (&$y, $bottomY, $endPage, $startPage): void {
            if (($y - $neededHeight) < $bottomY) {
                $endPage();
                $startPage();
            }
        };

        $addParagraph = static function (
            string $text,
            float $fontSize,
            bool $bold,
            array $rgb,
            float $lineLeading,
            float $spaceAfter
        ) use (&$buf, &$y, $marginX, $maxWidth, $ensureSpace): void {
            $lines = self::wrapText($text, $fontSize, $maxWidth, $bold);
            if (empty($lines)) {
                return;
            }
            $totalHeight = count($lines) * $lineLeading;
            $ensureSpace($totalHeight + $spaceAfter);

            $fontKey = $bold ? '/F2' : '/F1';
            [$r, $g, $b] = $rgb;

            foreach ($lines as $line) {
                $enc = self::toWinAnsi($line);
                $buf .= sprintf(
                    "BT %s %.2f Tf %.3f %.3f %.3f rg 1 0 0 1 %.2f %.2f Tm (%s) Tj ET\n",
                    $fontKey,
                    $fontSize,
                    $r,
                    $g,
                    $b,
                    $marginX,
                    $y,
                    self::escapePdfString($enc)
                );
                $y -= $lineLeading;
            }
            $y -= $spaceAfter;
        };

        $addSectionHeading = static function (string $title) use (&$buf, &$y, $marginX, $maxWidth, $ensureSpace): void {
            $ensureSpace(28.0);
            $y -= 6.0;
            // Blue section heading (#1d4ed8) + subtle underline rule
            $buf .= sprintf(
                "BT /F2 12.5 Tf 0.114 0.306 0.847 rg 1 0 0 1 %.2f %.2f Tm (%s) Tj ET\n",
                $marginX,
                $y,
                self::escapePdfString(self::toWinAnsi($title))
            );
            $y -= 5.0;
            $buf .= sprintf(
                "0.75 0.84 0.98 RG 0.8 w %.2f %.2f m %.2f %.2f l S\n",
                $marginX,
                $y,
                $marginX + $maxWidth,
                $y
            );
            $y -= 12.0;
        };

        /**
         * @param array<int, string> $items
         */
        $addBulletList = static function (array $items, string $emptyText) use ($addParagraph): void {
            if (empty($items)) {
                $addParagraph($emptyText, 10.0, false, [0.392, 0.455, 0.545], 14.5, 8.0);
                return;
            }
            foreach ($items as $item) {
                $addParagraph('• ' . $item, 10.2, false, [0.118, 0.161, 0.231], 14.5, 4.0);
            }
            $addParagraph('', 10.0, false, [0.118, 0.161, 0.231], 4.0, 6.0);
        };

        $startPage();

        // Header banner bar
        $buf .= sprintf(
            "0.071 0.165 0.380 rg %.2f %.2f %.2f %.2f re f\n",
            $marginX,
            $y - 34.0,
            $maxWidth,
            42.0
        );
        $buf .= sprintf(
            "BT /F2 16 Tf 1 1 1 rg 1 0 0 1 %.2f %.2f Tm (%s) Tj ET\n",
            $marginX + 12.0,
            $y - 12.0,
            self::escapePdfString(self::toWinAnsi('AI Voice Recording Summary Report'))
        );
        $buf .= sprintf(
            "BT /F1 9.5 Tf 0.859 0.918 0.996 rg 1 0 0 1 %.2f %.2f Tm (%s) Tj ET\n",
            $marginX + 12.0,
            $y - 26.0,
            self::escapePdfString(self::toWinAnsi('Executive Intelligence & Multi-Section Analysis'))
        );
        $y -= 50.0;

        // Metadata box
        $metaLines = [
            'Recording Filename: ' . (string)($meta['filename'] ?? 'N/A'),
            'Audio Duration: ' . (string)($meta['duration'] ?? 'N/A'),
            'File Size: ' . (string)($meta['file_size'] ?? 'N/A'),
            'Summary Language: ' . (string)($meta['summary_language'] ?? 'N/A'),
            'Generated At: ' . (string)($meta['generated_at'] ?? 'N/A'),
        ];

        $metaBoxHeight = (count($metaLines) * 14.0) + 14.0;
        $buf .= sprintf(
            "0.945 0.969 1.000 rg %.2f %.2f %.2f %.2f re f\n",
            $marginX,
            $y - $metaBoxHeight + 8.0,
            $maxWidth,
            $metaBoxHeight
        );
        $buf .= sprintf(
            "0.749 0.859 0.996 RG 0.7 w %.2f %.2f %.2f %.2f re S\n",
            $marginX,
            $y - $metaBoxHeight + 8.0,
            $maxWidth,
            $metaBoxHeight
        );
        $y -= 6.0;
        foreach ($metaLines as $mLine) {
            $buf .= sprintf(
                "BT /F2 9.5 Tf 0.118 0.161 0.231 rg 1 0 0 1 %.2f %.2f Tm (%s) Tj ET\n",
                $marginX + 10.0,
                $y,
                self::escapePdfString(self::toWinAnsi($mLine))
            );
            $y -= 14.0;
        }
        $y -= 10.0;

        // Section 1: Overall Summary
        $addSectionHeading('1. Overall Summary');
        $addParagraph(
            (string)($summary['overall_summary'] ?? ''),
            10.5,
            false,
            [0.118, 0.161, 0.231],
            15.0,
            8.0
        );

        // Section 2: Main Topics Discussed
        $addSectionHeading('2. Main Topics Discussed');
        $addBulletList($summary['main_topics'] ?? [], 'No main topics were listed.');

        // Section 3: Important Points
        $addSectionHeading('3. Important Points');
        $addBulletList($summary['important_points'] ?? [], 'No important points were listed.');

        // Section 4: Key Metrics, References & Information
        $addSectionHeading('4. Key Metrics, References & Information');
        $addBulletList($summary['key_metrics_references'] ?? [], 'No specific metrics, dates, or references were mentioned.');

        // Section 5: Decisions & Conclusions
        $addSectionHeading('5. Decisions & Conclusions');
        $addBulletList(
            $summary['decisions_conclusions'] ?? [],
            (string)($summary['empty_decisions_text'] ?? 'No formal decisions were mentioned.')
        );

        // Section 6: Action Items & Next Steps
        $addSectionHeading('6. Action Items & Next Steps');
        $addBulletList(
            $summary['action_items'] ?? [],
            (string)($summary['empty_actions_text'] ?? 'No action items were identified.')
        );

        // Section 7: High-Level Synthesis
        $addSectionHeading('7. High-Level Synthesis');
        $addParagraph(
            (string)($summary['high_level_synthesis'] ?? ''),
            10.5,
            false,
            [0.118, 0.161, 0.231],
            15.0,
            10.0
        );

        $endPage();

        return self::assemblePdfDocument($pages, $pageWidth, $pageHeight);
    }

    /**
     * @param array<int, string> $pages
     */
    private static function assemblePdfDocument(array $pages, float $width, float $height): string
    {
        $objects = [];
        $catalogObjNum = 1;
        $pagesObjNum   = 2;
        $fontRegNum    = 3;
        $fontBoldNum   = 4;

        $objects[$fontRegNum] = "<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>";
        $objects[$fontBoldNum] = "<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>";

        $pageObjNums = [];
        $nextObj = 5;

        foreach ($pages as $idx => $streamContent) {
            $contentObjNum = $nextObj++;
            $pageObjNum    = $nextObj++;
            $pageObjNums[] = $pageObjNum;

            $objects[$contentObjNum] = sprintf(
                "<< /Length %d >>\nstream\n%sendstream",
                strlen($streamContent),
                $streamContent
            );

            $objects[$pageObjNum] = sprintf(
                "<< /Type /Page /Parent %d 0 R /MediaBox [0 0 %.2f %.2f] /Resources << /Font << /F1 %d 0 R /F2 %d 0 R >> >> /Contents %d 0 R >>",
                $pagesObjNum,
                $width,
                $height,
                $fontRegNum,
                $fontBoldNum,
                $contentObjNum
            );
            unset($idx);
        }

        $kidsStr = implode(' ', array_map(static fn(int $n): string => $n . ' 0 R', $pageObjNums));
        $objects[$pagesObjNum] = sprintf(
            "<< /Type /Pages /Kids [%s] /Count %d >>",
            $kidsStr,
            count($pageObjNums)
        );
        $objects[$catalogObjNum] = sprintf(
            "<< /Type /Catalog /Pages %d 0 R >>",
            $pagesObjNum
        );

        ksort($objects);

        $pdf = "%PDF-1.4\n";
        $offsets = [0 => 0];

        foreach ($objects as $objNum => $body) {
            $offsets[$objNum] = strlen($pdf);
            $pdf .= $objNum . " 0 obj\n" . $body . "\nendobj\n";
        }

        $xrefOffset = strlen($pdf);
        $count = count($objects) + 1;
        $pdf .= "xref\n0 " . $count . "\n";
        $pdf .= "0000000000 65535 f \n";
        for ($i = 1; $i < $count; $i++) {
            $pdf .= sprintf("%010d 00000 n \n", $offsets[$i] ?? 0);
        }

        $pdf .= sprintf(
            "trailer\n<< /Size %d /Root %d 0 R >>\nstartxref\n%d\n%%%%EOF\n",
            $count,
            $catalogObjNum,
            $xrefOffset
        );

        return $pdf;
    }

    /**
     * @return array<int, string>
     */
    private static function wrapText(string $text, float $fontSize, float $maxWidth, bool $bold): array
    {
        $text = str_replace(["\r\n", "\r"], "\n", trim($text));
        if ($text === '') {
            return [];
        }

        $avgCharWidth = $fontSize * ($bold ? 0.54 : 0.50);
        $maxChars = max(12, (int)floor($maxWidth / max(4.0, $avgCharWidth)));

        $result = [];
        $paragraphs = explode("\n", $text);
        foreach ($paragraphs as $para) {
            $para = trim($para);
            if ($para === '') {
                $result[] = '';
                continue;
            }
            $wrapped = wordwrap($para, $maxChars, "\n", true);
            foreach (explode("\n", $wrapped) as $line) {
                $result[] = $line;
            }
        }
        return $result;
    }

    private static function toWinAnsi(string $utf8): string
    {
        $converted = @iconv('UTF-8', 'Windows-1252//TRANSLIT//IGNORE', $utf8);
        return is_string($converted) ? $converted : preg_replace('/[^\x20-\x7E]/', '?', $utf8) ?? '';
    }

    private static function escapePdfString(string $str): string
    {
        return str_replace(
            ['\\', '(', ')'],
            ['\\\\', '\\(', '\\)'],
            $str
        );
    }
}