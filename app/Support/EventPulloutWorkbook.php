<?php

namespace App\Support;

use Phar;
use PharData;
use RuntimeException;
use ZipArchive;

class EventPulloutWorkbook
{
    /**
     * @param  list<array{name: string, barcode: string, item_id: string, physical_stock: int, good_stock: int, damaged_stock: int, pullout_qty: int|string, actual_pullout: int|string}>  $items
     * @param  array{reference?: string, occurred_on?: string, source?: string, notes?: string}  $document
     */
    public static function create(array $items, array $document = []): string
    {
        $path = tempnam(sys_get_temp_dir(), 'event-pullout-');
        if ($path === false) {
            throw new RuntimeException('Unable to create the Event pull-out workbook.');
        }

        try {
            $files = self::workbookFiles($items, $document);
            if (class_exists(ZipArchive::class)) {
                $archive = new ZipArchive;
                $result = $archive->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
                if ($result !== true) {
                    throw new RuntimeException("Unable to open the Event pull-out workbook archive (code {$result}).");
                }
                foreach ($files as $name => $content) {
                    if (! $archive->addFromString($name, $content)) {
                        $archive->close();
                        throw new RuntimeException("Unable to add {$name} to the Event pull-out workbook.");
                    }
                }
                if (! $archive->close()) {
                    throw new RuntimeException('Unable to finish the Event pull-out workbook archive.');
                }
            } else {
                if (! unlink($path)) {
                    throw new RuntimeException('Unable to prepare the Event pull-out workbook.');
                }
                $archive = new PharData($path, 0, null, Phar::ZIP);
                foreach ($files as $name => $content) {
                    $archive->addFromString($name, $content);
                }
                unset($archive);
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
     * @return array{reference: string, occurred_on: string, source: string, notes: string, items: list<array{name: string, barcode: string, item_id: string, physical_stock: string, good_stock: string, damaged_stock: string, pullout_qty: string, actual_pullout: string, stock_condition: string}>}
     */
    public static function read(string $path): array
    {
        $archive = self::openArchive($path);
        try {
            $sharedStrings = self::readSharedStrings($archive);
            $sheetXml = self::archiveEntry($archive, 'xl/worksheets/sheet1.xml');
        } finally {
            self::closeArchive($archive);
        }

        $document = new \DOMDocument;
        if (! $document->loadXML($sheetXml, LIBXML_NONET)) {
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

        $metadata = [];
        foreach ($rows[1] ?? [] as $value) {
            foreach ([
                'reference' => '/^Reference Document No\.:\s*(.*)$/i',
                'source' => '/^Event\s*\/\s*Recipient:\s*(.*)$/i',
                'notes' => '/^Remarks:\s*(.*)$/i',
            ] as $key => $pattern) {
                if (preg_match($pattern, $value, $match)) {
                    $metadata[$key] = trim($match[1]);
                }
            }
        }
        $metadata['occurred_on'] = $rows[1][3] ?? '';

        $columns = [];
        foreach ($rows[2] ?? [] as $column => $heading) {
            $normalized = strtolower(preg_replace('/[^a-z0-9]+/i', ' ', trim($heading)) ?? '');
            $columns[$normalized] = $column;
        }
        $required = [
            'name description',
            'barcode',
            'item id',
            'physical stocks qty',
            'good items',
            'damaged items',
            'physical actual pullout',
        ];
        if (array_diff($required, array_keys($columns))) {
            throw new RuntimeException('The Excel worksheet must contain the Event pull-out headers in row 2.');
        }
        $pulloutColumn = $columns['pull out qty'] ?? null;

        $items = [];
        foreach ($rows as $rowNumber => $row) {
            if ($rowNumber <= 2) {
                continue;
            }
            $items[] = [
                'name' => $row[$columns['name description']] ?? '',
                'barcode' => $row[$columns['barcode']] ?? '',
                'item_id' => $row[$columns['item id']] ?? '',
                'physical_stock' => $row[$columns['physical stocks qty']] ?? '',
                'good_stock' => $row[$columns['good items']] ?? '',
                'damaged_stock' => $row[$columns['damaged items']] ?? '',
                'pullout_qty' => $pulloutColumn === null ? '' : ($row[$pulloutColumn] ?? ''),
                'actual_pullout' => $row[$columns['physical actual pullout']] ?? '',
                'stock_condition' => $row[$columns['stock condition'] ?? 0] ?? '',
            ];
        }

        return [
            'reference' => $metadata['reference'] ?? '',
            'occurred_on' => $metadata['occurred_on'],
            'source' => $metadata['source'] ?? '',
            'notes' => $metadata['notes'] ?? '',
            'items' => $items,
        ];
    }

    /**
     * @param  list<array{name: string, barcode: string, item_id: string, physical_stock: int, good_stock: int, damaged_stock: int, pullout_qty: int|string, actual_pullout: int|string, stock_condition?: string}>  $items
     * @param  array{reference?: string, occurred_on?: string, source?: string, notes?: string}  $document
     * @return array<string, string>
     */
    private static function workbookFiles(array $items, array $document): array
    {
        $lastRow = count($items) + 2;
        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .'<sheetPr><pageSetUpPr fitToPage="1"/></sheetPr>'
            .'<dimension ref="A1:I'.$lastRow.'"/>'
            .'<sheetViews><sheetView showGridLines="0" workbookViewId="0"><pane ySplit="2" topLeftCell="A3" activePane="bottomLeft" state="frozen"/><selection pane="bottomLeft" activeCell="A3" sqref="A3"/></sheetView></sheetViews>'
            .'<sheetFormatPr defaultRowHeight="15"/>'
            .'<cols><col min="1" max="1" width="34" customWidth="1"/><col min="2" max="3" width="22" customWidth="1"/><col min="4" max="8" width="20" customWidth="1"/><col min="9" max="9" width="0" hidden="1" customWidth="1"/></cols>'
            .'<sheetData>'
            .'<row r="1" ht="60" customHeight="1">'
            .self::inlineCell('A1', 'EVENT PULL-OUT', 1)
            .self::inlineCell('B1', 'Reference Document No.: '.($document['reference'] ?? ''), 2)
            .self::inlineCell('C1', $document['occurred_on'] ?? '', 2)
            .self::inlineCell('D1', 'Event / Recipient: '.($document['source'] ?? ''), 2)
            .self::inlineCell('E1', '', 2)
            .self::inlineCell('F1', 'Remarks: '.($document['notes'] ?? ''), 2)
            .self::inlineCell('G1', '', 2)
            .self::inlineCell('H1', '', 2)
            .'</row><row r="2" ht="40" customHeight="1">';

        foreach ([
            'Name / Description',
            'Barcode',
            'Item ID',
            'Physical Stocks Qty',
            'Good Items',
            'Damaged Items',
            'Pull-Out Qty',
            'Physical Actual-Pullout',
            'Stock Condition',
        ] as $index => $heading) {
            $style = $index === 0 ? 6 : 3;
            $xml .= self::inlineCell(self::columnName($index + 1).'2', $heading, $style);
        }
        $xml .= '</row>';

        foreach ($items as $index => $item) {
            $rowNumber = $index + 3;
            $values = [
                $item['name'],
                $item['barcode'],
                $item['item_id'],
                $item['physical_stock'],
                $item['good_stock'],
                $item['damaged_stock'],
                $item['pullout_qty'],
                $item['actual_pullout'],
                $item['stock_condition'] ?? '',
            ];
            $xml .= '<row r="'.$rowNumber.'" ht="30" customHeight="1">';
            foreach ($values as $columnIndex => $value) {
                $cell = self::columnName($columnIndex + 1).$rowNumber;
                if ($columnIndex === 8) {
                    $xml .= self::inlineCell($cell, (string) $value, 4);

                    continue;
                }
                $style = $columnIndex >= 2 ? 5 : 4;
                $xml .= is_int($value)
                    ? '<c r="'.$cell.'" s="'.$style.'"><v>'.$value.'</v></c>'
                    : self::inlineCell($cell, (string) $value, $style);
            }
            $xml .= '</row>';
        }

        $xml .= '</sheetData><mergeCells count="2"><mergeCell ref="D1:E1"/><mergeCell ref="F1:H1"/></mergeCells>'
            .'<printOptions gridLines="0" horizontalCentered="0" verticalCentered="0"/>'
            .'<pageMargins left="0.6" right="0.6" top="0.6" bottom="0" header="0" footer="0"/>'
            .'<pageSetup paperSize="1" orientation="portrait" fitToWidth="1" fitToHeight="0"/></worksheet>';

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
                .'<sheets><sheet name="Event Pullout" sheetId="1" r:id="rId1"/></sheets></workbook>',
            'xl/_rels/workbook.xml.rels' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
                .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
                .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
                .'<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
                .'</Relationships>',
            'xl/styles.xml' => self::stylesXml(),
            'xl/worksheets/sheet1.xml' => $xml,
        ];
    }

    private static function stylesXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .'<fonts count="4"><font><sz val="11"/><name val="Calibri"/></font><font><b/><sz val="16"/><color rgb="FF6B7280"/><name val="Calibri"/></font><font><b/><sz val="11"/><name val="Calibri"/></font><font><b/><sz val="10"/><color rgb="FFFFFFFF"/><name val="Calibri"/></font></fonts>'
            .'<fills count="3"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill><fill><patternFill patternType="solid"><fgColor rgb="FF1F4E78"/><bgColor indexed="64"/></patternFill></fill></fills>'
            .'<borders count="2"><border><left/><right/><top/><bottom/><diagonal/></border><border><left style="thin"><color rgb="FFD1D5DB"/></left><right style="thin"><color rgb="FFD1D5DB"/></right><top style="thin"><color rgb="FFD1D5DB"/></top><bottom style="thin"><color rgb="FFD1D5DB"/></bottom><diagonal/></border></borders>'
            .'<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            .'<cellXfs count="7"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'
            .'<xf numFmtId="0" fontId="1" fillId="0" borderId="1" xfId="0" applyAlignment="1"><alignment vertical="center"/></xf>'
            .'<xf numFmtId="0" fontId="2" fillId="0" borderId="1" xfId="0" applyAlignment="1"><alignment horizontal="left" vertical="top" wrapText="1"/></xf>'
            .'<xf numFmtId="0" fontId="3" fillId="2" borderId="1" xfId="0" applyAlignment="1"><alignment horizontal="center" vertical="center" wrapText="1"/></xf>'
            .'<xf numFmtId="0" fontId="0" fillId="0" borderId="1" xfId="0" applyAlignment="1"><alignment horizontal="left" vertical="center"/></xf>'
            .'<xf numFmtId="0" fontId="0" fillId="0" borderId="1" xfId="0" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf>'
            .'<xf numFmtId="0" fontId="3" fillId="2" borderId="1" xfId="0" applyAlignment="1"><alignment horizontal="left" vertical="center" wrapText="1"/></xf></cellXfs>'
            .'<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles></styleSheet>';
    }

    private static function inlineCell(string $reference, string $value, int $style): string
    {
        return '<c r="'.$reference.'" s="'.$style.'" t="inlineStr"><is><t xml:space="preserve">'
            .htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8')
            .'</t></is></c>';
    }

    private static function openArchive(string $path): ZipArchive|PharData
    {
        if (class_exists(ZipArchive::class)) {
            $archive = new ZipArchive;
            if ($archive->open($path) !== true) {
                throw new RuntimeException('Unable to open the Excel workbook.');
            }

            return $archive;
        }

        try {
            return new PharData($path, 0, null, Phar::ZIP);
        } catch (\Throwable $exception) {
            throw new RuntimeException('Unable to open the Excel workbook.', 0, $exception);
        }
    }

    private static function archiveEntry(ZipArchive|PharData $archive, string $name): string
    {
        $content = $archive instanceof ZipArchive
            ? $archive->getFromName($name)
            : (isset($archive[$name]) ? $archive[$name]->getContent() : false);
        if ($content === false) {
            throw new RuntimeException("The Excel workbook is missing {$name}.");
        }

        return $content;
    }

    /** @return list<string> */
    private static function readSharedStrings(ZipArchive|PharData $archive): array
    {
        $content = $archive instanceof ZipArchive
            ? $archive->getFromName('xl/sharedStrings.xml')
            : (isset($archive['xl/sharedStrings.xml']) ? $archive['xl/sharedStrings.xml']->getContent() : false);
        if ($content === false) {
            return [];
        }

        $document = new \DOMDocument;
        if (! $document->loadXML($content, LIBXML_NONET)) {
            throw new RuntimeException('The Excel shared strings are invalid.');
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

    private static function closeArchive(ZipArchive|PharData $archive): void
    {
        if ($archive instanceof ZipArchive) {
            $archive->close();
        } else {
            unset($archive);
        }
    }

    private static function columnNumber(string $name): int
    {
        $number = 0;
        foreach (str_split($name) as $letter) {
            $number = $number * 26 + ord($letter) - 64;
        }

        return $number;
    }

    private static function columnName(int $number): string
    {
        $name = '';
        while ($number > 0) {
            $number--;
            $name = chr(65 + ($number % 26)).$name;
            $number = intdiv($number, 26);
        }

        return $name;
    }
}
