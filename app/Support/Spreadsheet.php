<?php

namespace App\Support;

use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Writer;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;

/** One way to hand a report over: CSV or XLSX from the same header and rows. */
final class Spreadsheet
{
    /**
     * @param  list<string>  $header
     * @param  iterable<list<mixed>>  $rows
     */
    public static function download(string $basename, string $format, array $header, iterable $rows): Response
    {
        if ($format !== 'xlsx') {
            return Csv::download("{$basename}.csv", $header, $rows);
        }

        $path = tempnam(sys_get_temp_dir(), 'roster').'.xlsx';
        $writer = new Writer;
        $writer->openToFile($path);
        $writer->addRow(Row::fromValuesWithStyle($header, (new Style)->withFontBold(true)));
        foreach ($rows as $row) {
            // Numbers stay numbers so totals work in Excel; text is protected from formula injection.
            $writer->addRow(Row::fromValues(array_map(fn ($v) => is_numeric($v) && ! is_string($v) ? $v : (is_numeric($v) ? (float) $v : Csv::cell($v)), $row)));
        }
        $writer->close();

        return (new BinaryFileResponse($path, 200, ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']))
            ->setContentDisposition('attachment', "{$basename}.xlsx")->deleteFileAfterSend();
    }
}
