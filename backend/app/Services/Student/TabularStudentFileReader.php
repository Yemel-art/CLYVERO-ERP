<?php

declare(strict_types=1);

namespace App\Services\Student;

use DateTimeImmutable;
use Illuminate\Http\UploadedFile;
use RuntimeException;
use SimpleXMLElement;
use ZipArchive;

final class TabularStudentFileReader
{
    private const SPREADSHEET_NAMESPACE = 'http://schemas.openxmlformats.org/spreadsheetml/2006/main';

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

        try {
            $shared = $this->sharedStrings($zip);
            $sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');
            if ($sheetXml === false) {
                throw new RuntimeException('The first Excel worksheet is missing.');
            }
        } finally {
            $zip->close();
        }

        $sheet = $this->xml($sheetXml, 'The first Excel worksheet contains invalid XML.');
        $root = $this->main($sheet);
        $sheetData = $root->sheetData;
        if (! $sheetData instanceof SimpleXMLElement) {
            throw new RuntimeException('The first Excel worksheet has no readable data table.');
        }

        $matrix = [];
        foreach ($sheetData->row as $row) {
            $values = [];
            $rowNodes = $this->main($row);
            foreach ($rowNodes->c as $cell) {
                $attributes = $cell->attributes();
                $reference = (string) ($attributes['r'] ?? '');
                preg_match('/^[A-Z]+/', $reference, $match);
                $column = $this->columnIndex($match[0] ?? 'A');
                $values[$column] = $this->cellValue($cell, $shared);
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
                $value = (string) ($values[$column] ?? '');
                $row[$header] = $this->isDateHeader($header) && is_numeric($value)
                    ? $this->excelDate($value)
                    : $value;
            }
            if (collect($row)->contains(fn (string $value): bool => $value !== '')) {
                $rows[] = $row;
            }
        }

        return $rows;
    }

    /** @return array<int, string> */
    private function sharedStrings(ZipArchive $zip): array
    {
        $sharedXml = $zip->getFromName('xl/sharedStrings.xml');
        if ($sharedXml === false) {
            return [];
        }

        $xml = $this->xml($sharedXml, 'The Excel shared-string table contains invalid XML.');
        $root = $this->main($xml);
        $items = $root->si;
        if (! $items instanceof SimpleXMLElement) {
            return [];
        }

        $shared = [];
        foreach ($items as $item) {
            $nodes = $this->main($item);
            if (isset($nodes->t)) {
                $shared[] = (string) $nodes->t;
                continue;
            }

            $value = '';
            foreach ($nodes->r as $run) {
                $runNodes = $this->main($run);
                $value .= (string) ($runNodes->t ?? '');
            }
            $shared[] = $value;
        }

        return $shared;
    }

    /** @param array<int, string> $shared */
    private function cellValue(SimpleXMLElement $cell, array $shared): string
    {
        $nodes = $this->main($cell);
        $attributes = $cell->attributes();
        $type = (string) ($attributes['t'] ?? '');

        if ($type === 's') {
            return trim($shared[(int) $nodes->v] ?? '');
        }

        if ($type === 'inlineStr') {
            $inline = $nodes->is;
            if (! $inline instanceof SimpleXMLElement) {
                return '';
            }
            $inlineNodes = $this->main($inline);
            if (isset($inlineNodes->t)) {
                return trim((string) $inlineNodes->t);
            }
            $value = '';
            foreach ($inlineNodes->r as $run) {
                $value .= (string) ($this->main($run)->t ?? '');
            }

            return trim($value);
        }

        return trim((string) ($nodes->v ?? ''));
    }

    private function xml(string $content, string $message): SimpleXMLElement
    {
        $previous = libxml_use_internal_errors(true);
        try {
            $xml = simplexml_load_string($content);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }

        if (! $xml instanceof SimpleXMLElement) {
            throw new RuntimeException($message);
        }

        return $xml;
    }

    private function main(SimpleXMLElement $element): SimpleXMLElement
    {
        $namespaces = $element->getDocNamespaces(true);
        $namespace = $namespaces[''] ?? $namespaces['x'] ?? self::SPREADSHEET_NAMESPACE;

        return $element->children($namespace);
    }

    private function isDateHeader(string $header): bool
    {
        $normalized = mb_strtolower(trim($header));
        $normalized = str_replace([' ', '-'], '_', $normalized);

        return in_array($normalized, ['date_of_birth', 'date_de_naissance', 'date_naissance', 'birth_date'], true);
    }

    private function excelDate(string $serial): string
    {
        $days = (int) floor((float) $serial);
        if ($days < 1 || $days > 2958465) {
            return $serial;
        }

        return (new DateTimeImmutable('1899-12-30'))->modify("+{$days} days")->format('Y-m-d');
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
