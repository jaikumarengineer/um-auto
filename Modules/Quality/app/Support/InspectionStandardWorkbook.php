<?php

namespace Modules\Quality\Support;

use Modules\Quality\Models\IncomingPart;
use Modules\Quality\Models\InspectionStandard;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use ZipArchive;

class InspectionStandardWorkbook
{
    /** Excel character widths from the printed inspection standard, columns A–K. */
    private const WIDTHS = [11.0, 15.0, 11.0, 9.0, 15.0, 16.5, 13.5, 13.5, 12.0, 14.0, 12.5];

    private const YELLOW = 1;

    private const VALUE = 2;

    private const TITLE = 3;

    private const DATA = 4;

    private const LEGEND = 5;

    /**
     * @var array<int, array{height: float, cells: array<int, array{value: string, style: int}>}>
     */
    private array $rows = [];

    /** @var list<string> */
    private array $merges = [];

    public static function download(InspectionStandard $standard, IncomingPart $part, string $filename): BinaryFileResponse
    {
        $tmp = tempnam(sys_get_temp_dir(), 'xlsx');
        $zip = new ZipArchive;
        $zip->open($tmp, ZipArchive::OVERWRITE);
        $zip->addFromString('[Content_Types].xml', self::contentTypes());
        $zip->addFromString('_rels/.rels', self::rootRels());
        $zip->addFromString('xl/workbook.xml', self::workbook());
        $zip->addFromString('xl/_rels/workbook.xml.rels', self::workbookRels());
        $zip->addFromString('xl/styles.xml', self::styles());
        $zip->addFromString('xl/worksheets/sheet1.xml', (new self)->sheet($standard, $part));
        $zip->close();

        return response()->download($tmp, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend(true);
    }

    private function sheet(InspectionStandard $standard, IncomingPart $part): string
    {
        $date = $standard->effective_from?->format('d.m.Y') ?? '';

        $this->put(1, 1, 'Inspection Standard - Parts', self::TITLE, 33);
        $this->merge(1, 1, 1, 11);

        $this->put(2, 1, 'UMAC-IIS', self::YELLOW, 16);
        $this->put(2, 2, "Supplier's Name", self::YELLOW);
        $this->merge(2, 2, 2, 4);
        $this->put(2, 5, "Supplier's Code", self::YELLOW);
        $this->merge(2, 5, 2, 7);
        $this->put(2, 8, 'Doc.No.', self::YELLOW);
        $this->put(2, 9, (string) $standard->doc_no, self::VALUE);
        $this->put(2, 10, 'Page', self::YELLOW);
        $this->put(2, 11, (string) $standard->page_label, self::VALUE);

        $this->put(3, 1, 'No. '.$standard->code, self::YELLOW, 16);
        $this->merge(3, 2, 3, 4);
        $this->paint(3, 2, 3, 7);
        $this->put(3, 8, 'Rev.No.', self::YELLOW);
        $this->put(3, 9, (string) $standard->revision, self::VALUE);
        $this->paint(3, 10, 3, 11);

        $this->put(4, 2, (string) $standard->supplier_name, self::VALUE, 18);
        $this->merge(4, 2, 4, 4);
        $this->put(4, 5, (string) $standard->supplier_code, self::VALUE);
        $this->merge(4, 5, 4, 7);
        $this->paint(4, 1, 4, 1);
        $this->put(4, 8, 'Rev.Date', self::YELLOW);
        $this->put(4, 9, $date, self::VALUE);
        $this->put(4, 10, 'Model', self::YELLOW);
        $this->put(4, 11, (string) $standard->title, self::VALUE);

        $this->put(5, 2, 'Part Name', self::YELLOW, 21);
        $this->merge(5, 2, 5, 4);
        $this->put(5, 5, 'Part No.', self::YELLOW);
        $this->merge(5, 5, 5, 7);
        $this->paint(5, 1, 5, 1);
        $this->put(5, 8, 'Drawing No. '.$standard->drawing_no, self::YELLOW);
        $this->merge(5, 8, 5, 11);

        $this->put(6, 2, $part->name, self::VALUE, 21);
        $this->merge(6, 2, 6, 4);
        $this->put(6, 5, $part->part_number, self::VALUE);
        $this->merge(6, 5, 6, 7);
        $this->paint(6, 1, 6, 1);
        $this->put(6, 8, 'Drawing Rev.No. '.$standard->drawing_rev, self::YELLOW);
        $this->merge(6, 8, 6, 11);

        $this->put(7, 1, 'S.No.', self::YELLOW, 16);
        $this->merge(7, 1, 8, 1);
        $this->put(7, 2, 'Inspection Item', self::YELLOW);
        $this->merge(7, 2, 8, 3);
        $this->put(7, 4, 'Criteria', self::YELLOW);
        $this->merge(7, 4, 8, 5);
        $this->put(7, 6, 'Inspection', self::YELLOW);
        $this->put(7, 7, 'Inspection', self::YELLOW);
        $this->put(7, 8, 'Sample', self::YELLOW);
        $this->put(7, 9, 'Inspection', self::YELLOW);
        $this->put(7, 10, 'CHARC. CLASS', self::YELLOW);
        $this->merge(7, 10, 8, 10);
        $this->put(7, 11, 'Remarks', self::YELLOW);
        $this->merge(7, 11, 8, 11);
        $this->put(8, 6, 'Method', self::YELLOW, 15);
        $this->put(8, 7, 'Tool', self::YELLOW);
        $this->put(8, 8, 'Plan', self::YELLOW);
        $this->put(8, 9, 'Stage', self::YELLOW);

        $row = 9;

        foreach ($standard->characteristics as $line) {
            $text = (string) $line->specification;
            $height = strlen($text) > 80 ? 72.0 : (strlen($text) > 40 ? 48.0 : 30.0);
            $this->put($row, 1, (string) $line->sequence, self::DATA, $height);
            $this->put($row, 2, (string) $line->name, self::DATA);
            $this->merge($row, 2, $row, 3);
            $this->put($row, 4, $text, self::DATA);
            $this->merge($row, 4, $row, 5);
            $this->put($row, 6, (string) $line->method, self::DATA);
            $this->put($row, 7, (string) $line->instrument, self::DATA);
            $this->put($row, 8, (string) $line->sample_plan, self::DATA);
            $this->put($row, 9, (string) $line->inspection_stage, self::DATA);
            $this->put($row, 10, $line->classLabel(), self::DATA);
            $this->put($row, 11, (string) $line->remarks, self::DATA);
            $row++;
        }

        $this->put($row, 1, 'General Tolerance', self::YELLOW, 18);
        $this->merge($row, 1, $row, 11);
        $row++;

        $bands = [
            ['Above', ['0.5', '3', '6', '30', '120', '315', '1000'], 'Approved By', (string) $standard->approved_by],
            ['Up to and including', ['3', '6', '30', '120', '315', '1000', '2000'], 'Checked By', (string) $standard->checked_by],
            ['Tolerance', ['± 0.1', '± 0.1', '± 0.2', '± 0.3', '± 0.5', '± 0.8', '± 1.2'], 'Prepared By', (string) $standard->prepared_by],
        ];

        foreach ($bands as [$label, $values, $sign, $name]) {
            $this->put($row, 1, $label, self::YELLOW, $label === 'Up to and including' ? 27 : 20);
            foreach ($values as $index => $value) {
                $this->put($row, $index + 2, $value, self::VALUE);
            }
            $this->put($row, 9, $sign, self::YELLOW);
            $this->put($row, 10, $name, self::VALUE);
            $this->merge($row, 10, $row, 11);
            $row++;
        }

        $legends = [
            'V.C = Vernier Caliper    MIC Met = Micro Meter    TPG = Thread Plug Gauge    V.H.G = Vernier Height Gauge    TRG = Thread Ring Gauge',
            'H.T = Hardness Tester    D.T.G = Dial Thickness Gauge',
            'Category:    Critical    Major    Minor    Others',
        ];

        foreach ($legends as $index => $legend) {
            $this->put($row, 1, $legend, self::LEGEND, $index === 2 ? 22 : 16);
            $this->merge($row, 1, $row, 11);
            $row++;
        }

        return $this->xml();
    }

    private function put(int $row, int $column, string $value, int $style, ?float $height = null): void
    {
        $this->rows[$row]['cells'][$column] = ['value' => $value, 'style' => $style];

        if ($height !== null) {
            $this->rows[$row]['height'] = $height;
        }
    }

    private function paint(int $row, int $from, int $endRow, int $to): void
    {
        for ($r = $row; $r <= $endRow; $r++) {
            for ($c = $from; $c <= $to; $c++) {
                $this->rows[$r]['cells'][$c] ??= ['value' => '', 'style' => self::YELLOW];
            }
        }
    }

    private function merge(int $row, int $from, int $endRow, int $to): void
    {
        $style = $this->rows[$row]['cells'][$from]['style'] ?? self::YELLOW;

        for ($r = $row; $r <= $endRow; $r++) {
            for ($c = $from; $c <= $to; $c++) {
                $this->rows[$r]['cells'][$c] ??= ['value' => '', 'style' => $style];
            }
        }

        $this->merges[] = self::column($from).$row.':'.self::column($to).$endRow;
    }

    private function xml(): string
    {
        $last = max(array_keys($this->rows));
        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            .'<sheetPr><pageSetUpPr fitToPage="1"/></sheetPr>'
            .'<dimension ref="A1:K'.$last.'"/>'
            .'<sheetViews><sheetView workbookViewId="0"/></sheetViews>'
            .'<sheetFormatPr defaultRowHeight="15"/>'
            .'<cols>';

        foreach (self::WIDTHS as $index => $width) {
            $column = $index + 1;
            $xml .= '<col min="'.$column.'" max="'.$column.'" width="'.$width.'" customWidth="1"/>';
        }

        $xml .= '</cols><sheetData>';
        ksort($this->rows);

        foreach ($this->rows as $number => $row) {
            $height = $row['height'] ?? 15;
            $xml .= '<row r="'.$number.'" ht="'.$height.'" customHeight="1">';
            $cells = $row['cells'] ?? [];
            ksort($cells);

            foreach ($cells as $column => $cell) {
                $ref = self::column($column).$number;
                $text = htmlspecialchars($cell['value'], ENT_XML1 | ENT_QUOTES, 'UTF-8');
                $xml .= '<c r="'.$ref.'" s="'.$cell['style'].'" t="inlineStr"><is><t xml:space="preserve">'.$text.'</t></is></c>';
            }

            $xml .= '</row>';
        }

        $xml .= '</sheetData><mergeCells count="'.count($this->merges).'">';

        foreach ($this->merges as $merge) {
            $xml .= '<mergeCell ref="'.$merge.'"/>';
        }

        return $xml.'</mergeCells>'
            .'<pageMargins left="0.25" right="0.25" top="0.4" bottom="0.4" header="0.2" footer="0.2"/>'
            .'<pageSetup orientation="landscape" paperSize="9" fitToWidth="1" fitToHeight="1"/>'
            .'</worksheet>';
    }

    private static function column(int $index): string
    {
        $name = '';

        while ($index > 0) {
            $index--;
            $name = chr(65 + ($index % 26)).$name;
            $index = intdiv($index, 26);
        }

        return $name;
    }

    private static function contentTypes(): string
    {
        return <<<'XML'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml" ContentType="application/xml"/>
  <Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>
  <Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>
  <Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>
</Types>
XML;
    }

    private static function rootRels(): string
    {
        return <<<'XML'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>
</Relationships>
XML;
    }

    private static function workbook(): string
    {
        return <<<'XML'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
  <sheets>
    <sheet name="Inspection Standard" sheetId="1" r:id="rId1"/>
  </sheets>
</workbook>
XML;
    }

    private static function workbookRels(): string
    {
        return <<<'XML'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>
  <Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>
</Relationships>
XML;
    }

    private static function styles(): string
    {
        return <<<'XML'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">
  <fonts count="3">
    <font><sz val="10"/><name val="Calibri"/></font>
    <font><b/><sz val="10"/><name val="Calibri"/></font>
    <font><b/><sz val="16"/><name val="Calibri"/></font>
  </fonts>
  <fills count="3">
    <fill><patternFill patternType="none"/></fill>
    <fill><patternFill patternType="gray125"/></fill>
    <fill><patternFill patternType="solid"><fgColor rgb="FFF4F4F5"/></patternFill></fill>
  </fills>
  <borders count="2">
    <border><left/><right/><top/><bottom/><diagonal/></border>
    <border>
      <left style="thin"><color auto="1"/></left>
      <right style="thin"><color auto="1"/></right>
      <top style="thin"><color auto="1"/></top>
      <bottom style="thin"><color auto="1"/></bottom>
      <diagonal/>
    </border>
  </borders>
  <cellStyleXfs count="1">
    <xf numFmtId="0" fontId="0" fillId="0" borderId="0"/>
  </cellStyleXfs>
  <cellXfs count="6">
    <xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>
    <xf numFmtId="0" fontId="1" fillId="2" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center" wrapText="1"/></xf>
    <xf numFmtId="0" fontId="0" fillId="2" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center" wrapText="1"/></xf>
    <xf numFmtId="0" fontId="2" fillId="2" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf>
    <xf numFmtId="0" fontId="0" fillId="0" borderId="1" xfId="0" applyFont="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center" wrapText="1"/></xf>
    <xf numFmtId="0" fontId="1" fillId="2" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center" wrapText="1"/></xf>
  </cellXfs>
</styleSheet>
XML;
    }
}
