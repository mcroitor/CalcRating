<?php

namespace App\Services;

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

class PlayersExport
{
    /**
     * Export players collection to XLSX download stream.
     */
    public function toXlsx(Collection $players, string $filename = 'players.xlsx'): StreamedResponse
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Players');

        // Headers in latin per questions.md #10
        $headers = ['id', 'last_name', 'first_name', 'birth_date', 'age', 'title', 'current_rating'];
        $sheet->fromArray([$headers], null, 'A1');

        $headerStyle = [
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '4F46E5'],
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
        ];
        $sheet->getStyle('A1:G1')->applyFromArray($headerStyle);
        $sheet->getRowDimension(1)->setRowHeight(24);

        $rowNum = 2;
        foreach ($players as $player) {
            $row = [
                $player->id,
                $this->sanitizeFormula($player->last_name),
                $this->sanitizeFormula($player->first_name),
                $player->birth_date?->format('Y-m-d') ?? '',
                $player->age ?? '',
                $player->title?->value ?? 'None',
                $player->current_rating,
            ];

            $sheet->fromArray([$row], null, "A{$rowNum}");
            $rowNum++;
        }

        // Apply borders and auto column width
        $lastRow = max(1, $rowNum - 1);
        $sheet->getStyle("A1:G{$lastRow}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);

        foreach (range('A', 'G') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        return response()->streamDownload(function () use ($spreadsheet) {
            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            'Cache-Control' => 'max-age=0',
        ]);
    }

    /**
     * Export players collection to PDF response.
     */
    public function toPdf(Collection $players, string $filename = 'players.pdf'): Response
    {
        $pdf = Pdf::loadView('players.export-pdf', [
            'players' => $players,
            'generated_at' => now()->format('d.m.Y H:i'),
        ])->setPaper('a4', 'landscape');

        return $pdf->download($filename);
    }

    /**
     * Render standalone HTML export view.
     */
    public function toHtml(Collection $players): View
    {
        return view('players.export-html', [
            'players' => $players,
            'generated_at' => now()->format('d.m.Y H:i'),
        ]);
    }

    /**
     * Protect against Excel Formula Injection (CSV/XLSX Injection).
     */
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
