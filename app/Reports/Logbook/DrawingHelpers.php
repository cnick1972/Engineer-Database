<?php

/**
 * Maintenance Log Application
 *
 * Copyright (c) 2024 The Maintenance Log Developers.
 * All rights reserved.
 *
 * This source code is proprietary and confidential. Unauthorized copying,
 * modification, distribution, or disclosure is strictly prohibited without
 * prior written consent.
 */

namespace App\Reports\Logbook;

use TCPDF;

class DrawingHelpers
{
    public static function drawMergedGrid(
        TCPDF $pdf,
        float $x,
        float $y,
        float $rowH,
        array $colW,
        float $thin,
        float $thick,
        array $fullColX = [] // NEW: 0-based column indexes to draw a big X across
    ): void {
        // grid geometry
        $rows = 6;                        // as per your current layout
        $colCount = count($colW);         // expected 6

        $pdf->SetLineWidth($thin);

        // Column edges X[0]..X[n]
        $X = [$x];
        for ($i = 0; $i < $colCount; $i++) {
            $X[] = $X[$i] + $colW[$i];
        }

        // Row edges Y[0]..Y[6]
        $Y = [$y];
        for ($r = 0; $r < $rows; $r++) {
            $Y[] = $Y[$r] + $rowH;
        }

        // --- Vertical lines (preserve your special cases) ---
        // i === 3 : from Y0..Y5
        // i === 4 : from Y1..Y5
        // others  : full height
        for ($i = 1; $i <= $colCount - 1; $i++) {
            if ($i === 4) {
                $pdf->Line($X[$i], $Y[1], $X[$i], $Y[5]);
            } elseif ($i === 3) {
                $pdf->Line($X[$i], $Y[0], $X[$i], $Y[5]);
            } else {
                $pdf->Line($X[$i], $Y[0], $X[$i], $Y[6]);
            }
        }

        // --- Horizontal lines (preserve merges rows 2..4; merge column 6 entirely) ---
        for ($r = 1; $r <= 5; $r++) {
            $yy = $Y[$r];
            $stopBeforeLastCol = $X[$colCount - 1]; // X[5] when 6 cols

            if ($r >= 2 && $r <= 4) {
                // Your existing split around X[2]..X[3], and stop before last column band
                $pdf->Line($X[0], $yy, $X[2], $yy);
                $pdf->Line($X[3], $yy, $stopBeforeLastCol, $yy);
                // Do NOT draw across last column (so col 6 is vertically merged)
            } else {
                // Single segment up to the start of the last column; skip X[last-1]..X[last]
                $pdf->Line($X[0], $yy, $stopBeforeLastCol, $yy);
            }
        }

        // --- Outer border ---
        $pdf->SetLineWidth($thick);
        $pdf->Rect($X[0], $Y[0], $X[$colCount] - $X[0], $Y[6] - $Y[0]);

        // --- Full-column X overlays (if any) ---
        if (!empty($fullColX)) {
            $pdf->SetLineWidth($thin); // tweak if you want heavier X
            $colTop = $Y[0];
            $colH   = $Y[6] - $Y[0];
            foreach ($fullColX as $ci) {
                if (!isset($colW[$ci])) {
                    continue;
                }
                $colLeft = $X[$ci];
                $colWdt  = $colW[$ci];
                self::drawColumnX($pdf, $colLeft, $colTop, $colWdt, $colH, 1.5, null);
            }
        }
    }



    public static function drawDiagonalThroughCells(TCPDF $pdf, float $x, float $y, float $rowH, array $colW, int $numRows = 6): void
    {
        // Same look as before
        $pdf->SetLineWidth(0.3);
        $pdf->SetDrawColor(0, 0, 0);

        // Total height covered by the stacked rows
        $h = $rowH * $numRows;

        // Column 1: from its top-left to bottom-right
        $pdf->Line(
            $x,           // left edge of col 1
            $y,           // top
            $x + $colW[0],
            $y + $h       // bottom
        );

        // Column 2: from its top-left to bottom-right
        $pdf->Line(
            $x + $colW[0],                 // left edge of col 2
            $y,                            // top
            $x + $colW[0] + $colW[1],
            $y + $h                        // bottom
        );
    }

    public static function drawCol1Labels(TCPDF $pdf, float $x, float $y, float $rowH, float $w, string $font, float $fs): void
    {
        $labels = ['Date','A/C Reg','W/O','T/C Seq.#','Manual','Ref.'];
        $pdf->SetFont($font, '', $fs);
        foreach ($labels as $i => $txt) {
            $pdf->MultiCell($w, $rowH, $txt, 0, 'C', false, 0, $x, $y + $i * $rowH, true, 0, false, true, $rowH, 'M');
        }
    }

    public static function drawCol4Labels(TCPDF $pdf, float $x, float $y, float $rowH, float $w, string $font, float $fs): void
    {
        $labels = ['CDCCL','EWIZ','EZAP','AWL'];
        $pdf->SetFont($font, '', $fs);
        foreach ($labels as $i => $txt) {
            $pdf->MultiCell($w, $rowH, $txt, 0, 'C', false, 0, $x, $y + ($i + 1) * $rowH, true, 0, false, true, $rowH, 'M');
        }
    }

    public static function drawCol3Headings(
        TCPDF $pdf,
        float $x,
        float $y,
        float $rowH,
        float $col1,
        float $col2,
        float $col3,
        string $font,
        float $fs,
        string $taskDesc = '',
        bool $isContinuation = false
    ): void {
        $pad = Layout::PAD_MM;
        $pdf->SetFont($font, '', $fs);
        $x3 = $x + $col1 + $col2;

        $label = $isContinuation ? 'Task detail (cont):' : 'Task detail:';
        $pdf->MultiCell($col3 - 2 * $pad, $rowH, 'Task classification:', 0, 'L', false, 0, $x3 + $pad, $y, true);
        $pdf->MultiCell($col3 - 2 * $pad, 4 * $rowH, $label . "\n" . trim($taskDesc), 0, 'L', false, 0, $x3 + $pad, $y + $rowH, true);
    }

    public static function drawToolsUsedRow(
        TCPDF $pdf,
        float $xStart,
        float $yStart,
        float $rowH,
        float $col1_w,
        float $col2_w,
        float $col3_w,
        string $font,
        float $fontSize,
        ?string $tools
    ): void {
        $padX = 1.0;
        $xCol3 = $xStart + $col1_w + $col2_w;
        $yRow6 = $yStart + (5 * $rowH); // row 6 (0-indexed)
        $pdf->SetFont($font, '', $fontSize);
        $pdf->SetTextColor(0, 0, 0);

        $label = 'Tooling/Equipment Used: ';
        $content = trim((string)$tools) !== '' ? trim((string)$tools) : '—';
        $text = $label . $content;

        $pdf->MultiCell(
            $col3_w - 2 * $padX,
            $rowH,
            $text,
            0,
            'L',
            false,
            0,
            $xCol3 + $padX,
            $yRow6,
            true,
            0,
            false,
            true,
            $rowH,
            'M'
        );
    }

    public static function drawColumnX(
        TCPDF $pdf,
        float $x,
        float $y,         // top-left of the column
        float $w,
        float $h,         // column width/height
        float $pad = 1.5,           // inset so lines don't overlap borders
        ?float $lineWidth = null    // null = keep current; else override temporarily
    ): void {
        $orig = $pdf->getLineWidth();
        if ($lineWidth !== null) {
            $pdf->SetLineWidth($lineWidth);
        }
        // Two diagonals across the column rect
        $pdf->Line($x + $pad, $y + $pad, $x + $w - $pad, $y + $h - $pad);
        $pdf->Line($x + $w - $pad, $y + $pad, $x + $pad, $y + $h - $pad);
        if ($lineWidth !== null) {
            $pdf->SetLineWidth($orig);
        }
    }

    public static function drawFooterOwner(TCPDF $pdf, string $font, float $fontSize = 12.0, string $ownerName = LOGBOOK_OWNER): void
    {
        $pdf->SetFont($font, '', $fontSize);
        $pdf->SetTextColor(0, 0, 0);

        // Positions
        $margins      = $pdf->getMargins();
        $leftMargin   = (float)$margins['left'];
        $bottomMargin = (float)$margins['bottom'];
        $pageHeight   = (float)$pdf->getPageHeight();

        // Y-position just above bottom margin (keep your existing offset)
        $yPos = $pageHeight - $bottomMargin - 10; // adjust if you want it closer/farther

        // X positions relative to LEFT MARGIN
        $xSignature = $leftMargin + 130.0;
        $xPage      = $leftMargin + 245.0;
        $xOf        = $leftMargin + 265.0;

        // 1) Owner name (limit width so it doesn't overlap "Signature:")
        $pdf->SetXY($leftMargin, $yPos);
        $ownerCellWidth = max(0.0, $xSignature - $leftMargin - 2.0); // small gap before "Signature:"
        $pdf->Cell(
            $ownerCellWidth,
            6,
            "Logbook Owner's Name: " . $ownerName,
            0,
            0,
            'L',
            false,
            '',
            0,
            false,
            'T',
            'M'
        );

        // 2) "Signature:" at 159mm from left margin
        $pdf->SetXY($xSignature, $yPos);
        $pdf->Cell($pdf->GetStringWidth('Signature: ') + 0.5, 6, 'Signature: ', 0, 0, 'L');

        // 3) "Page" at 245mm from left margin
        $pdf->SetXY($xPage, $yPos);
        $pdf->Cell($pdf->GetStringWidth('Page') + 0.5, 6, 'Page', 0, 0, 'L');

        // 4) "of" at 265mm from left margin
        $pdf->SetXY($xOf, $yPos);
        $pdf->Cell($pdf->GetStringWidth('of') + 0.5, 6, 'of', 0, 0, 'L');

        // (Optional) If you later want numbers:
        // $pdf->SetXY($xPage + $pdf->GetStringWidth('Page ') + 2, $yPos);
        // $pdf->Cell(10, 6, $pdf->getAliasNumPage(), 0, 0, 'L'); // current page
        // $pdf->SetXY($xOf + $pdf->GetStringWidth('of ') + 2, $yPos);
        // $pdf->Cell(10, 6, $pdf->getAliasNbPages(), 0, 0, 'L');  // total pages
    }


    public static function drawTextInCol2Row(
        TCPDF $pdf,
        float $x,
        float $y,
        float $rowH,
        float $col1,
        float $col2,
        int $row,
        string $font,
        float $fs,
        string $text
    ): void {
        $pad = Layout::PAD_MM;
        $pdf->SetFont($font, '', $fs);
        $pdf->MultiCell($col2 - 2 * $pad, $rowH, $text, 0, 'L', false, 0, $x + $col1 + $pad, $y + $row * $rowH, true);
    }

    public static function drawStampBlock(TCPDF $pdf, float $x, float $y, float $rowH, float $w, string $font, float $fs, string $eng = 'ABC'): void
    {
        $txt1 = 'Supervisors\'s Stamp &';
        $txt2 = 'Signature:';
        $pdf->SetFont($font, '', $fs);

        $pdf->MultiCell($w, $rowH, $txt1, 0, 'L', false, 0, $x, $y + $i * $rowH, true, 0, false, true, $rowH, 'M');
        $pdf->MultiCell($w, $rowH, $txt2, 0, 'L', false, 0, $x, $y + ($i + 1) * $rowH, true, 0, false, true, $rowH, 'M');
        $pdf->SetFont($font, '', 6);
        $pdf->MultiCell($w, $rowH, $eng, 0, 'L', false, 0, $x, $y + ($i + 5) * $rowH, true, 0, false, true, $rowH, 'M');

    }
}
