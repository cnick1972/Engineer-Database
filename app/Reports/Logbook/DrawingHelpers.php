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
    public static function drawMergedGrid(TCPDF $pdf, float $x, float $y, float $rowH, array $colW, float $thin, float $thick): void
    {
        $rows = 6;
        $pdf->SetLineWidth($thin);
        $X = [$x];
        for ($i = 0; $i < 6; $i++) {
            $X[] = $X[$i] + $colW[$i];
        }
        $Y = [$y];
        for ($r = 0; $r < $rows; $r++) {
            $Y[] = $Y[$r] + $rowH;
        }

        for ($i = 1; $i <= 5; $i++) {
            if ($i === 4) {
                $pdf->Line($X[$i], $Y[1], $X[$i], $Y[5]);
            } elseif ($i === 3) {
                $pdf->Line($X[$i], $Y[0], $X[$i], $Y[5]);
            } else {
                $pdf->Line($X[$i], $Y[0], $X[$i], $Y[6]);
            }
        }
        for ($r = 1; $r <= 5; $r++) {
            $yy = $Y[$r];
            if ($r >= 2 && $r <= 4) {
                $pdf->Line($X[0], $yy, $X[2], $yy);
                $pdf->Line($X[3], $yy, $X[6], $yy);
            } else {
                $pdf->Line($X[0], $yy, $X[6], $yy);
            }
        }
        $pdf->SetLineWidth($thick);
        $pdf->Rect($X[0], $Y[0], $X[6] - $X[0], $Y[6] - $Y[0]);
    }

    public static function drawDiagonalThroughCells(TCPDF $pdf, float $x, float $y, float $rowH, array $colW, int $numRows = 6): void
    {
        $pdf->SetLineWidth(0.3);
        $pdf->SetDrawColor(120, 120, 120);
        for ($r = 0; $r < $numRows; $r++) {
            $yt = $y + $r * $rowH;
            $yb = $yt + $rowH;
            $pdf->Line($x, $yt, $x + $colW[0], $yb);
            $pdf->Line($x + $colW[0], $yt, $x + $colW[0] + $colW[1], $yb);
        }
        $pdf->SetDrawColor(0, 0, 0);
    }

    public static function drawCol1Labels(TCPDF $pdf, float $x, float $y, float $rowH, float $w, string $font, float $fs): void
    {
        $labels = ['Date','A/C Reg','W/O','T/C Seq.#','Manual','Ref.'];
        $pdf->SetFont($font, '', $fs);
        foreach ($labels as $i => $txt) {
            $pdf->MultiCell($w, $rowH, $txt, 0, 'C', false, 0, $x, $y + $i * $rowH, true, 0, false, true, $rowH, 'M');
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

    public static function drawFooterOwner(TCPDF $pdf, string $font, float $fontSize = 12.0, string $ownerName = LOGBOOK_OWNER): void
    {

        $pdf->SetFont($font, '', $fontSize);
        $pdf->SetTextColor(0, 0, 0);

        // Y-position just above bottom margin
        $pageHeight = $pdf->getPageHeight();
        $bottomMargin = $pdf->getMargins()['bottom'];
        $yPos = $pageHeight - $bottomMargin - 10; // 4mm above bottom

        $pdf->SetXY($pdf->getMargins()['left'], $yPos);
        $pdf->Cell(
            0,
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
}
