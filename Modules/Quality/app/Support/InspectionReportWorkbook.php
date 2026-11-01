<?php

namespace Modules\Quality\Support;

use Modules\Quality\Models\InspectionCharacteristic;
use Modules\Quality\Models\InspectionReport;
use Modules\Quality\Models\InspectionResult;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use ZipArchive;

class InspectionReportWorkbook
{
    private const LABEL = 1;

    private const VALUE = 2;

    private const TITLE = 3;

    private const TEXT = 4;

    private const OK = 5;

    private const NG = 6;

    /**
     * @var array<int, array{height: float, cells: array<int, array{value: string, style: int}>}>
     */
    private array $rows = [];

    /** @var list<string> */
    private array $merges = [];

    private int $lastColumn = 1;

    public static function download(InspectionReport $report, string $filename): BinaryFileResponse
    {
        $tmp = tempnam(sys_get_temp_dir(), 'xlsx');
        $zip = new ZipArchive;
        $zip->open($tmp, ZipArchive::OVERWRITE);
        $zip->addFromString('[Content_Types].xml', self::contentTypes());
        $zip->addFromString('_rels/.rels', self::rootRels());
        $zip->addFromString('xl/workbook.xml', self::workbook());
        $zip->addFromString('xl/_rels/workbook.xml.rels', self::workbookRels());
        $zip->addFromString('xl/styles.xml', self::styles());
        $zip->addFromString('xl/worksheets/sheet1.xml', (new self)->sheet($report));
        $zip->close();

        return response()->download($tmp, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend(true);
    }

    private function sheet(InspectionReport $report): string
    {
        $samples = max(1, (int) $report->samples->max('sample_size'), (int) $report->sample_size);
        $supplierStart = 6;
        $supplierStatus = 5 + $samples + 1;
        $umacStart = $supplierStatus + 1;
        $umacStatus = $umacStart + $samples - 1 + 1;
        $remarks = $umacStatus + 1;
        $this->lastColumn = $report->samples->isNotEmpty() ? max($remarks, 11) : $remarks;
        $headerEnd = 10;

        $this->put(1, 1, 'INCOMING INSPECTION REPORT', self::TITLE, 24);
        $this->merge(1, 1, 1, $headerEnd);

        $this->put(2, 1, 'DOC. NO. F/QA/02    DOC. DATE 01.01.16    REV. NO/DATE 01/23.03.21    '.$report->report_number, self::LABEL, 18);
        $this->merge(2, 1, 2, $headerEnd);

        $this->pair(4, 'Material / PART NAME', (string) ($report->lot?->part?->name ?? ''), 'BILL No.', (string) ($report->bill_no ?? ''));
        $this->pair(5, 'Material / Part Code', (string) ($report->lot?->part?->part_number ?? ''), 'DATE', $report->inspected_at?->format('d M Y H:i') ?? '');
        $this->pair(6, 'Batch', (string) ($report->lot?->lot_number ?? ''), 'SUPPLIER', (string) ($report->lot?->vendor?->name ?? ''));
        $this->pair(7, 'ASSY. NAME', (string) ($report->assembly_name ?? ''), 'SUPPLIER Code', (string) ($report->lot?->vendor?->code ?? ''));
        $sample = (string) $report->sample_size;
        if ($report->samples->isNotEmpty()) {
            $sample .= ' · '.Ml08SampleQuote::summary(Ml08SampleQuote::fromSnapshot($report->samples));
        } elseif ($report->samplingPlan) {
            $sample .= ' · AQL '.$report->samplingPlan->aqlLabel();
        }
        $this->pair(8, 'PROCESS', (string) ($report->process ?? ''), 'SAMPLE SIZE', $sample);
        $this->pair(9, 'LOT SIZE', Qty::format($report->inspected_qty), 'QTY. REJECTED', Qty::format($report->rejected_qty));
        $standard = trim(($report->standard?->code ?? '').' '.($report->standard?->title ?? ''));
        $this->pair(10, 'QTY. ACCEPTED', Qty::format($report->accepted_qty), 'Standard', $standard);

        $grid = 12;

        if ($report->samples->isNotEmpty()) {
            $this->put($grid, 1, 'CLASS', self::LABEL, 18);
            $this->put($grid, 2, 'AQL', self::LABEL);
            $this->put($grid, 3, 'SAMPLE', self::LABEL);
            $this->put($grid, 4, 'RATE', self::LABEL);
            $this->put($grid, 5, 'ACCEPT', self::LABEL);
            $this->put($grid, 6, 'SUPPLIER DEFECTIVES', self::LABEL);
            $this->put($grid, 7, 'SUPPLIER %', self::LABEL);
            $this->put($grid, 8, 'SUPPLIER RESULT', self::LABEL);
            $this->put($grid, 9, 'UMAC DEFECTIVES', self::LABEL);
            $this->put($grid, 10, 'UMAC %', self::LABEL);
            $this->put($grid, 11, 'UMAC RESULT', self::LABEL);
            $grid++;

            foreach ($report->samples as $classSample) {
                $this->put($grid, 1, $classSample->classLabel(), self::VALUE, 18);
                $this->put($grid, 2, $classSample->aqlLabel(), self::VALUE);
                $this->put($grid, 3, (string) $classSample->sample_size, self::VALUE);
                $this->put($grid, 4, $classSample->rateLabel(), self::VALUE);
                $this->put($grid, 5, (string) $classSample->accept_number, self::VALUE);
                $this->put($grid, 6, (string) $classSample->supplier_defectives, self::VALUE);
                $this->put($grid, 7, $classSample->percentLabel('supplier'), self::VALUE);
                $this->put($grid, 8, $classSample->verdictLabel('supplier'), $classSample->supplier_result === 'reject' ? self::NG : self::OK);
                $this->put($grid, 9, (string) $classSample->umac_defectives, self::VALUE);
                $this->put($grid, 10, $classSample->percentLabel('umac'), self::VALUE);
                $this->put($grid, 11, $classSample->verdictLabel('umac'), $classSample->umac_result === 'reject' ? self::NG : self::OK);
                $grid++;
            }

            $grid++;
        }

        $head = $grid;
        $sub = $grid + 1;
        $this->put($head, 1, 'S.No.', self::LABEL, 22);
        $this->merge($head, 1, $sub, 1);
        $this->put($head, 2, 'DESCRIPTION', self::LABEL);
        $this->merge($head, 2, $sub, 2);
        $this->put($head, 3, 'CHARC. CLASS', self::LABEL);
        $this->merge($head, 3, $sub, 3);
        $this->put($head, 4, 'SPEC.', self::LABEL);
        $this->merge($head, 4, $sub, 4);
        $this->put($head, 5, 'INSP. METHOD', self::LABEL);
        $this->merge($head, 5, $sub, 5);
        $this->put($head, $supplierStart, 'SUPPLIER OBSERVATION', self::LABEL);
        $this->merge($head, $supplierStart, $head, $supplierStatus);
        $this->put($head, $umacStart, 'UMAC OBSERVATION', self::LABEL);
        $this->merge($head, $umacStart, $head, $umacStatus);
        $this->put($head, $remarks, 'REMARKS', self::LABEL);
        $this->merge($head, $remarks, $sub, $remarks);

        for ($sampleIndex = 1; $sampleIndex <= $samples; $sampleIndex++) {
            $this->put($sub, $supplierStart + $sampleIndex - 1, (string) $sampleIndex, self::LABEL);
            $this->put($sub, $umacStart + $sampleIndex - 1, (string) $sampleIndex, self::LABEL);
        }

        $this->put($sub, $supplierStatus, 'STATUS (OK/NOT OK)', self::LABEL);
        $this->put($sub, $umacStatus, 'STATUS (OK/NOT OK)', self::LABEL);

        $row = $sub + 1;
        foreach ($report->results as $index => $result) {
            $this->put($row, 1, (string) ($index + 1), self::VALUE, 18);
            $this->put($row, 2, (string) $result->characteristic_name, self::TEXT);
            $this->put($row, 3, InspectionCharacteristic::CLASSES[$result->characteristic_class] ?? (string) $result->characteristic_class, self::VALUE);
            $this->put($row, 4, (string) $result->specification, self::TEXT);
            $this->put($row, 5, (string) $result->method, self::TEXT);

            $limit = $report->sampleSizeForClass($result->characteristic_class);

            foreach ($this->readings($result, 'supplier', $samples) as $offset => $value) {
                $inSample = $offset < $limit;
                $this->put($row, $supplierStart + $offset, $inSample ? $value : '', $inSample ? $this->readingStyle($result, $value) : self::VALUE);
            }

            $supplierStatusValue = $this->sideStatus($result, 'supplier', $samples);
            $this->put($row, $supplierStatus, InspectionReadings::label($supplierStatusValue), $this->statusStyle($supplierStatusValue));

            foreach ($this->readings($result, 'umac', $samples) as $offset => $value) {
                $inSample = $offset < $limit;
                $this->put($row, $umacStart + $offset, $inSample ? $value : '', $inSample ? $this->readingStyle($result, $value) : self::VALUE);
            }

            $umacStatusValue = $this->sideStatus($result, 'umac', $samples);
            $this->put($row, $umacStatus, InspectionReadings::label($umacStatusValue), $this->statusStyle($umacStatusValue));
            $this->put($row, $remarks, (string) ($result->remarks ?? ''), self::TEXT);
            $row++;
        }

        $row++;
        $supplierOverall = $this->overall($report, 'supplier', $samples);
        $umacOverall = $this->overall($report, 'umac', $samples);
        $this->pair($row, 'SUPPLIER STATUS', InspectionReadings::label($supplierOverall), 'UMAC STATUS', InspectionReadings::label($umacOverall), $this->statusStyle($supplierOverall), $this->statusStyle($umacOverall));

        $row++;
        $this->pair($row, 'DECISION', $this->decision($report->supplier_decision), 'DECISION', $this->decision($report->umac_decision));

        $row++;
        $this->put($row, 1, 'INSPECTED BY', self::LABEL, 22);
        $this->merge($row, 1, $row, 2);
        $this->put($row, 3, (string) ($report->inspector?->full_name ?? ''), self::VALUE);
        $this->merge($row, 3, $row, $headerEnd);

        $row++;
        $this->put($row, 1, 'VERIFIED BY', self::LABEL, 22);
        $this->merge($row, 1, $row, 2);
        $this->put($row, 3, (string) ($report->verifier?->full_name ?? ''), self::VALUE);
        $this->merge($row, 3, $row, $headerEnd);

        if (filled($report->remarks)) {
            $row += 2;
            $this->put($row, 1, 'REMARKS', self::LABEL, 22);
            $this->merge($row, 1, $row, 2);
            $this->put($row, 3, (string) $report->remarks, self::TEXT);
            $this->merge($row, 3, $row, $headerEnd);
        }

        return $this->xml();
    }

    private function pair(int $row, string $leftLabel, string $leftValue, string $rightLabel, string $rightValue, int $leftStyle = self::VALUE, int $rightStyle = self::VALUE): void
    {
        $this->put($row, 1, $leftLabel, self::LABEL, 22);
        $this->merge($row, 1, $row, 2);
        $this->put($row, 3, $leftValue, $leftStyle);
        $this->merge($row, 3, $row, 5);
        $this->put($row, 6, $rightLabel, self::LABEL);
        $this->merge($row, 6, $row, 7);
        $this->put($row, 8, $rightValue, $rightStyle);
        $this->merge($row, 8, $row, 10);
    }

    /**
     * @return list<string>
     */
    private function readings(InspectionResult $result, string $side, int $samples): array
    {
        $values = $side === 'supplier' ? $result->supplier_readings : $result->umac_readings;

        return InspectionReadings::pad(is_array($values) ? $values : [], $samples);
    }

    private function sideStatus(InspectionResult $result, string $side, int $samples): string
    {
        $limits = $this->limits($result);
        $manual = ! InspectionReadings::hasLimits($limits['min'], $limits['max']);

        if ($manual) {
            return InspectionReadings::manualStatus($side === 'supplier' ? $result->supplier_result : $result->umac_result);
        }

        return InspectionReadings::status($this->readings($result, $side, $samples), $limits['min'], $limits['max']);
    }

    private function overall(InspectionReport $report, string $side, int $samples): string
    {
        $statuses = [];

        foreach ($report->results as $result) {
            $statuses[] = $this->sideStatus($result, $side, $samples);
        }

        return InspectionReadings::rollup($statuses);
    }

    /**
     * @return array{min: ?float, max: ?float}
     */
    private function limits(InspectionResult $result): array
    {
        return InspectionReadings::limits(
            $result->characteristic?->tolerance_min,
            $result->characteristic?->tolerance_max,
            $result->specification,
        );
    }

    private function readingStyle(InspectionResult $result, string $value): int
    {
        $limits = $this->limits($result);

        return match (InspectionReadings::state($value, $limits['min'], $limits['max'])) {
            'ok' => self::OK,
            'ng' => self::NG,
            default => self::VALUE,
        };
    }

    private function statusStyle(string $status): int
    {
        return match ($status) {
            'ok' => self::OK,
            'ng' => self::NG,
            default => self::VALUE,
        };
    }

    private function decision(?string $decision): string
    {
        return match ($decision) {
            'non_conforming' => 'NON CONFORMING',
            'aud' => 'A.U.D.',
            'conforming' => 'CONFORMING',
            default => '',
        };
    }

    private function put(int $row, int $column, string $value, int $style, ?float $height = null): void
    {
        $this->rows[$row]['cells'][$column] = ['value' => $value, 'style' => $style];

        if ($height !== null) {
            $this->rows[$row]['height'] = $height;
        }
    }

    private function merge(int $row, int $from, int $endRow, int $to): void
    {
        if ($from > $to || $row > $endRow) {
            return;
        }

        $style = $this->rows[$row]['cells'][$from]['style'] ?? self::VALUE;

        for ($r = $row; $r <= $endRow; $r++) {
            for ($c = $from; $c <= $to; $c++) {
                $this->rows[$r]['cells'][$c] ??= ['value' => '', 'style' => $style];
            }
        }

        if ($from === $to && $row === $endRow) {
            return;
        }

        $this->merges[] = self::column($from).$row.':'.self::column($to).$endRow;
    }

    private function xml(): string
    {
        $lastRow = max(array_keys($this->rows));
        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            .'<sheetPr><pageSetUpPr fitToPage="1"/></sheetPr>'
            .'<dimension ref="A1:'.self::column($this->lastColumn).$lastRow.'"/>'
            .'<sheetViews><sheetView workbookViewId="0"/></sheetViews>'
            .'<sheetFormatPr defaultRowHeight="15"/>'
            .'<cols>';

        for ($column = 1; $column <= $this->lastColumn; $column++) {
            $width = match (true) {
                $column === 2 => 22,
                $column === 4, $column === 5 => 16,
                $column === 3 => 14,
                default => 8,
            };
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

        $xml .= '</sheetData>';

        if ($this->merges !== []) {
            $xml .= '<mergeCells count="'.count($this->merges).'">';

            foreach ($this->merges as $merge) {
                $xml .= '<mergeCell ref="'.$merge.'"/>';
            }

            $xml .= '</mergeCells>';
        }

        return $xml
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
    <sheet name="Inspection Report" sheetId="1" r:id="rId1"/>
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
  <fonts count="4">
    <font><sz val="10"/><name val="Calibri"/></font>
    <font><b/><sz val="10"/><name val="Calibri"/></font>
    <font><b/><sz val="16"/><name val="Calibri"/></font>
    <font><b/><sz val="10"/><color rgb="FFFFFFFF"/><name val="Calibri"/></font>
  </fonts>
  <fills count="5">
    <fill><patternFill patternType="none"/></fill>
    <fill><patternFill patternType="gray125"/></fill>
    <fill><patternFill patternType="solid"><fgColor rgb="FFF4F4F5"/></patternFill></fill>
    <fill><patternFill patternType="solid"><fgColor rgb="FF16A34A"/></patternFill></fill>
    <fill><patternFill patternType="solid"><fgColor rgb="FFDC2626"/></patternFill></fill>
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
  <cellXfs count="7">
    <xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>
    <xf numFmtId="0" fontId="1" fillId="2" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center" wrapText="1"/></xf>
    <xf numFmtId="0" fontId="0" fillId="0" borderId="1" xfId="0" applyFont="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center" wrapText="1"/></xf>
    <xf numFmtId="0" fontId="2" fillId="2" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf>
    <xf numFmtId="0" fontId="0" fillId="0" borderId="1" xfId="0" applyFont="1" applyBorder="1" applyAlignment="1"><alignment horizontal="left" vertical="center" wrapText="1"/></xf>
    <xf numFmtId="0" fontId="3" fillId="3" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center" wrapText="1"/></xf>
    <xf numFmtId="0" fontId="3" fillId="4" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center" wrapText="1"/></xf>
  </cellXfs>
</styleSheet>
XML;
    }
}
