<?php

namespace App\Support;

use Phar;
use PharData;
use RuntimeException;

class NationalPulloutWorkbook
{
    /**
     * @param  list<array{name: string, quantity: int|string, unit_type: string, purpose: string, physical_stock: int, actual_pullout: int|string}>  $items
     */
    public static function create(array $items): string
    {
        $path = tempnam(sys_get_temp_dir(), 'national-pullout-');
        if ($path === false) {
            throw new RuntimeException('Unable to create a temporary National Bookstore Pullout workbook.');
        }
        if (! unlink($path)) {
            throw new RuntimeException('Unable to prepare the temporary National Bookstore Pullout workbook.');
        }

        $archive = new PharData($path, 0, null, Phar::ZIP);
        foreach (self::workbookFiles($items) as $name => $content) {
            $archive->addFromString($name, $content);
        }
        unset($archive);

        return $path;
    }

    /**
     * @param  list<array{name: string, quantity: int|string, unit_type: string, purpose: string, physical_stock: int, actual_pullout: int|string}>  $items
     * @return array<string, string>
     */
    private static function workbookFiles(array $items): array
    {
        $lastRow = count($items) + 3;
        $rows = [
            self::rowXml(1, ['P.O. #:', '', '', 'Date:', '', 'Remarks:', '', '', ''], 20, 3),
            self::rowXml(2, array_fill(0, 9, ''), 20, 1),
            self::rowXml(3, [
                'Product Item Name', '', '', 'Qty', 'Unit Type', 'Purpose', '', 'Physical Stocks', 'Actual Pull-out',
            ], 30, 2),
        ];

        foreach ($items as $index => $item) {
            $row = $index + 4;
            $rows[] = self::rowXml($row, [
                $item['name'],
                '',
                '',
                $item['quantity'],
                $item['unit_type'],
                $item['purpose'],
                '',
                $item['physical_stock'],
                $item['actual_pullout'],
            ], 20, 1, [4 => 4, 5 => 4, 7 => 4, 8 => 4]);
        }

        $sheetData = implode('', $rows);
        $merges = ['A1:C1', 'D1:E1', 'F1:I1', 'A2:C2', 'D2:E2', 'F2:I2', 'A3:C3', 'F3:G3'];
        for ($row = 4; $row <= $lastRow; $row++) {
            $merges[] = "A{$row}:C{$row}";
            $merges[] = "F{$row}:G{$row}";
        }
        $mergeXml = '<mergeCells count="'.count($merges).'">'.implode('', array_map(
            fn (string $range) => '<mergeCell ref="'.$range.'"/>',
            $merges
        )).'</mergeCells>';

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
                .'<definedNames><definedName name="_xlnm.Print_Area" localSheetId="0">\'Pullout\'!$A$1:$I$'.$lastRow.'</definedName>'
                .'<definedName name="_xlnm.Print_Titles" localSheetId="0">\'Pullout\'!$1:$3</definedName></definedNames>'
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
                .'<dimension ref="A1:I'.$lastRow.'"/>'
                .'<sheetViews><sheetView showGridLines="0" workbookViewId="0"><selection activeCell="A1" sqref="A1"/></sheetView></sheetViews>'
                .'<sheetFormatPr defaultRowHeight="15"/>'
                .'<cols><col min="1" max="1" width="40" customWidth="1"/>'
                .'<col min="2" max="3" width="2.5" customWidth="1"/>'
                .'<col min="4" max="5" width="9.5" customWidth="1"/>'
                .'<col min="6" max="7" width="9" customWidth="1"/>'
                .'<col min="8" max="9" width="9.5" customWidth="1"/></cols>'
                .'<sheetData>'.$sheetData.'</sheetData>'.$mergeXml
                .'<printOptions gridLines="0" horizontalCentered="0" verticalCentered="0"/>'
                .'<pageMargins left="0.6" right="0" top="0" bottom="0" header="0" footer="0"/>'
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
            .'<fonts count="2"><font><sz val="10"/><name val="Arial"/></font><font><b/><sz val="10"/><name val="Arial"/></font></fonts>'
            .'<fills count="3"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill>'
            .'<fill><patternFill patternType="solid"><fgColor rgb="FFF1F5F9"/><bgColor indexed="64"/></patternFill></fill></fills>'
            .'<borders count="2"><border><left/><right/><top/><bottom/><diagonal/></border>'
            .'<border><left style="thin"><color rgb="FF64748B"/></left><right style="thin"><color rgb="FF64748B"/></right>'
            .'<top style="thin"><color rgb="FF64748B"/></top><bottom style="thin"><color rgb="FF64748B"/></bottom><diagonal/></border></borders>'
            .'<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            .'<cellXfs count="5"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'
            .'<xf numFmtId="0" fontId="0" fillId="1" borderId="1" xfId="0"><alignment vertical="center" shrinkToFit="1"/></xf>'
            .'<xf numFmtId="0" fontId="1" fillId="2" borderId="1" xfId="0"><alignment horizontal="center" vertical="center" wrapText="1"/></xf>'
            .'<xf numFmtId="0" fontId="1" fillId="0" borderId="1" xfId="0"><alignment vertical="center" wrapText="1"/></xf>'
            .'<xf numFmtId="0" fontId="0" fillId="0" borderId="1" xfId="0"><alignment horizontal="center" vertical="center" wrapText="1"/></xf>'
            .'</cellXfs><cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>'
            .'</styleSheet>';
    }
}
