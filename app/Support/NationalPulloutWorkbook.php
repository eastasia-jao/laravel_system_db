<?php

namespace App\Support;

use Phar;
use PharData;
use RuntimeException;
use ZipArchive;

class NationalPulloutWorkbook
{
    /**
     * @return array{po_number: string, occurred_on: string, remarks: string, items: list<array{name: string, quantity: string, actual_pullout: string}>}
     */
    public static function read(string $path): array
    {
        $sharedStringsXml = self::archiveEntry($path, 'xl/sharedStrings.xml', false);
        $sharedStrings = $sharedStringsXml === null ? [] : self::sharedStrings($sharedStringsXml);
        $sheetXml = self::archiveEntry($path, 'xl/worksheets/sheet1.xml');
        $document = new \DOMDocument;
        if (! $document->loadXML($sheetXml)) {
            throw new RuntimeException('The Excel worksheet XML is invalid.');
        }
        $xpath = new \DOMXPath($document);
        $xpath->registerNamespace('x', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
        $rows = [];
        foreach ($xpath->query('//x:sheetData/x:row') as $rowNode) {
            $rowNumber = (int) $rowNode->getAttribute('r');
            foreach ($xpath->query('./x:c', $rowNode) as $cell) {
                preg_match('/^([A-Z]+)/', $cell->getAttribute('r'), $match);
                $column = self::columnNumber($match[1] ?? 'A');
                $type = $cell->getAttribute('t');
                if ($type === 'inlineStr') {
                    $value = '';
                    foreach ($xpath->query('.//x:is//x:t', $cell) as $textNode) {
                        $value .= $textNode->textContent;
                    }
                } else {
                    $valueNode = $xpath->query('./x:v', $cell)->item(0);
                    $value = $valueNode?->textContent ?? '';
                    if ($type === 's') {
                        $value = $sharedStrings[(int) $value] ?? '';
                    }
                }
                $rows[$rowNumber][$column] = trim((string) $value);
            }
        }

        $poNumber = $occurredOn = $remarks = '';
        foreach ($rows as $row) {
            foreach ($row as $value) {
                if (preg_match('/^P\.O\.\s*#:\s*(.*)$/i', $value, $match)) {
                    $poNumber = trim($match[1]);
                } elseif (preg_match('/^Date:\s*(.*)$/i', $value, $match)) {
                    $occurredOn = trim($match[1]);
                } elseif (preg_match('/^Remarks:\s*(.*)$/i', $value, $match)) {
                    $remarks = trim($match[1]);
                }
            }
        }

        $headerRow = null;
        $columns = [];
        foreach ($rows as $rowNumber => $row) {
            foreach ($row as $column => $value) {
                $heading = strtolower(trim($value));
                if ($heading === 'product item name') {
                    $headerRow = $rowNumber;
                    $columns['name'] = $column;
                } elseif ($heading === 'qty') {
                    $columns['quantity'] = $column;
                } elseif (in_array($heading, ['actual pull-out', 'actual pull out'], true)) {
                    $columns['actual_pullout'] = $column;
                }
            }
            if ($headerRow !== null) {
                break;
            }
        }
        if ($headerRow === null || ! isset($columns['quantity'], $columns['actual_pullout'])) {
            throw new RuntimeException('The Excel worksheet must contain Product Item Name, Qty, and Actual Pull-out headers.');
        }

        $items = [];
        foreach ($rows as $rowNumber => $row) {
            if ($rowNumber <= $headerRow) {
                continue;
            }
            $name = trim($row[$columns['name']] ?? '');
            $quantity = trim($row[$columns['quantity']] ?? '');
            $actual = trim($row[$columns['actual_pullout']] ?? '');
            if ($name === '' || ($quantity === '' && $actual === '')) {
                continue;
            }
            $items[] = ['name' => $name, 'quantity' => $quantity, 'actual_pullout' => $actual];
        }

        return [
            'po_number' => $poNumber,
            'occurred_on' => $occurredOn,
            'remarks' => $remarks,
            'items' => $items,
        ];
    }

    /**
     * @param  list<array{name: string, quantity: int|string, purpose: string, physical_stock: int|string, actual_pullout: int|string}>  $items
     * @param  array{po_number?: string, occurred_on?: string, remarks?: string}  $document
     */
    public static function create(array $items, array $document = []): string
    {
        $path = tempnam(sys_get_temp_dir(), 'national-pullout-');
        if ($path === false) {
            throw new RuntimeException('Unable to create a temporary National Bookstore Pullout workbook.');
        }

        try {
            $files = self::workbookFiles($items, $document);
            if (class_exists(ZipArchive::class)) {
                self::createZipArchive($path, $files);
            } else {
                self::createPharArchive($path, $files);
            }
        } catch (\Throwable $exception) {
            if (file_exists($path)) {
                unlink($path);
            }

            throw $exception;
        }

        return $path;
    }

    /**
     * @param  array<string, string>  $files
     */
    private static function createZipArchive(string $path, array $files): void
    {
        $archive = new ZipArchive;
        $result = $archive->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        if ($result !== true) {
            throw new RuntimeException("Unable to open the National Bookstore Pullout workbook archive (code {$result}).");
        }

        foreach ($files as $name => $content) {
            if (! $archive->addFromString($name, $content)) {
                $archive->close();
                throw new RuntimeException("Unable to add {$name} to the National Bookstore Pullout workbook.");
            }
        }

        if (! $archive->close()) {
            throw new RuntimeException('Unable to finish the National Bookstore Pullout workbook archive.');
        }
    }

    /**
     * @param  array<string, string>  $files
     */
    private static function createPharArchive(string $path, array $files): void
    {
        if (! unlink($path)) {
            throw new RuntimeException('Unable to prepare the temporary National Bookstore Pullout workbook.');
        }

        $archive = new PharData($path, 0, null, Phar::ZIP);
        foreach ($files as $name => $content) {
            $archive->addFromString($name, $content);
        }
        unset($archive);
    }

    private static function archiveEntry(string $path, string $name, bool $required = true): ?string
    {
        if (class_exists(ZipArchive::class)) {
            $archive = new ZipArchive;
            if ($archive->open($path) !== true) {
                throw new RuntimeException('Unable to open the Excel workbook.');
            }
            $content = $archive->getFromName($name);
            $archive->close();
        } else {
            try {
                $archive = new PharData($path, 0, null, Phar::ZIP);
                $content = isset($archive[$name]) ? $archive[$name]->getContent() : false;
                unset($archive);
            } catch (\Throwable $exception) {
                throw new RuntimeException('Unable to open the Excel workbook.', 0, $exception);
            }
        }
        if ($content === false && $required) {
            throw new RuntimeException("The Excel workbook is missing {$name}.");
        }

        return $content === false ? null : $content;
    }

    /** @return list<string> */
    private static function sharedStrings(string $xml): array
    {
        $document = new \DOMDocument;
        if (! $document->loadXML($xml)) {
            return [];
        }
        $xpath = new \DOMXPath($document);
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

    private static function columnNumber(string $name): int
    {
        $number = 0;
        foreach (str_split($name) as $character) {
            $number = ($number * 26) + ord($character) - 64;
        }

        return $number;
    }

    /**
     * @param  list<array{name: string, quantity: int|string, purpose: string, physical_stock: int|string, actual_pullout: int|string}>  $items
     * @param  array{po_number?: string, occurred_on?: string, remarks?: string}  $document
     * @return array<string, string>
     */
    private static function workbookFiles(array $items, array $document): array
    {
        $blankRows = $items === [] ? 10 : 0;
        $lastRow = count($items) + $blankRows + 2;
        $rows = [
            self::rowXml(1, [
                'NBS',
                'P.O. #: '.($document['po_number'] ?? ''),
                '',
                'Date: '.($document['occurred_on'] ?? ''),
                'Remarks: '.($document['remarks'] ?? ''),
                '',
            ], 20, 3, [0 => 5]),
            self::rowXml(2, ['', 'Product Item Name', 'Qty', 'Purpose', 'Physical Stocks', 'Actual Pull-out'], 30, 2),
        ];

        foreach ($items as $index => $item) {
            $row = $index + 3;
            $rows[] = self::rowXml($row, [
                '',
                $item['name'],
                $item['quantity'],
                $item['purpose'],
                $item['physical_stock'],
                $item['actual_pullout'],
            ], 20, 1, [2 => 4, 4 => 4, 5 => 4]);
        }
        for ($index = 0; $index < $blankRows; $index++) {
            $rows[] = self::rowXml(count($items) + $index + 3, array_fill(0, 6, ''), 20, 1, [2 => 4, 4 => 4, 5 => 4]);
        }

        $sheetData = implode('', $rows);

        return [
            '[Content_Types].xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
                .'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
                .'<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
                .'<Default Extension="xml" ContentType="application/xml"/>'
                .'<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
                .'<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
                .'<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
                .'</Types>',
            '_rels/.rels' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
                .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
                .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
                .'</Relationships>',
            'xl/workbook.xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
                .'<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
                .'<sheets><sheet name="Pullout" sheetId="1" r:id="rId1"/></sheets>'
                .'<definedNames><definedName name="_xlnm.Print_Area" localSheetId="0">\'Pullout\'!$A$1:$F$'.$lastRow.'</definedName>'
                .'<definedName name="_xlnm.Print_Titles" localSheetId="0">\'Pullout\'!$1:$2</definedName></definedNames>'
                .'</workbook>',
            'xl/_rels/workbook.xml.rels' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
                .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
                .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
                .'<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
                .'</Relationships>',
            'xl/styles.xml' => self::stylesXml(),
            'xl/worksheets/sheet1.xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
                .'<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
                .'<sheetPr><pageSetUpPr fitToPage="1"/></sheetPr>'
                .'<dimension ref="A1:F'.$lastRow.'"/>'
                .'<sheetViews><sheetView showGridLines="0" workbookViewId="0"><selection activeCell="A1" sqref="A1"/></sheetView></sheetViews>'
                .'<sheetFormatPr defaultRowHeight="15"/>'
                .'<cols><col min="1" max="1" width="8" customWidth="1"/>'
                .'<col min="2" max="2" width="48" customWidth="1"/>'
                .'<col min="3" max="3" width="9.5" customWidth="1"/>'
                .'<col min="4" max="4" width="20" customWidth="1"/>'
                .'<col min="5" max="5" width="18" customWidth="1"/>'
                .'<col min="6" max="6" width="16" customWidth="1"/></cols>'
                .'<sheetData>'.$sheetData.'</sheetData>'
                .'<mergeCells count="2"><mergeCell ref="B1:C1"/><mergeCell ref="E1:F1"/></mergeCells>'
                .'<printOptions gridLines="0" horizontalCentered="0" verticalCentered="0"/>'
                .'<pageMargins left="0.6" right="0.6" top="0.6" bottom="0" header="0" footer="0"/>'
                .'<pageSetup paperSize="1" orientation="portrait" fitToWidth="1" fitToHeight="0"/>'
                .'</worksheet>',
        ];
    }

    /**
     * @param  list<int|string>  $values
     * @param  array<int, int>  $cellStyles
     */
    private static function rowXml(int $number, array $values, int $height, int $defaultStyle, array $cellStyles = []): string
    {
        $xml = '<row r="'.$number.'" ht="'.$height.'" customHeight="1">';
        foreach ($values as $index => $value) {
            $cell = self::columnName($index + 1).$number;
            $style = $cellStyles[$index] ?? $defaultStyle;
            if (is_int($value) || is_float($value)) {
                $xml .= '<c r="'.$cell.'" s="'.$style.'" t="n"><v>'.$value.'</v></c>';
            } elseif ($value === '') {
                $xml .= '<c r="'.$cell.'" s="'.$style.'"/>';
            } else {
                $xml .= '<c r="'.$cell.'" s="'.$style.'" t="inlineStr"><is><t xml:space="preserve">'
                    .htmlspecialchars((string) $value, ENT_XML1 | ENT_COMPAT, 'UTF-8')
                    .'</t></is></c>';
            }
        }
        $xml .= '</row>';

        return $xml;
    }

    private static function columnName(int $column): string
    {
        $name = '';
        while ($column > 0) {
            $column--;
            $name = chr(65 + $column % 26).$name;
            $column = intdiv($column, 26);
        }

        return $name;
    }

    private static function stylesXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .'<fonts count="3"><font><sz val="10"/><name val="Arial"/></font><font><b/><sz val="10"/><name val="Arial"/></font><font><b/><color rgb="FFFFFFFF"/><sz val="10"/><name val="Arial"/></font></fonts>'
            .'<fills count="4"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill>'
            .'<fill><patternFill patternType="solid"><fgColor rgb="FFF1F5F9"/><bgColor indexed="64"/></patternFill></fill>'
            .'<fill><patternFill patternType="solid"><fgColor rgb="FFD92D20"/><bgColor indexed="64"/></patternFill></fill></fills>'
            .'<borders count="2"><border><left/><right/><top/><bottom/><diagonal/></border>'
            .'<border><left style="thin"><color rgb="FF64748B"/></left><right style="thin"><color rgb="FF64748B"/></right>'
            .'<top style="thin"><color rgb="FF64748B"/></top><bottom style="thin"><color rgb="FF64748B"/></bottom><diagonal/></border></borders>'
            .'<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            .'<cellXfs count="6"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'
            .'<xf numFmtId="0" fontId="0" fillId="1" borderId="1" xfId="0"><alignment vertical="center" shrinkToFit="1"/></xf>'
            .'<xf numFmtId="0" fontId="1" fillId="2" borderId="1" xfId="0"><alignment horizontal="center" vertical="center" wrapText="1"/></xf>'
            .'<xf numFmtId="0" fontId="1" fillId="0" borderId="1" xfId="0"><alignment vertical="center" wrapText="1"/></xf>'
            .'<xf numFmtId="0" fontId="0" fillId="0" borderId="1" xfId="0"><alignment horizontal="center" vertical="center" wrapText="1"/></xf>'
            .'<xf numFmtId="0" fontId="2" fillId="3" borderId="1" xfId="0"><alignment horizontal="center" vertical="center"/></xf>'
            .'</cellXfs><cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>'
            .'</styleSheet>';
    }
}
