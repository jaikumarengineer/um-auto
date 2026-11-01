<?php

namespace Modules\Quality\Support;

use DOMDocument;
use DOMXPath;
use RuntimeException;
use ZipArchive;

class InspectionStandardSheet
{
    /**
     * Read an incoming inspection standard workbook.
     *
     * Yellow header and footer labels stay fixed in the app. This returns only
     * the typed values: document control, supplier, drawing, sign-off, and lines.
     *
     * @return array{
     *     code: ?string,
     *     supplier_name: ?string,
     *     supplier_code: ?string,
     *     doc_no: ?string,
     *     page_label: ?string,
     *     revision: ?string,
     *     effective_from: ?string,
     *     model: ?string,
     *     drawing_no: ?string,
     *     drawing_rev: ?string,
     *     approved_by: ?string,
     *     checked_by: ?string,
     *     prepared_by: ?string,
     *     lines: list<array{name: string, specification: string, method: string, instrument: string, sample_plan: string, inspection_stage: string, characteristic_class: string, remarks: string}>
     * }
     */
    public static function read(string $path): array
    {
        $grid = self::grid($path);
        $headerRow = self::headerRow($grid);

        if ($headerRow === null) {
            throw new RuntimeException('This workbook is not an incoming inspection standard.');
        }

        $columns = self::columns($grid, $headerRow);
        $dataStart = self::dataStart($grid, $headerRow);
        $found = self::headerFields($grid, $headerRow);

        $lines = [];
        $lastRow = max(array_keys($grid));

        for ($row = $dataStart; $row <= $lastRow && $row < $headerRow + 400; $row++) {
            if (self::isFooterRow($grid, $row)) {
                break;
            }

            $line = [
                'name' => self::clean(self::cell($grid, $row, $columns['item'])),
                'specification' => self::clean(self::cell($grid, $row, $columns['criteria'])),
                'method' => self::clean(self::cell($grid, $row, $columns['method'])),
                'instrument' => self::clean(self::cell($grid, $row, $columns['tool'])),
                'sample_plan' => self::clean(self::cell($grid, $row, $columns['sample'])),
                'inspection_stage' => self::clean(self::cell($grid, $row, $columns['stage'])),
                'characteristic_class' => self::normalizeClass(self::clean(self::cell($grid, $row, $columns['class']))),
                'remarks' => self::clean(self::cell($grid, $row, $columns['remarks'])),
            ];

            if ($line['name'] === '' && $line['specification'] === '') {
                continue;
            }

            $lines[] = $line;
        }

        $signOff = self::signOff($grid, $dataStart);

        if ($lines === [] && ($found['code'] ?? null) === null) {
            throw new RuntimeException('This workbook is not an incoming inspection standard.');
        }

        return [
            'code' => $found['code'] ?? null,
            'supplier_name' => $found['supplier_name'] ?? null,
            'supplier_code' => $found['supplier_code'] ?? null,
            'doc_no' => $found['doc_no'] ?? null,
            'page_label' => $found['page_label'] ?? null,
            'revision' => $found['revision'] ?? null,
            'effective_from' => $found['effective_from'] ?? null,
            'model' => $found['model'] ?? null,
            'drawing_no' => $found['drawing_no'] ?? null,
            'drawing_rev' => $found['drawing_rev'] ?? null,
            'approved_by' => $signOff['approved_by'] ?? null,
            'checked_by' => $signOff['checked_by'] ?? null,
            'prepared_by' => $signOff['prepared_by'] ?? null,
            'lines' => $lines,
        ];
    }

    /**
     * @return array<int, array<int, string>>
     */
    private static function grid(string $path): array
    {
        $zip = new ZipArchive;

        if ($zip->open($path) !== true) {
            throw new RuntimeException('The Excel file could not be opened. Save it as .xlsx and upload it again.');
        }

        $shared = self::sharedStrings($zip->getFromName('xl/sharedStrings.xml') ?: '');
        $sheetPath = self::sheetPath($zip);
        $sheet = $zip->getFromName($sheetPath);
        $zip->close();

        if (! is_string($sheet) || $sheet === '') {
            throw new RuntimeException('The Excel file has no worksheet.');
        }

        $xpath = self::xpath($sheet);
        $grid = [];

        foreach ($xpath->query('//*[local-name()="c"]') ?: [] as $cell) {
            $ref = $cell->getAttribute('r');

            if (! preg_match('/^([A-Z]+)(\d+)$/', $ref, $match)) {
                continue;
            }

            $column = self::columnIndex($match[1]);
            $row = (int) $match[2];
            $type = $cell->getAttribute('t');
            $value = '';

            if ($type === 'inlineStr') {
                foreach ($xpath->query('.//*[local-name()="t"]', $cell) ?: [] as $text) {
                    $value .= $text->textContent;
                }
            } else {
                $raw = $xpath->query('./*[local-name()="v"]', $cell)?->item(0)?->textContent ?? '';
                $value = $type === 's' ? ($shared[(int) $raw] ?? '') : $raw;
            }

            $value = self::plainNumber($value);

            if (trim($value) === '') {
                continue;
            }

            $grid[$row][$column] = $value;
        }

        if ($grid === []) {
            throw new RuntimeException('The Excel file has no readable cells.');
        }

        ksort($grid);

        return $grid;
    }

    /**
     * @return list<string>
     */
    private static function sharedStrings(string $xml): array
    {
        if ($xml === '') {
            return [];
        }

        $xpath = self::xpath($xml);
        $strings = [];

        foreach ($xpath->query('//*[local-name()="si"]') ?: [] as $item) {
            $text = '';

            foreach ($xpath->query('.//*[local-name()="t"]', $item) ?: [] as $node) {
                $text .= $node->textContent;
            }

            $strings[] = $text;
        }

        return $strings;
    }

    private static function sheetPath(ZipArchive $zip): string
    {
        $workbook = $zip->getFromName('xl/workbook.xml') ?: '';
        $rels = $zip->getFromName('xl/_rels/workbook.xml.rels') ?: '';
        $sheetId = null;

        if ($workbook !== '') {
            $xpath = self::xpath($workbook);

            foreach ($xpath->query('//*[local-name()="sheet"]') ?: [] as $sheet) {
                $name = strtolower($sheet->getAttribute('name'));
                $id = '';

                foreach ($sheet->attributes as $attribute) {
                    if ($attribute->localName === 'id') {
                        $id = $attribute->value;
                    }
                }

                if ($sheetId === null || str_contains($name, 'inspection') || str_contains($name, 'aclp')) {
                    $sheetId = $id !== '' ? $id : $sheetId;
                }

                if (str_contains($name, 'inspection')) {
                    break;
                }
            }
        }

        if ($rels !== '' && $sheetId) {
            $xpath = self::xpath($rels);

            foreach ($xpath->query('//*[local-name()="Relationship"]') ?: [] as $relationship) {
                if ($relationship->getAttribute('Id') !== $sheetId) {
                    continue;
                }

                $target = ltrim($relationship->getAttribute('Target'), '/');

                return str_starts_with($target, 'xl/') ? $target : 'xl/'.$target;
            }
        }

        return 'xl/worksheets/sheet1.xml';
    }

    private static function xpath(string $xml): DOMXPath
    {
        $document = new DOMDocument;
        $document->loadXML($xml, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);

        return new DOMXPath($document);
    }

    /**
     * @param  array<int, array<int, string>>  $grid
     */
    private static function headerRow(array $grid): ?int
    {
        foreach ($grid as $row => $cells) {
            foreach ($cells as $value) {
                $norm = self::norm($value);

                if ($norm === 's.no.' || $norm === 's.no' || $norm === 's no.') {
                    return $row;
                }
            }
        }

        return null;
    }

    /**
     * @param  array<int, array<int, string>>  $grid
     * @return array{item: int, criteria: int, method: int, tool: int, sample: int, stage: int, class: int, remarks: int}
     */
    private static function columns(array $grid, int $headerRow): array
    {
        $columns = [
            'item' => null,
            'criteria' => null,
            'method' => null,
            'tool' => null,
            'sample' => null,
            'stage' => null,
            'class' => null,
            'remarks' => null,
        ];

        $max = max(array_keys($grid[$headerRow] ?? [])) + 2;

        for ($column = 1; $column <= $max; $column++) {
            $text = self::norm(self::cell($grid, $headerRow, $column));
            $below = self::norm(self::cell($grid, $headerRow + 1, $column));

            if (str_contains($text, 'inspection item')) {
                $columns['item'] = $column;
            } elseif ($text === 'criteria') {
                $columns['criteria'] = $column;
            } elseif (str_contains($text, 'charc') || str_contains($text, 'characteristic class')) {
                $columns['class'] = $column;
            } elseif (str_contains($text, 'remark')) {
                $columns['remarks'] = $column;
            } elseif (($text === 'inspection' && $below === 'method') || str_contains($text, 'inspection method')) {
                $columns['method'] = $column;
            } elseif (($text === 'inspection' && $below === 'tool') || str_contains($text, 'inspection tool')) {
                $columns['tool'] = $column;
            } elseif (($text === 'sample' && $below === 'plan') || str_contains($text, 'sample plan')) {
                $columns['sample'] = $column;
            } elseif (($text === 'inspection' && $below === 'stage') || str_contains($text, 'inspection stage')) {
                $columns['stage'] = $column;
            }
        }

        if ($columns['item'] === null || $columns['criteria'] === null) {
            throw new RuntimeException('The inspection columns could not be found.');
        }

        return [
            'item' => $columns['item'],
            'criteria' => $columns['criteria'],
            'method' => $columns['method'] ?? $columns['criteria'] + 1,
            'tool' => $columns['tool'] ?? $columns['criteria'] + 2,
            'sample' => $columns['sample'] ?? $columns['criteria'] + 3,
            'stage' => $columns['stage'] ?? $columns['criteria'] + 4,
            'class' => $columns['class'] ?? ($columns['remarks'] ? $columns['remarks'] - 1 : $columns['criteria'] + 5),
            'remarks' => $columns['remarks'] ?? $columns['criteria'] + 6,
        ];
    }

    private static function normalizeClass(string $value): string
    {
        $value = trim($value);

        if ($value === '') {
            return 'C';
        }

        $norm = strtolower($value);

        return match (true) {
            in_array($norm, ['c', 'critical'], true) => 'C',
            in_array($norm, ['m', 'major'], true) => 'M',
            in_array($norm, ['mi', 'minor'], true) => 'Mi',
            in_array($norm, ['o', 'other', 'others', 's', 'significant'], true) => 'O',
            default => 'C',
        };
    }

    /**
     * @param  array<int, array<int, string>>  $grid
     */
    private static function dataStart(array $grid, int $headerRow): int
    {
        foreach ($grid[$headerRow + 1] ?? [] as $value) {
            $norm = self::norm($value);

            if (in_array($norm, ['method', 'tool', 'plan', 'stage'], true)) {
                return $headerRow + 2;
            }
        }

        return $headerRow + 1;
    }

    /**
     * @param  array<int, array<int, string>>  $grid
     * @return array<string, string>
     */
    private static function headerFields(array $grid, int $headerRow): array
    {
        $found = [];

        foreach ($grid as $row => $cells) {
            if ($row >= $headerRow) {
                break;
            }

            foreach ($cells as $column => $value) {
                $collapsed = self::collapsed($value);
                $norm = self::norm($collapsed);

                if (preg_match('/^drawing\s+rev\.?\s*no\.?\s*(.*)$/iu', $collapsed, $match)) {
                    $found['drawing_rev'] = trim($match[1]);

                    continue;
                }

                if (preg_match('/^drawing\s+no\.?\s*(.*)$/iu', $collapsed, $match)) {
                    $found['drawing_no'] = trim($match[1]);

                    continue;
                }

                if (preg_match('/^no\.?\s+([A-Za-z0-9][A-Za-z0-9._\/-]{0,40})$/iu', $collapsed, $match)) {
                    $found['code'] = $match[1];

                    continue;
                }

                $field = match ($norm) {
                    "supplier's name", 'suppliers name' => 'supplier_name',
                    "supplier's code", 'suppliers code' => 'supplier_code',
                    'doc.no.', 'doc. no.', 'doc no.' => 'doc_no',
                    'page' => 'page_label',
                    'rev.no.', 'rev. no.', 'rev no.' => 'revision',
                    'rev.date', 'rev. date', 'rev date' => 'effective_from',
                    'model' => 'model',
                    'no.', 'no' => 'code',
                    default => null,
                };

                if ($field === null || isset($found[$field])) {
                    continue;
                }

                $picked = in_array($field, ['supplier_name', 'supplier_code'], true)
                    ? (self::valueBelow($grid, $row, $column) ?? self::valueRight($grid, $row, $column))
                    : (self::valueRight($grid, $row, $column) ?? self::valueBelow($grid, $row, $column));

                if ($picked === null) {
                    continue;
                }

                $found[$field] = $field === 'effective_from'
                    ? (self::date($picked) ?? $picked)
                    : $picked;
            }
        }

        if (isset($found['effective_from'])) {
            $found['effective_from'] = self::date($found['effective_from']) ?? $found['effective_from'];
        }

        return $found;
    }

    /**
     * @param  array<int, array<int, string>>  $grid
     * @return array<string, string>
     */
    private static function signOff(array $grid, int $startRow): array
    {
        $found = [];
        $map = [
            'approved by' => 'approved_by',
            'checked by' => 'checked_by',
            'prepared by' => 'prepared_by',
        ];

        foreach ($grid as $row => $cells) {
            if ($row < $startRow) {
                continue;
            }

            foreach ($cells as $column => $value) {
                $field = $map[self::norm($value)] ?? null;

                if ($field === null || isset($found[$field])) {
                    continue;
                }

                $picked = self::valueRight($grid, $row, $column);

                if ($picked !== null) {
                    $found[$field] = $picked;
                }
            }
        }

        return $found;
    }

    /**
     * @param  array<int, array<int, string>>  $grid
     */
    private static function isFooterRow(array $grid, int $row): bool
    {
        foreach ($grid[$row] ?? [] as $value) {
            $norm = self::norm($value);

            if (
                str_contains($norm, 'general tolerance')
                || str_contains($norm, 'genernal tolerance')
                || str_starts_with($norm, 'reviision history')
                || str_starts_with($norm, 'revision history')
                || $norm === 'above'
                || str_starts_with($norm, 'v.c')
            ) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<int, array<int, string>>  $grid
     */
    private static function valueRight(array $grid, int $row, int $column): ?string
    {
        $value = self::clean(self::cell($grid, $row, $column + 1));

        return $value === '' || self::isLabel($value) ? null : $value;
    }

    /**
     * @param  array<int, array<int, string>>  $grid
     */
    private static function valueBelow(array $grid, int $row, int $column): ?string
    {
        for ($next = $row + 1; $next <= $row + 4; $next++) {
            $value = self::clean(self::cell($grid, $next, $column));

            if ($value === '') {
                continue;
            }

            if (self::isLabel($value)) {
                return null;
            }

            return $value;
        }

        return null;
    }

    /**
     * @param  array<int, array<int, string>>  $grid
     */
    private static function cell(array $grid, int $row, int $column): string
    {
        return $grid[$row][$column] ?? '';
    }

    private static function isLabel(string $value): bool
    {
        $norm = self::norm($value);

        return in_array($norm, [
            "supplier's name", 'suppliers name', "supplier's code", 'suppliers code',
            'doc.no.', 'doc. no.', 'page', 'rev.no.', 'rev. no.', 'rev.date', 'rev. date',
            'model', 'part name', 'part no.', 'part no', 's.no.', 's.no', 'inspection item',
            'criteria', 'umac-iis', 'approved by', 'checked by', 'prepared by', 'above',
            'up to and including', 'tolerance', 'rev. no', 'rev.date', 'description of changes',
            'no.', 'no',
        ], true)
            || str_starts_with($norm, 'drawing no')
            || str_starts_with($norm, 'drawing rev')
            || str_starts_with($norm, 'inspection standard')
            || str_contains($norm, 'general tolerance')
            || str_contains($norm, 'genernal tolerance');
    }

    private static function date(string $value): ?string
    {
        $value = trim($value);

        if (preg_match('/^(\d{1,2})[.\-\/](\d{1,2})[.\-\/](\d{4})$/', $value, $match)) {
            return sprintf('%04d-%02d-%02d', (int) $match[3], (int) $match[2], (int) $match[1]);
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return $value;
        }

        if (is_numeric($value) && (float) $value > 20000 && (float) $value < 80000) {
            $date = date_create('1899-12-30');

            if ($date === false) {
                return null;
            }

            $date->modify('+'.(int) $value.' days');

            return $date->format('Y-m-d');
        }

        return null;
    }

    private static function plainNumber(string $value): string
    {
        if (preg_match('/^-?\d+\.0+$/', trim($value))) {
            return (string) (int) $value;
        }

        return $value;
    }

    private static function clean(string $value): string
    {
        $value = str_replace(["\r\n", "\r"], "\n", $value);

        return trim($value);
    }

    private static function collapsed(string $value): string
    {
        return preg_replace('/\s+/', ' ', trim(str_replace(["\r\n", "\r", "\n"], ' ', $value))) ?? trim($value);
    }

    private static function norm(string $value): string
    {
        return strtolower(self::collapsed($value));
    }

    private static function columnIndex(string $letters): int
    {
        $index = 0;

        foreach (str_split($letters) as $letter) {
            $index = ($index * 26) + (ord($letter) - 64);
        }

        return $index;
    }
}
