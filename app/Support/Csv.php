<?php

namespace App\Support;

use Symfony\Component\HttpFoundation\StreamedResponse;

final class Csv
{
    /**
     * @param  list<string>  $header
     * @param  iterable<list<mixed>>  $rows
     */
    public static function download(string $filename, array $header, iterable $rows): StreamedResponse
    {
        return response()->streamDownload(function () use ($header, $rows) {
            $out = fopen('php://output', 'w');
            fputcsv($out, $header, escape: '');
            foreach ($rows as $row) {
                fputcsv($out, array_map(self::cell(...), $row), escape: '');
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** Stops spreadsheet apps from running a cell as a formula (CSV injection). */
    public static function cell(mixed $value): mixed
    {
        return is_string($value) && $value !== '' && str_contains('=+-@', $value[0]) ? "'".$value : $value;
    }
}
