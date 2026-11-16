<?php

namespace App\Actions;

use App\Enums\ChessTitle;
use App\Models\Club;
use App\Models\Player;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

class ImportPlayersAction
{
    /**
     * Process player import from a spreadsheet file (CSV, XLSX, ODS).
     *
     * @return array{
     *     total_rows: int,
     *     imported: int,
     *     errors_count: int,
     *     errors: list<array{row: int, message: string}>,
     *     warnings: list<array{row: int, message: string}>
     * }
     */
    public function execute(UploadedFile|string $file, ?Club $club = null): array
    {
        $filePath = $file instanceof UploadedFile ? $file->getRealPath() : $file;

        $reader = IOFactory::createReaderForFile($filePath);
        if (method_exists($reader, 'setReadDataOnly')) {
            $reader->setReadDataOnly(true);
        }

        $spreadsheet = $reader->load($filePath);
        $worksheet = $spreadsheet->getActiveSheet();
        $rows = $worksheet->toArray(null, true, false, false);

        if (empty($rows)) {
            return [
                'total_rows' => 0,
                'imported' => 0,
                'errors_count' => 0,
                'errors' => [['row' => 1, 'message' => __('players.import.empty_file')]],
                'warnings' => [],
            ];
        }

        // Parse and validate header
        $header = array_map(fn ($cell) => strtolower(trim((string) $cell)), array_shift($rows));
        $columnMap = [];

        foreach ($header as $index => $colName) {
            $columnMap[$colName] = $index;
        }

        $requiredColumns = ['first_name', 'last_name', 'birth_date'];
        $missingColumns = array_diff($requiredColumns, array_keys($columnMap));

        if (! empty($missingColumns)) {
            return [
                'total_rows' => 0,
                'imported' => 0,
                'errors_count' => 1,
                'errors' => [[
                    'row' => 1,
                    'message' => __('players.import.missing_columns', ['columns' => implode(', ', $missingColumns)]),
                ]],
                'warnings' => [],
            ];
        }

        $totalRows = 0;
        $importedCount = 0;
        $errors = [];
        $warnings = [];
        $chunk = [];
        $chunkSize = 100;
        $seenInBatch = [];

        foreach ($rows as $rowIndexZero => $row) {
            $rowNumber = $rowIndexZero + 2; // 1-based, skipping header

            // Skip completely empty rows
            $hasContent = false;
            foreach ($row as $val) {
                if ($val !== null && trim((string) $val) !== '') {
                    $hasContent = true;
                    break;
                }
            }

            if (! $hasContent) {
                continue;
            }

            $totalRows++;

            // Extract row values
            $firstName = trim((string) ($row[$columnMap['first_name']] ?? ''));
            $lastName = trim((string) ($row[$columnMap['last_name']] ?? ''));
            $rawBirthDate = $row[$columnMap['birth_date']] ?? null;
            $rawRating = isset($columnMap['current_rating']) ? $row[$columnMap['current_rating']] : null;
            $rawTitle = isset($columnMap['title']) ? trim((string) $row[$columnMap['title']]) : '';
            $notes = isset($columnMap['notes']) ? trim((string) $row[$columnMap['notes']]) : null;

            // Validate mandatory fields
            if ($firstName === '' || $lastName === '') {
                $errors[] = [
                    'row' => $rowNumber,
                    'message' => __('players.import.name_required'),
                ];

                continue;
            }

            // Parse birth date
            $parsedDate = $this->parseBirthDate($rawBirthDate);
            if (! $parsedDate) {
                $errors[] = [
                    'row' => $rowNumber,
                    'message' => __('players.import.invalid_birth_date'),
                ];

                continue;
            }

            if ($parsedDate->isFuture() || $parsedDate->isToday()) {
                $errors[] = [
                    'row' => $rowNumber,
                    'message' => __('players.import.birth_date_future'),
                ];

                continue;
            }

            $birthDateString = $parsedDate->format('Y-m-d');

            // Deduplication key
            $dedupKey = mb_strtolower("{$lastName}|{$firstName}|{$birthDateString}");

            if (isset($seenInBatch[$dedupKey])) {
                $warnings[] = [
                    'row' => $rowNumber,
                    'message' => __('players.import.duplicate_in_file', [
                        'name' => "{$lastName} {$firstName}",
                        'birth_date' => $birthDateString,
                    ]),
                ];

                continue;
            }

            // Check database for existing player
            $existingPlayer = Player::where('last_name', $lastName)
                ->where('first_name', $firstName)
                ->whereDate('birth_date', $birthDateString)
                ->first();

            if ($existingPlayer !== null) {
                if ($club !== null) {
                    $club->members()->syncWithoutDetaching([
                        $existingPlayer->id => ['joined_at' => now()->toDateString()],
                    ]);
                }

                $warnings[] = [
                    'row' => $rowNumber,
                    'message' => __('players.import.already_exists', [
                        'name' => "{$lastName} {$firstName}",
                        'birth_date' => $birthDateString,
                    ]),
                ];

                continue;
            }

            $seenInBatch[$dedupKey] = true;

            // Rating parsing (default 1000)
            $rating = 1000;
            if ($rawRating !== null && trim((string) $rawRating) !== '') {
                if (! is_numeric($rawRating) || (int) $rawRating < 0) {
                    $errors[] = [
                        'row' => $rowNumber,
                        'message' => __('players.import.invalid_rating'),
                    ];

                    continue;
                }
                $rating = (int) $rawRating;
            }

            // Title parsing (default None)
            $title = ChessTitle::None;
            if ($rawTitle !== '') {
                $matched = ChessTitle::tryFrom($rawTitle);
                if ($matched) {
                    $title = $matched;
                } else {
                    // Try case-insensitive matching
                    foreach (ChessTitle::cases() as $case) {
                        if (strcasecmp($case->value, $rawTitle) === 0) {
                            $title = $case;
                            break;
                        }
                    }
                }
            }

            $chunk[] = [
                'first_name' => $firstName,
                'last_name' => $lastName,
                'birth_date' => $birthDateString,
                'title' => $title,
                'current_rating' => $rating,
                'notes' => $notes ?: null,
                'version' => 1,
            ];

            // If chunk reaches chunk size, save it in a single transaction
            if (count($chunk) >= $chunkSize) {
                $saved = $this->saveChunk($chunk, $errors, $rowNumber, $club);
                $importedCount += $saved;
                $chunk = [];

                if ($saved === 0 && ! empty($errors)) {
                    // DB-level failure stopped import
                    break;
                }
            }
        }

        // Save remaining records in last chunk
        if (! empty($chunk)) {
            $saved = $this->saveChunk($chunk, $errors, $totalRows + 1, $club);
            $importedCount += $saved;
        }

        return [
            'total_rows' => $totalRows,
            'imported' => $importedCount,
            'errors_count' => count($errors),
            'errors' => $errors,
            'warnings' => $warnings,
        ];
    }

    /**
     * Parse date from various formats (string or Excel timestamp).
     */
    protected function parseBirthDate(mixed $value): ?Carbon
    {
        if ($value === null || trim((string) $value) === '') {
            return null;
        }

        // Numeric Excel timestamp
        if (is_numeric($value)) {
            try {
                $dateObj = ExcelDate::excelToDateTimeObject((float) $value);

                return Carbon::instance($dateObj);
            } catch (\Throwable) {
                return null;
            }
        }

        $str = trim((string) $value);

        // Standard Y-m-d
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $str)) {
            try {
                return Carbon::createFromFormat('Y-m-d', $str);
            } catch (\Throwable) {
                return null;
            }
        }

        // Standard d.m.Y
        if (preg_match('/^\d{2}\.\d{2}\.\d{4}$/', $str)) {
            try {
                return Carbon::createFromFormat('d.m.Y', $str);
            } catch (\Throwable) {
                return null;
            }
        }

        return null;
    }

    /**
     * Save chunk inside DB transaction.
     *
     * @param  list<array<string, mixed>>  $chunk
     * @param  list<array{row: int, message: string}>  &$errors
     */
    protected function saveChunk(array $chunk, array &$errors, int $lastRow, ?Club $club = null): int
    {
        try {
            return DB::transaction(function () use ($chunk, $club) {
                $count = 0;
                $joinedAt = now()->toDateString();

                foreach ($chunk as $item) {
                    $player = Player::create($item);

                    if ($club !== null) {
                        $club->members()->syncWithoutDetaching([
                            $player->id => ['joined_at' => $joinedAt],
                        ]);
                    }

                    $count++;
                }

                return $count;
            });
        } catch (\Throwable $e) {
            Log::error('Failed to persist player chunk during import', [
                'error' => $e->getMessage(),
                'last_row' => $lastRow,
            ]);

            $errors[] = [
                'row' => $lastRow,
                'message' => __('players.import.database_error', ['error' => $e->getMessage()]),
            ];

            return 0;
        }
    }
}
