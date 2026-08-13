<?php

declare(strict_types=1);

namespace App\Helpers;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CsvExporter
{
    /**
     * Stream database records as CSV to minimize memory footprint.
     *
     * @param  array<string>  $headers
     * @param  Builder|Relation  $query
     * @param  callable  $rowMapper  Translates a single record to an array of values
     */
    public static function streamDownload(string $filename, array $headers, $query, callable $rowMapper): StreamedResponse
    {
        $responseHeaders = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
            'Pragma' => 'no-cache',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Expires' => '0',
        ];

        return new StreamedResponse(function () use ($headers, $query, $rowMapper) {
            $file = fopen('php://output', 'w');
            if ($file === false) {
                return;
            }

            // Write UTF-8 BOM for Excel compatibility
            fwrite($file, "\xEF\xBB\xBF");

            // Write column headers
            fputcsv($file, $headers);

            // Chunk through database rows to avoid loading large query results into memory
            $query->chunk(500, function ($records) use ($file, $rowMapper) {
                foreach ($records as $record) {
                    fputcsv($file, $rowMapper($record));
                }
                if (ob_get_level() > 0) {
                    ob_flush();
                    flush();
                }
            });

            fclose($file);
        }, 200, $responseHeaders);
    }
}
