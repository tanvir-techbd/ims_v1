<?php

namespace App\Support\Reports;

use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Plain-PHP CSV streaming — no third-party Excel package. pxlrbt/filament-
 * excel (which wraps maatwebsite/excel -> phpoffice/phpspreadsheet) can't
 * currently install on this project's PHP 8.5 + Filament 3.3 combination
 * (phpspreadsheet caps at PHP <8.5, and the phpspreadsheet-compatible
 * filament-excel majors require Filament 4/5). CSV opens cleanly in Excel/
 * LibreOffice/Sheets, satisfying the "Excel/CSV export" requirement without
 * the dependency conflict. Swap in true .xlsx export later if the ecosystem
 * catches up — the export button/action shape here won't need to change.
 *
 * @param  array<int, string>  $headers
 * @param  iterable<int, array<int, string|int|null>>  $rows
 */
class CsvExport
{
    public static function stream(string $filename, array $headers, iterable $rows): StreamedResponse
    {
        return new StreamedResponse(function () use ($headers, $rows) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, $headers);

            foreach ($rows as $row) {
                fputcsv($handle, $row);
            }

            fclose($handle);
        }, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }
}
