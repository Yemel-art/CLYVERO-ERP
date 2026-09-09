<?php

declare(strict_types=1);

namespace App\Services\Student;

use Illuminate\Http\UploadedFile;
use RuntimeException;
use SimpleXMLElement;
use ZipArchive;

final class TabularStudentFileReader
{
    /** @return array<int, array<string, string>> */
    public function read(UploadedFile $file): array
    {
        return strtolower($file->getClientOriginalExtension()) === 'xlsx'
            ? $this->xlsx($file->getRealPath())
            : $this->csv($file->getRealPath());
    }

    /** @return array<int, array<string, string>> */
    private function csv(string $path): array
    {
        $handle = fopen($path, 'rb');
        if ($handle === false) {
            throw new RuntimeException('The import file could not be opened.');
        }
        $sample = (string) fgets($handle);
        rewind($handle);
        $delimiter = collect([',', ';', "\t"])->sortByDesc(fn (string $candidate): int => substr_count($sample, $candidate))->first() ?? ',';
        $headers = fgetcsv($handle, 0, $delimiter);
        if (! is_array($headers)) {
            fclose($handle);
            throw new RuntimeException('The import file has no header row.');
        }
        $headers = array_map(fn ($value): string => $this->clean((string) $value), $headers);
        $rows = [];
        while (($values = fgetcsv($handle, 0, $delimiter)) !== false) {
            $values = array_pad($values, count($headers), '');
            $row = array_combine($headers, array_slice(array_map(fn ($value): string => trim((string) $value), $values), 0, count($headers)));
            if (is_array($row) && collect($row)->contains(fn (string $value): bool => $value !== '')) {
                $rows[] = $row;
            }
        }
        fclose($handle);

        return $rows;
    }

    /** @return array<int, array<string, string>> */
    private function xlsx(string $path): array
    {
        if (! class_exists(ZipArchive::class)) {
            throw new RuntimeException('The PHP zip extension is required to read Excel files.');
        }
        $zip = new ZipArchive();
        if ($zip->open($path) !== true) {
            throw new RuntimeException('The Excel file could not be opened.');
        }
        $shared = [];
        $sharedXml = $zip->getFromName('xl/sharedStrings.xml');
        if ($sharedXml !== false) {
            $xml = new SimpleXMLElement($sharedXml);
            foreach ($xml->si as $item) {
                $shared[] = isset($item->t) ? (string) $item->t : implode('', array_map('strval', iterator_to_array($item->r->t ?? [])));
            }
        }
        $sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');
        $zip->close();
        if ($sheetXml === false) {
            throw new RuntimeException('The first Excel worksheet is missing.');
        }
        $sheet = new SimpleXMLElement($sheetXml);
        $matrix = [];
        foreach ($sheet->sheetData->row as $row) {
            $values = [];
            foreach ($row->c as $cell) {
                $reference = (string) $cell['r'];
                preg_match('/^[A-Z]+/', $reference, $match);
                $column = $this->columnIndex($match[0] ?? 'A');
                $type = (string) $cell['t'];
                $value = $type === 's'
                    ? ($shared[(int) $cell->v] ?? '')
                    : ($type === 'inlineStr' ? (string) $cell->is->t : (string) $cell->v);
                $values[$column] = trim($value);
            }
            if ($values !== []) {
                $matrix[] = $values;
            }
        }
        if ($matrix === []) {
            return [];
        }
        $headers = [];
        foreach ($matrix[0] as $column => $header) {
            $headers[$column] = $this->clean($header);
        }
        $rows = [];
        foreach (array_slice($matrix, 1) as $values) {
            $row = [];
            foreach ($headers as $column => $header) {
                $row[$header] = (string) ($values[$column] ?? '');
            }
            if (collect($row)->contains(fn (string $value): bool => $value !== '')) {
                $rows[] = $row;
            }
        }

        return $rows;
    }

    private function clean(string $header): string
    {
        $header = preg_replace('/^\xEF\xBB\xBF/', '', $header) ?? $header;

        return trim($header);
    }

    private function columnIndex(string $letters): int
    {
        $index = 0;
        foreach (str_split($letters) as $letter) {
            $index = ($index * 26) + (ord($letter) - 64);
        }

        return $index - 1;
    }
}
