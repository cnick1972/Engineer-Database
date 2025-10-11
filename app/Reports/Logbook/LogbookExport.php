<?php

namespace App\Reports\Logbook;

use TCPDF;
use PDO;

class LogbookExport
{
    public function __construct(private PDO $pdo)
    {
    }

    public function generate(): void
    {
        $pdf = new TCPDF('L', 'mm', 'A4', true, 'UTF-8', false);
        $pdf->SetCompression(true);
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->SetMargins(Layout::MARGIN_LEFT, Layout::MARGIN_TOP, Layout::MARGIN_RIGHT);
        $pdf->SetAutoPageBreak(true, Layout::MARGIN_BOTTOM);

        $rows = $this->fetchTasks();
        if (!$rows) {
            $pdf->AddPage();
            $pdf->SetFont('helvetica', '', 16);
            $pdf->SetXY(7, 7);
            $pdf->Cell(0, 8, 'Experience Logbook', 0, 1, 'L');
            $pdf->Ln(5);
            $pdf->SetFont('helvetica', '', 12);
            $pdf->Cell(0, 8, 'No tasks found.', 0, 1, 'L');
            $pdf->Output('logbook_export_v2.pdf', 'I');
            return;
        }

        $groups = $this->groupTasks($rows);

        $pt_to_mm = 25.4 / 72.0;
        $LW_THIN  = 0.5 * $pt_to_mm;
        $LW_THICK = 1.5 * $pt_to_mm;

        foreach ($groups as $group) {
            $m = $group['meta'];
            $tasks = $group['tasks'];

            $this->startGroupPage($pdf, $m);
            $y = $pdf->GetY();
            $tablesOnPage = 0;

            foreach ($tasks as $t) {
                $chunks = $this->splitTask($pdf, $t['task_description'], Layout::COL3_W - 2, 4 * Layout::ROW_HEIGHT - 1);

                foreach ($chunks as $i => $chunk) {
                    if ($tablesOnPage >= Layout::TABLES_PER_PAGE) {
                        $this->startGroupPage($pdf, $m);
                        $y = $pdf->GetY();
                        $tablesOnPage = 0;
                    }

                    DrawingHelpers::drawMergedGrid($pdf, 7, $y, Layout::ROW_HEIGHT, [
                        Layout::COL1_W, Layout::COL2_W, Layout::COL3_W, Layout::COL4_W, Layout::COL5_W,
                        max(0.0, ($pdf->getPageWidth() - 14) - (Layout::COL1_W + Layout::COL2_W + Layout::COL3_W + Layout::COL4_W + Layout::COL5_W))
                    ], $LW_THIN, $LW_THICK);

                    if ($i === 0) {
                        DrawingHelpers::drawCol1Labels($pdf, 7, $y, Layout::ROW_HEIGHT, Layout::COL1_W, 'helvetica', Layout::FONT_SIZE_NORMAL);
                        $date = date('d M y', strtotime($t['date_performed']));
                        DrawingHelpers::drawTextInCol2Row($pdf, 7, $y, Layout::ROW_HEIGHT, Layout::COL1_W, Layout::COL2_W, 0, 'helvetica', 9, $date);
                        DrawingHelpers::drawTextInCol2Row($pdf, 7, $y, Layout::ROW_HEIGHT, Layout::COL1_W, Layout::COL2_W, 1, 'helvetica', 9, $t['tail_number']);

                        $WO = trim((string)$t['WO_number']);
                        $check = trim((string)$t['check_pack_reference']);
                        $seq = trim((string)$t['task_card_seq']);
                        $l3 = ($WO !== '' && $WO !== '00000000') ? $WO : $check;
                        $l4 = ($WO !== '' && $WO !== '00000000') ? '' : $seq;
                        DrawingHelpers::drawTextInCol2Row($pdf, 7, $y, Layout::ROW_HEIGHT, Layout::COL1_W, Layout::COL2_W, 2, 'helvetica', 9, $l3);
                        if ($l4) {
                            DrawingHelpers::drawTextInCol2Row($pdf, 7, $y, Layout::ROW_HEIGHT, Layout::COL1_W, Layout::COL2_W, 3, 'helvetica', 9, $l4);
                        }
                    } else {
                        DrawingHelpers::drawDiagonalThroughCells($pdf, 7, $y, Layout::ROW_HEIGHT, [Layout::COL1_W, Layout::COL2_W], 4);
                    }

                    $cont = ($i > 0);
                    DrawingHelpers::drawCol3Headings($pdf, 7, $y, Layout::ROW_HEIGHT, Layout::COL1_W, Layout::COL2_W, Layout::COL3_W, 'helvetica', 9, $chunk, $cont);
                    DrawingHelpers::drawToolsUsedRow(
                        $pdf,
                        7,
                        $y,
                        Layout::ROW_HEIGHT,
                        Layout::COL1_W,
                        Layout::COL2_W,
                        Layout::COL3_W,
                        'helvetica',
                        9,
                        $t['calibrated_tools'] ?? ''
                    );

                    $y += Layout::ROWS_COUNT * Layout::ROW_HEIGHT + 6.0;
                    $tablesOnPage++;
                }
            }
        }

        $pdf->Output('logbook_export_v2.pdf', 'I');
    }

    private function fetchTasks(): array
    {
        $sql = "SELECT mt.*, a.aircraft_type, a.engine_type, a.tail_number,
                       ata.ata_number, ata.description AS ata_desc
                FROM maintenance_tasks mt
                JOIN aircraft a ON mt.aircraft_id = a.aircraft_id
                JOIN ata ON mt.ata_id = ata.ata_id
                ORDER BY CAST(ata.ata_number AS UNSIGNED), ata.ata_number, a.aircraft_type, a.engine_type, mt.date_performed, mt.task_id";
        return $this->pdo->query($sql)->fetchAll(\PDO::FETCH_ASSOC);
    }

    private function groupTasks(array $rows): array
    {
        $groups = [];
        foreach ($rows as $r) {
            $key = $r['aircraft_type'] . '|' . $r['engine_type'] . '|' . $r['ata_number'];
            $groups[$key]['meta'] = [
                'ac' => $r['aircraft_type'],'eng' => $r['engine_type'],'ata' => $r['ata_number'],'desc' => $r['ata_desc']
            ];
            $groups[$key]['tasks'][] = $r;
        }
        return $groups;
    }

    private function startGroupPage(TCPDF $pdf, array $meta): void
    {
        $pdf->AddPage();
        DrawingHelpers::drawFooterOwner($pdf, 'helvetica', 12);
        $pdf->SetFont('helvetica', '', 16);
        $pdf->SetXY(7, 7);
        $pdf->Cell(0, 8, 'Aircraft Maintenance Experience Logbook', 0, 1, 'L');
        $pdf->Ln(11 * 25.4 / 72.0);
        $pdf->SetFont('helvetica', '', 11);
        $pdf->Cell(66, 5, 'Aircraft type: ' . $meta['ac'], 0, 0, 'L');
        $pdf->Cell(70, 5, 'Engine: ' . $meta['eng'], 0, 0, 'L');
        $pdf->Cell(0, 5, 'ATA Chapter: ' . $meta['ata'] . ' - ' . $meta['desc'], 0, 1, 'L');
        $pdf->Ln(2);
    }

    private function splitTask(TCPDF $pdf, string $text, float $boxW, float $boxH): array
    {
        $pdf->SetFont('helvetica', '', 9);
        $words = preg_split('/\s+/u', trim($text));
        $chunks = [];
        $curr = '';
        foreach ($words as $w) {
            $test = $curr === '' ? $w : $curr . ' ' . $w;
            $h = $pdf->getStringHeight($boxW, $test);
            if ($h > $boxH && $curr !== '') {
                $chunks[] = trim($curr);
                $curr = $w;
            } else {
                $curr = $test;
            }
        }
        if ($curr !== '') {
            $chunks[] = trim($curr);
        }
        return $chunks ?: [''];
    }
}
