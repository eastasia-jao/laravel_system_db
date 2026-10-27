<?php

namespace App\Support;

use DOMDocument;
use DOMXPath;
use Phar;
use PharData;
use RuntimeException;
use ZipArchive;

class XlsxTable
{
    /** @return list<list<string>> */
    public static function read(string $path): array
    {
        $archive = self::openArchive($path);
        try {
            $sharedStrings = self::sharedStrings(self::entry($archive, 'xl/sharedStrings.xml', false));
            $sheetXml = self::entry($archive, 'xl/worksheets/sheet1.xml');
        } finally {
            self::closeArchive($archive);
        }

        $document = new DOMDocument;
        if (! $document->loadXML($sheetXml, LIBXML_NONET)) {
            throw new RuntimeException('The Excel worksheet is invalid.');
        }
        $xpath = new DOMXPath($document);
        $xpath->registerNamespace('x', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
        $indexedRows = [];
        $width = 0;
        foreach ($xpath->query('//x:sheetData/x:row') as $rowNode) {
            $row = [];
            foreach ($xpath->query('./x:c', $rowNode) as $cell) {
                preg_match('/^([A-Z]+)/i', $cell->getAttribute('r'), $match);
                $column = self::columnNumber(strtoupper($match[1] ?? 'A'));
                $type = $cell->getAttribute('t');
                if ($type === 'inlineStr') {
                    $value = '';
                    foreach ($xpath->query('.//x:is//x:t', $cell) as $textNode) {
                        $value .= $textNode->textContent;
                    }
                } else {
                    $value = $xpath->query('./x:v', $cell)->item(0)?->textContent ?? '';
                    if ($type === 's') {
                        $value = $sharedStrings[(int) $value] ?? '';
                    }
                }
                $row[$column] = trim((string) $value);
                $width = max($width, $column);
            }
            if ($row !== []) {
                $indexedRows[] = $row;
            }
        }

        return array_map(function (array $row) use ($width) {
            $values = array_fill(0, $width, '');
            foreach ($row as $column => $value) {
                $values[$column - 1] = $value;
            }

            return $values;
        }, $indexedRows);
    }

    /** @param list<list<string>> $rows */
    public static function toCsv(array $rows): string
    {
        $stream = fopen('php://temp', 'r+');
        foreach ($rows as $row) {
            fputcsv($stream, $row);
        }
        rewind($stream);
        $csv = stream_get_contents($stream) ?: '';
        fclose($stream);

        return $csv;
    }

    private static function openArchive(string $path): ZipArchive|PharData
    {
        if (class_exists(ZipArchive::class)) {
            $archive = new ZipArchive;
            if ($archive->open($path) !== true) {
                throw new RuntimeException('The Excel file could not be opened.');
            }

            return $archive;
        }

        try {
            return new PharData($path, 0, null, Phar::ZIP);
        } catch (\Throwable) {
            throw new RuntimeException('The Excel file could not be opened.');
        }
    }

    private static function entry(ZipArchive|PharData $archive, string $name, bool $required = true): ?string
    {
        $content = $archive instanceof ZipArchive
            ? $archive->getFromName($name)
            : (isset($archive[$name]) ? $archive[$name]->getContent() : false);
        if ($content === false && $required) {
            throw new RuntimeException('The Excel file is missing its first worksheet.');
        }

        return $content === false ? null : $content;
    }

    /** @return list<string> */
    private static function sharedStrings(?string $xml): array
    {
        if ($xml === null) {
            return [];
        }
        $document = new DOMDocument;
        if (! $document->loadXML($xml, LIBXML_NONET)) {
            throw new RuntimeException('The Excel shared strings are invalid.');
        }
        $xpath = new DOMXPath($document);
        $xpath->registerNamespace('x', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
        $strings = [];
        foreach ($xpath->query('//x:si') as $item) {
            $value = '';
            foreach ($xpath->query('.//x:t', $item) as $textNode) {
                $value .= $textNode->textContent;
            }
            $strings[] = $value;
        }

        return $strings;
    }

    private static function columnNumber(string $letters): int
    {
        $number = 0;
        foreach (str_split($letters) as $letter) {
            $number = ($number * 26) + ord($letter) - 64;
        }

        return $number;
    }

    private static function closeArchive(ZipArchive|PharData $archive): void
    {
        if ($archive instanceof ZipArchive) {
            $archive->close();
        }
    }
}
