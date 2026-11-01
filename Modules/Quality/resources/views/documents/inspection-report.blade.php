@php
    use Modules\Quality\Models\InspectionCharacteristic;
    use Modules\Quality\Support\InspectionReadings;
    use Modules\Quality\Support\Ml08SampleQuote;
    use Modules\Quality\Support\Qty;

    $samples = max(1, (int) $report->samples->max('sample_size'), (int) $report->sample_size);
    $sampleLimit = function ($result) use ($report): int {
        return $report->sampleSizeForClass($result->characteristic_class);
    };
    $limitsFor = function ($result): array {
        return InspectionReadings::limits(
            $result->characteristic?->tolerance_min,
            $result->characteristic?->tolerance_max,
            $result->specification,
        );
    };
    $readingsFor = function ($result, string $side) use ($samples): array {
        $values = $side === 'supplier' ? $result->supplier_readings : $result->umac_readings;

        return InspectionReadings::pad(is_array($values) ? $values : [], $samples);
    };
    $sideStatus = function ($result, string $side) use ($limitsFor, $readingsFor): string {
        $limits = $limitsFor($result);

        if (! InspectionReadings::hasLimits($limits['min'], $limits['max'])) {
            return InspectionReadings::manualStatus($side === 'supplier' ? $result->supplier_result : $result->umac_result);
        }

        return InspectionReadings::status($readingsFor($result, $side), $limits['min'], $limits['max']);
    };
    $overall = function (string $side) use ($report, $sideStatus): string {
        $statuses = [];

        foreach ($report->results as $result) {
            $statuses[] = $sideStatus($result, $side);
        }

        return InspectionReadings::rollup($statuses);
    };
    $decision = function (?string $value): string {
        return match ($value) {
            'non_conforming' => 'NON CONFORMING',
            'aud' => 'A.U.D.',
            'conforming' => 'CONFORMING',
            default => '—',
        };
    };
    $supplierOverall = $overall('supplier');
    $umacOverall = $overall('umac');
    $perPage = 10;
    $chunks = [];

    for ($start = 0; $start < $samples; $start += $perPage) {
        $chunks[] = [$start, min($perPage, $samples - $start)];
    }
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $report->report_number }} · Incoming Inspection Report</title>
    <style>
        @page { margin: 8mm 6mm; }
        * { box-sizing: border-box; }
        body { margin: 0; font-family: DejaVu Sans, sans-serif; font-size: 8px; color: #111; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #222; padding: 3px 4px; vertical-align: middle; }
        th { background: #f4f4f5; font-weight: bold; text-align: center; }
        .title { background: #f4f4f5; font-size: 14px; font-weight: bold; text-align: center; padding: 8px; }
        .meta { background: #f4f4f5; font-size: 8px; font-weight: bold; text-align: center; }
        .value { text-align: center; font-weight: bold; }
        .left { text-align: left; }
        .ok { background: #16a34a; color: #fff; font-weight: bold; text-align: center; }
        .ng { background: #dc2626; color: #fff; font-weight: bold; text-align: center; }
        .plain { text-align: center; }
        .status-cell { text-align: center; }
        .pill { display: inline-block; min-width: 42px; padding: 1px 4px; border-radius: 2px; font-weight: bold; }
        .images { margin: 8px 0; }
        .images img { height: 70px; width: auto; margin-right: 6px; }
        .gap { height: 8px; }
        .readings { table-layout: fixed; width: 100%; page-break-inside: auto; }
        .readings th, .readings td { font-size: 7px; padding: 2px 1px; word-wrap: break-word; }
        .cont { margin: 0 0 4px; font-size: 8px; font-weight: bold; }
        .break { page-break-before: always; }
    </style>
</head>
<body>
    <table>
        <tr>
            <th class="title" colspan="4">INCOMING INSPECTION REPORT</th>
        </tr>
        <tr>
            <td class="meta" colspan="4">DOC. NO. F/QA/02 &nbsp;&nbsp; DOC. DATE 01.01.16 &nbsp;&nbsp; REV. NO/DATE 01/23.03.21 &nbsp;&nbsp; {{ $report->report_number }}</td>
        </tr>
        <tr>
            <th>Material / PART NAME</th>
            <td class="value">{{ $report->lot?->part?->name }}</td>
            <th>BILL No.</th>
            <td class="value">{{ $report->bill_no ?: '—' }}</td>
        </tr>
        <tr>
            <th>Material / Part Code</th>
            <td class="value">{{ $report->lot?->part?->part_number }}</td>
            <th>DATE</th>
            <td class="value">{{ $report->inspected_at?->format('d M Y H:i') }}</td>
        </tr>
        <tr>
            <th>Batch</th>
            <td class="value">{{ $report->lot?->lot_number }}</td>
            <th>SUPPLIER</th>
            <td class="value">{{ $report->lot?->vendor?->name }}</td>
        </tr>
        <tr>
            <th>ASSY. NAME</th>
            <td class="value">{{ $report->assembly_name ?: '—' }}</td>
            <th>SUPPLIER Code</th>
            <td class="value">{{ $report->lot?->vendor?->code }}</td>
        </tr>
        <tr>
            <th>PROCESS</th>
            <td class="value">{{ $report->process ?: '—' }}</td>
            <th>SAMPLE SIZE</th>
            <td class="value">
                {{ $report->sample_size }}
                @if ($report->samples->isNotEmpty())
                    · {{ Ml08SampleQuote::summary(Ml08SampleQuote::fromSnapshot($report->samples)) }}
                @elseif ($report->samplingPlan)
                    · {{ __('AQL') }} {{ $report->samplingPlan->aqlLabel() }}
                @endif
            </td>
        </tr>
        <tr>
            <th>LOT SIZE</th>
            <td class="value">{{ Qty::format($report->inspected_qty) }}</td>
            <th>QTY. REJECTED</th>
            <td class="value">{{ Qty::format($report->rejected_qty) }}</td>
        </tr>
        <tr>
            <th>QTY. ACCEPTED</th>
            <td class="value">{{ Qty::format($report->accepted_qty) }}</td>
            <th>Standard</th>
            <td class="value">{{ $report->standard?->code }} · {{ $report->standard?->title }}</td>
        </tr>
    </table>

    @if ($images !== [])
        <div class="images">
            @foreach ($images as $image)
                <img src="{{ $image }}" alt="">
            @endforeach
        </div>
    @endif

    <div class="gap"></div>

    @if ($report->samples->isNotEmpty())
        <table>
            <thead>
                <tr>
                    <th>{{ __('Class') }}</th>
                    <th>{{ __('AQL') }}</th>
                    <th>{{ __('Sample') }}</th>
                    <th>{{ __('Rate') }}</th>
                    <th>{{ __('Accept') }}</th>
                    <th>{{ __('Supplier defectives') }}</th>
                    <th>{{ __('Supplier %') }}</th>
                    <th>{{ __('Supplier result') }}</th>
                    <th>{{ __('UMAC defectives') }}</th>
                    <th>{{ __('UMAC %') }}</th>
                    <th>{{ __('UMAC result') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($report->samples as $sample)
                    <tr>
                        <td class="plain">{{ $sample->classLabel() }}</td>
                        <td class="plain">{{ $sample->aqlLabel() }}</td>
                        <td class="plain">{{ $sample->sample_size }}</td>
                        <td class="plain">{{ $sample->rateLabel() }}</td>
                        <td class="plain">{{ $sample->accept_number }}</td>
                        <td class="plain">{{ $sample->supplier_defectives }}</td>
                        <td class="plain">{{ $sample->percentLabel('supplier') }}</td>
                        <td class="plain">{{ $sample->verdictLabel('supplier') }}</td>
                        <td class="plain">{{ $sample->umac_defectives }}</td>
                        <td class="plain">{{ $sample->percentLabel('umac') }}</td>
                        <td class="plain">{{ $sample->verdictLabel('umac') }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        <div class="gap"></div>
    @endif

    @foreach ($chunks as $chunkIndex => [$offset, $count])
        @if ($chunkIndex > 0)
            <div class="break"></div>
            <p class="cont">Samples {{ $offset + 1 }}–{{ $offset + $count }} of {{ $samples }}</p>
        @endif
        <table class="readings">
            <thead>
                <tr>
                    <th rowspan="2" style="width: 4%;">S.No.</th>
                    <th rowspan="2" style="width: 10%;">DESCRIPTION</th>
                    <th rowspan="2" style="width: 7%;">CHARC. CLASS</th>
                    <th rowspan="2" style="width: 10%;">SPEC.</th>
                    <th rowspan="2" style="width: 8%;">INSP. METHOD</th>
                    <th colspan="{{ $count + 1 }}">SUPPLIER OBSERVATION</th>
                    <th colspan="{{ $count + 1 }}">UMAC OBSERVATION</th>
                    <th rowspan="2" style="width: 8%;">REMARKS</th>
                </tr>
                <tr>
                    @for ($sample = $offset + 1; $sample <= $offset + $count; $sample++)
                        <th>{{ $sample }}</th>
                    @endfor
                    <th>STATUS</th>
                    @for ($sample = $offset + 1; $sample <= $offset + $count; $sample++)
                        <th>{{ $sample }}</th>
                    @endfor
                    <th>STATUS</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($report->results as $index => $result)
                    @php
                        $limits = $limitsFor($result);
                        $supplierReadings = array_slice($readingsFor($result, 'supplier'), $offset, $count);
                        $umacReadings = array_slice($readingsFor($result, 'umac'), $offset, $count);
                        $supplierStatus = $sideStatus($result, 'supplier');
                        $umacStatus = $sideStatus($result, 'umac');
                        $rowSamples = $sampleLimit($result);
                    @endphp
                    <tr>
                        <td class="plain">{{ $index + 1 }}</td>
                        <td class="left">{{ $result->characteristic_name }}</td>
                        <td class="plain">{{ InspectionCharacteristic::CLASSES[$result->characteristic_class] ?? $result->characteristic_class }}</td>
                        <td class="left">{{ $result->specification }}</td>
                        <td class="left">{{ $result->method }}</td>
                        @foreach ($supplierReadings as $piece => $value)
                            @php
                                $absolute = $offset + $piece;
                                $inSample = $absolute < $rowSamples;
                                $state = $inSample ? InspectionReadings::state($value, $limits['min'], $limits['max']) : 'empty';
                            @endphp
                            <td class="{{ $state === 'ok' || $state === 'ng' ? $state : 'plain' }}">{{ $inSample ? ($value !== '' ? $value : '—') : '' }}</td>
                        @endforeach
                        <td class="status-cell"><span class="pill {{ $supplierStatus === 'ok' || $supplierStatus === 'ng' ? $supplierStatus : '' }}">{{ InspectionReadings::label($supplierStatus) }}</span></td>
                        @foreach ($umacReadings as $piece => $value)
                            @php
                                $absolute = $offset + $piece;
                                $inSample = $absolute < $rowSamples;
                                $state = $inSample ? InspectionReadings::state($value, $limits['min'], $limits['max']) : 'empty';
                            @endphp
                            <td class="{{ $state === 'ok' || $state === 'ng' ? $state : 'plain' }}">{{ $inSample ? ($value !== '' ? $value : '—') : '' }}</td>
                        @endforeach
                        <td class="status-cell"><span class="pill {{ $umacStatus === 'ok' || $umacStatus === 'ng' ? $umacStatus : '' }}">{{ InspectionReadings::label($umacStatus) }}</span></td>
                        <td class="left">{{ $result->remarks ?: '—' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endforeach

    <div class="gap"></div>

    <table>
        <tr>
            <th>SUPPLIER STATUS</th>
            <td class="status-cell"><span class="pill {{ $supplierOverall === 'ok' || $supplierOverall === 'ng' ? $supplierOverall : '' }}">{{ InspectionReadings::label($supplierOverall) }}</span></td>
            <th>UMAC STATUS</th>
            <td class="status-cell"><span class="pill {{ $umacOverall === 'ok' || $umacOverall === 'ng' ? $umacOverall : '' }}">{{ InspectionReadings::label($umacOverall) }}</span></td>
        </tr>
        <tr>
            <th>DECISION</th>
            <td class="value">{{ $decision($report->supplier_decision) }}</td>
            <th>DECISION</th>
            <td class="value">{{ $decision($report->umac_decision) }}</td>
        </tr>
        <tr>
            <th>INSPECTED BY</th>
            <td class="value" colspan="3">{{ $report->inspector?->full_name ?: '—' }}</td>
        </tr>
        <tr>
            <th>VERIFIED BY</th>
            <td class="value" colspan="3">{{ $report->verifier?->full_name ?: '—' }}</td>
        </tr>
        @if ($report->remarks)
            <tr>
                <th>REMARKS</th>
                <td class="left" colspan="3">{{ $report->remarks }}</td>
            </tr>
        @endif
    </table>
</body>
</html>
