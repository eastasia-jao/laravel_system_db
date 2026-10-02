<?php

namespace App\Support;

class ProductExportWorkbook
{
    public static function fromCsv(string $csv): string
    {
        return self::fromRows(self::readRows($csv));
    }

    public static function fromRows(array $rows): string
    {
        $sheet = self::worksheetXml($rows);

        return self::zip(self::packageFiles($sheet));
    }

    private static function readRows(string $csv): array
    {
        $stream = fopen('php://temp', 'r+');
        fwrite($stream, $csv);
        rewind($stream);
        $rows = [];

        try {
            while (($row = fgetcsv($stream)) !== false) {
                if ($rows === [] && isset($row[0])) {
                    $row[0] = preg_replace('/^\xEF\xBB\xBF/', '', $row[0]);
                }
                if ($rows !== []) {
                    $row[1] = CsvIdentifier::read($row[1] ?? '', 'Item ID');
                    $row[4] = CsvIdentifier::read($row[4] ?? '', 'Barcode');
                }
                $rows[] = $row;
            }
        } finally {
            fclose($stream);
        }

        return $rows;
    }

    private static function worksheetXml(array $rows): string
    {
        $lastRow = max(1, count($rows));
        $columnCount = max(1, count($rows[0] ?? []));
        $lastColumn = self::columnName($columnCount);
        $headers = array_map(fn ($value) => strtolower(trim((string) $value)), $rows[0] ?? []);
        $textColumns = array_keys(array_filter($headers, fn ($value) => in_array($value, ['item id', 'name', 'description', 'barcode', 'brand', 'retail group', 'retail department', 'unit type'], true)));
        $decimalColumns = array_keys(array_filter($headers, fn ($value) => str_ends_with($value, 'price')));
        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>';
        $xml .= '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">';
        $xml .= '<dimension ref="A1:'.$lastColumn.$lastRow.'"/><sheetViews><sheetView tabSelected="1" workbookViewId="0"><pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews><sheetFormatPr defaultRowHeight="15"/><sheetData>';

        foreach ($rows as $rowIndex => $row) {
            $number = $rowIndex + 1;
            $xml .= '<row r="'.$number.'">';
            foreach (array_slice($row, 0, $columnCount) as $columnIndex => $value) {
                $reference = self::columnName($columnIndex + 1).$number;
                $xml .= self::cellXml($reference, $value, $rowIndex === 0, $columnIndex, $textColumns, $decimalColumns);
            }
            $xml .= '</row>';
        }

        $xml .= '</sheetData><autoFilter ref="A1:'.$lastColumn.$lastRow.'"/><pageMargins left="0.7" right="0.7" top="0.75" bottom="0.75" header="0.3" footer="0.3"/></worksheet>';

        return $xml;
    }

    private static function cellXml(string $reference, mixed $value, bool $header, int $columnIndex, array $textColumns, array $decimalColumns): string
    {
        $value = (string) $value;
        if ($header || in_array($columnIndex, $textColumns, true) || $value === '' || ! is_numeric($value)) {
            $style = $header ? 1 : (in_array($columnIndex, $textColumns, true) ? 2 : 0);

            return '<c r="'.$reference.'" s="'.$style.'" t="inlineStr"><is><t xml:space="preserve">'.self::xml($value).'</t></is></c>';
        }

        $style = in_array($columnIndex, $decimalColumns, true) ? 3 : 0;

        return '<c r="'.$reference.'" s="'.$style.'"><v>'.self::xml($value).'</v></c>';
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

    private static function xml(string $value): string
    {
        $value = preg_replace('/[^\P{C}\t\r\n]/u', '', $value) ?? '';

        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }

    private static function packageFiles(string $sheet): array
    {
        return [
            '[Content_Types].xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/><Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/><Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/><Override PartName="/docProps/app.xml" ContentType="application/vnd.openxmlformats-officedocument.extended-properties+xml"/></Types>',
            '_rels/.rels' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties" Target="docProps/core.xml"/><Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/extended-properties" Target="docProps/app.xml"/></Relationships>',
            'docProps/core.xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties" xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:dcterms="http://purl.org/dc/terms/" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"><dc:creator>Art Caravan PH</dc:creator><cp:lastModifiedBy>Art Caravan PH</cp:lastModifiedBy></cp:coreProperties>',
            'docProps/app.xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Properties xmlns="http://schemas.openxmlformats.org/officeDocument/2006/extended-properties" xmlns:vt="http://schemas.openxmlformats.org/officeDocument/2006/docPropsVTypes"><Application>Art Caravan PH</Application><AppVersion>16.0300</AppVersion></Properties>',
            'xl/workbook.xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><workbookPr/><bookViews><workbookView xWindow="0" yWindow="0" windowWidth="24000" windowHeight="12000"/></bookViews><sheets><sheet name="Products" sheetId="1" state="visible" r:id="rId1"/></sheets><calcPr calcId="191029"/></workbook>',
            'xl/_rels/workbook.xml.rels' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/></Relationships>',
            'xl/styles.xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><fonts count="2"><font><sz val="11"/><name val="Calibri"/><family val="2"/></font><font><b/><color rgb="FFFFFFFF"/><sz val="11"/><name val="Calibri"/><family val="2"/></font></fonts><fills count="3"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill><fill><patternFill patternType="solid"><fgColor rgb="FF1D4ED8"/><bgColor indexed="64"/></patternFill></fill></fills><borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders><cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs><cellXfs count="4"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/><xf numFmtId="0" fontId="1" fillId="2" borderId="0" xfId="0" applyFont="1" applyFill="1"/><xf numFmtId="49" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1"/><xf numFmtId="4" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1"/></cellXfs><cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles><dxfs count="0"/><tableStyles count="0" defaultTableStyle="TableStyleMedium2" defaultPivotStyle="PivotStyleLight16"/></styleSheet>',
            'xl/worksheets/sheet1.xml' => $sheet,
        ];
    }

    private static function zip(array $files): string
    {
        $body = '';
        $directory = '';
        $offset = 0;
        $dosTime = 0;
        $dosDate = (1 << 5) | 1;

        foreach ($files as $path => $content) {
            $compressed = gzdeflate($content, 9);
            $crc = hexdec(hash('crc32b', $content));
            $pathLength = strlen($path);
            $compressedLength = strlen($compressed);
            $contentLength = strlen($content);
            $local = pack(
                'VvvvvvVVVvv',
                0x04034b50, 20, 0, 8, $dosTime, $dosDate,
                $crc, $compressedLength, $contentLength, $pathLength, 0
            ).$path.$compressed;
            $body .= $local;
            $directory .= pack(
                'VvvvvvvVVVvvvvvVV',
                0x02014b50, 20, 20, 0, 8, $dosTime, $dosDate,
                $crc, $compressedLength, $contentLength, $pathLength,
                0, 0, 0, 0, 0, $offset
            ).$path;
            $offset += strlen($local);
        }

        return $body.$directory.pack(
            'VvvvvVVv',
            0x06054b50, 0, 0, count($files), count($files), strlen($directory), strlen($body), 0
        );
    }
}
