<?php

namespace App\Services;

use App\Models\Tournament;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TournamentExport
{
    public function toXlsx(Tournament $tournament, Collection $standings, Collection $pairingsByRound, string $filename): StreamedResponse
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Tournament');

        $meta = [
            'name' => $tournament->name,
            'club' => $tournament->club?->name ?? '',
            'start_date' => $tournament->start_date?->format('Y-m-d'),
            'end_date' => $tournament->end_date?->format('Y-m-d'),
            'location' => $tournament->location,
            'arbiter' => trim(($tournament->arbiter?->surname ?? '').' '.($tournament->arbiter?->name ?? '')),
            'pairing_type' => $tournament->pairing_type?->value,
            'rating_mode' => $tournament->rating_mode?->value,
        ];

        $metaRows = [];
        foreach ($meta as $label => $value) {
            $metaRows[] = [$label, $this->sanitizeFormula($value)];
        }
        $sheet->fromArray($metaRows, null, 'A1');
        $sheet->getStyle('A1:A'.count($metaRows))->getFont()->setBold(true);

        $headerRow = count($metaRows) + 2;

        $headers = ['place', 'player', 'title', 'rating_before', 'points', 'rating_after', 'delta'];
        $sheet->fromArray([$headers], null, "A{$headerRow}");

        $headerStyle = [
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '4F46E5']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
        ];
        $sheet->getStyle("A{$headerRow}:G{$headerRow}")->applyFromArray($headerStyle);
        $sheet->getRowDimension($headerRow)->setRowHeight(24);

        $rowNum = $headerRow + 1;
        foreach ($standings as $index => $row) {
            $after = $row->ratingAfter;
            $delta = $after !== null ? $after - $row->ratingBefore : null;
            $sheet->fromArray([[
                $index + 1,
                $this->sanitizeFormula($row->playerName),
                $row->playerTitle?->value ?? '',
                $row->ratingBefore,
                number_format($row->points, 1, '.', ''),
                $after ?? '',
                $delta ?? '',
            ]], null, "A{$rowNum}");
            $rowNum++;
        }

        $lastRow = max($headerRow, $rowNum - 1);
        $sheet->getStyle("A{$headerRow}:G{$lastRow}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
        foreach (range('A', 'G') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        foreach ($pairingsByRound->sortKeys() as $roundNumber => $roundPairings) {
            $roundSheet = $spreadsheet->createSheet();
            $roundSheet->setTitle('Round '.$roundNumber);

            $roundHeaders = ['board_number', 'white', 'result', 'black', 'advanced'];
            $roundSheet->fromArray([$roundHeaders], null, 'A1');
            $roundSheet->getStyle('A1:E1')->applyFromArray($headerStyle);
            $roundSheet->getRowDimension(1)->setRowHeight(24);

            $roundRow = 2;
            foreach ($roundPairings->sortBy('board_number') as $pairing) {
                $roundSheet->fromArray([[
                    $pairing->board_number,
                    $this->sanitizeFormula($pairing->whitePlayer?->full_name),
                    $pairing->result?->value,
                    $this->sanitizeFormula($pairing->blackPlayer?->full_name),
                    $this->sanitizeFormula($pairing->advancedPlayer?->full_name),
                ]], null, "A{$roundRow}");
                $roundRow++;
            }

            $roundLastRow = max(1, $roundRow - 1);
            $roundSheet->getStyle("A1:E{$roundLastRow}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
            foreach (range('A', 'E') as $col) {
                $roundSheet->getColumnDimension($col)->setAutoSize(true);
            }
        }

        $spreadsheet->setActiveSheetIndex(0);

        return response()->streamDownload(function () use ($spreadsheet) {
            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            'Cache-Control' => 'max-age=0',
        ]);
    }

    public function toPdf(Tournament $tournament, Collection $standings, Collection $pairingsByRound, string $filename): Response
    {
        $pdf = Pdf::loadView('tournaments.export-pdf', [
            'tournament' => $tournament,
            'standings' => $standings,
            'pairingsByRound' => $pairingsByRound,
            'generated_at' => now()->format('d.m.Y H:i'),
        ])->setPaper('a4', 'landscape');

        return $pdf->download($filename);
    }

    public function toHtml(Tournament $tournament, Collection $standings, Collection $pairingsByRound): View
    {
        return view('tournaments.export-html', [
            'tournament' => $tournament,
            'standings' => $standings,
            'pairingsByRound' => $pairingsByRound,
            'generated_at' => now()->format('d.m.Y H:i'),
        ]);
    }

    protected function sanitizeFormula(?string $value): string
    {
        if ($value === null) {
            return '';
        }
        $trimmed = trim($value);
        if ($trimmed !== '' && in_array($trimmed[0], ['=', '+', '-', '@'], true)) {
            return "'".$trimmed;
        }

        return $value;
    }
}
