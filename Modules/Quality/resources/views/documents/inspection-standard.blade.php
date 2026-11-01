<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $part->part_number }} · Inspection Standard</title>
    <style>
        @page { margin: 8mm 6mm; }
        * { box-sizing: border-box; }
        body { margin: 0; font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #111; }
        table { width: 100%; border-collapse: collapse; table-layout: fixed; }
        th, td { border: 1px solid #000; padding: 5px 6px; vertical-align: middle; }
        .y { background: #f4f4f5; font-weight: bold; text-align: center; }
        .v { background: #f4f4f5; text-align: center; font-weight: normal; }
        .title { background: #f4f4f5; font-size: 16px; font-weight: bold; text-align: center; padding: 10px; }
        .d { text-align: center; font-weight: normal; }
        .legend { background: #f4f4f5; font-weight: bold; text-align: center; font-size: 9px; padding: 6px; }
        .left { text-align: left; }
    </style>
</head>
<body>
    <table>
        <colgroup>
            <col style="width: 7.5%">
            <col style="width: 10.5%">
            <col style="width: 7.5%">
            <col style="width: 6%">
            <col style="width: 10.5%">
            <col style="width: 11.5%">
            <col style="width: 9.5%">
            <col style="width: 9.5%">
            <col style="width: 8.5%">
            <col style="width: 10%">
            <col style="width: 9%">
        </colgroup>
        <tr>
            <th class="title" colspan="11">Inspection Standard - Parts</th>
        </tr>
        <tr>
            <th class="y">UMAC-IIS</th>
            <th class="y" colspan="3">Supplier's Name</th>
            <th class="y" colspan="3">Supplier's Code</th>
            <th class="y">Doc.No.</th>
            <td class="v">{{ $standard->doc_no }}</td>
            <th class="y">Page</th>
            <td class="v">{{ $standard->page_label }}</td>
        </tr>
        <tr>
            <th class="y">No. {{ $standard->code }}</th>
            <td class="v" colspan="3"></td>
            <td class="v" colspan="3"></td>
            <th class="y">Rev.No.</th>
            <td class="v">{{ $standard->revision }}</td>
            <td class="v" colspan="2"></td>
        </tr>
        <tr>
            <td class="v"></td>
            <td class="v" colspan="3">{{ $standard->supplier_name }}</td>
            <td class="v" colspan="3">{{ $standard->supplier_code }}</td>
            <th class="y">Rev.Date</th>
            <td class="v">{{ $standard->effective_from?->format('d.m.Y') }}</td>
            <th class="y">Model</th>
            <td class="v">{{ $standard->title }}</td>
        </tr>
        <tr>
            <td class="v"></td>
            <th class="y" colspan="3">Part Name</th>
            <th class="y" colspan="3">Part No.</th>
            <th class="y left" colspan="4">Drawing No. {{ $standard->drawing_no }}</th>
        </tr>
        <tr>
            <td class="v"></td>
            <td class="v" colspan="3">{{ $part->name }}</td>
            <td class="v" colspan="3">{{ $part->part_number }}</td>
            <th class="y left" colspan="4">Drawing Rev.No. {{ $standard->drawing_rev }}</th>
        </tr>
        <tr>
            <th class="y" rowspan="2">S.No.</th>
            <th class="y" colspan="2" rowspan="2">Inspection Item</th>
            <th class="y" colspan="2" rowspan="2">Criteria</th>
            <th class="y">Inspection</th>
            <th class="y">Inspection</th>
            <th class="y">Sample</th>
            <th class="y">Inspection</th>
            <th class="y" rowspan="2">CHARC. CLASS</th>
            <th class="y" rowspan="2">Remarks</th>
        </tr>
        <tr>
            <th class="y">Method</th>
            <th class="y">Tool</th>
            <th class="y">Plan</th>
            <th class="y">Stage</th>
        </tr>
        @foreach ($standard->characteristics as $line)
            <tr>
                <td class="d">{{ $line->sequence }}</td>
                <td class="d" colspan="2">{{ $line->name }}</td>
                <td class="d" colspan="2">{{ $line->specification }}</td>
                <td class="d">{{ $line->method }}</td>
                <td class="d">{{ $line->instrument }}</td>
                <td class="d">{{ $line->sample_plan }}</td>
                <td class="d">{{ $line->inspection_stage }}</td>
                <td class="d">{{ $line->classLabel() }}</td>
                <td class="d">{{ $line->remarks }}</td>
            </tr>
        @endforeach
        <tr>
            <th class="y" colspan="11">General Tolerance</th>
        </tr>
        <tr>
            <th class="y">Above</th>
            @foreach (['0.5', '3', '6', '30', '120', '315', '1000'] as $band)
                <td class="v">{{ $band }}</td>
            @endforeach
            <th class="y">Approved By</th>
            <td class="v" colspan="2">{{ $standard->approved_by }}</td>
        </tr>
        <tr>
            <th class="y">Up to and including</th>
            @foreach (['3', '6', '30', '120', '315', '1000', '2000'] as $band)
                <td class="v">{{ $band }}</td>
            @endforeach
            <th class="y">Checked By</th>
            <td class="v" colspan="2">{{ $standard->checked_by }}</td>
        </tr>
        <tr>
            <th class="y">Tolerance</th>
            @foreach (['± 0.1', '± 0.1', '± 0.2', '± 0.3', '± 0.5', '± 0.8', '± 1.2'] as $band)
                <td class="v">{{ $band }}</td>
            @endforeach
            <th class="y">Prepared By</th>
            <td class="v" colspan="2">{{ $standard->prepared_by }}</td>
        </tr>
        <tr>
            <td class="legend" colspan="11">V.C = Vernier Caliper &nbsp;&nbsp; MIC Met = Micro Meter &nbsp;&nbsp; TPG = Thread Plug Gauge &nbsp;&nbsp; V.H.G = Vernier Height Gauge &nbsp;&nbsp; TRG = Thread Ring Gauge</td>
        </tr>
        <tr>
            <td class="legend" colspan="11">H.T = Hardness Tester &nbsp;&nbsp; D.T.G = Dial Thickness Gauge</td>
        </tr>
        <tr>
            <td class="legend" colspan="11">Category: &nbsp;&nbsp; Critical &nbsp;&nbsp; Major &nbsp;&nbsp; Minor &nbsp;&nbsp; Others</td>
        </tr>
    </table>
</body>
</html>
