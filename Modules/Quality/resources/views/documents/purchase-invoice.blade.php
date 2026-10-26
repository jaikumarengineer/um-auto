@php
    use Modules\Quality\Support\Qty;
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $invoice->invoice_number }} · Purchase invoice</title>
    <style>
        :root { color-scheme: light; }
        * { box-sizing: border-box; }
        body { margin: 0; font-family: Georgia, "Times New Roman", serif; color: #1c1917; background: #f5f5f4; }
        .toolbar { display: flex; justify-content: space-between; gap: 12px; max-width: 920px; margin: 0 auto; padding: 16px 20px 0; font-family: ui-sans-serif, system-ui, sans-serif; }
        .toolbar a, .toolbar button { border: 1px solid #d6d3d1; background: white; border-radius: 999px; padding: 8px 14px; text-decoration: none; color: #1c1917; cursor: pointer; font: inherit; }
        .sheet { max-width: 920px; margin: 16px auto 40px; background: white; padding: 40px 44px; box-shadow: 0 12px 40px rgba(0,0,0,.06); }
        .kicker { letter-spacing: .16em; text-transform: uppercase; font-size: 11px; color: #78716c; font-family: ui-sans-serif, system-ui, sans-serif; }
        h1 { margin: 6px 0 0; font-size: 32px; font-weight: 500; }
        .grid { display: grid; grid-template-columns: 1fr 1fr; gap: 24px; margin-top: 28px; }
        .label { font-family: ui-sans-serif, system-ui, sans-serif; font-size: 11px; letter-spacing: .08em; text-transform: uppercase; color: #78716c; }
        table { width: 100%; border-collapse: collapse; margin-top: 28px; font-family: ui-sans-serif, system-ui, sans-serif; font-size: 13px; }
        th { text-align: left; font-size: 11px; letter-spacing: .06em; text-transform: uppercase; color: #78716c; border-bottom: 1px solid #e7e5e4; padding: 8px 6px; }
        td { border-bottom: 1px solid #f5f5f4; padding: 10px 6px; vertical-align: top; }
        .num { text-align: right; font-variant-numeric: tabular-nums; }
        .warn { color: #b45309; }
        .note { margin-top: 28px; font-family: ui-sans-serif, system-ui, sans-serif; font-size: 12px; color: #57534e; }
        @media print {
            body { background: white; }
            .toolbar { display: none; }
            .sheet { box-shadow: none; margin: 0; max-width: none; }
        }
    </style>
</head>
<body>
    <div class="toolbar">
        <a href="{{ route('admin::quality.purchase.invoices') }}">Back to list</a>
        <button type="button" onclick="window.print()">Print</button>
    </div>
    <article class="sheet">
        <p class="kicker">{{ config('app.name') }} · Purchase invoice</p>
        <h1>{{ $invoice->invoice_number }}</h1>
        <p class="kicker" style="margin-top:8px;">Checked against {{ $invoice->purchaseOrder?->po_number }} · {{ $invoice->status }}</p>

        <div class="grid">
            <div>
                <p class="label">Vendor</p>
                <p style="margin:4px 0 0; font-size:18px;">{{ $invoice->vendor?->name }}</p>
                <p style="margin:4px 0 0; font-family: ui-sans-serif, system-ui, sans-serif; font-size:13px;">{{ $invoice->vendor?->code }}</p>
            </div>
            <div>
                <p class="label">References</p>
                <p style="margin:4px 0 0; font-family: ui-sans-serif, system-ui, sans-serif; font-size:14px;">
                    Invoice date {{ $invoice->invoice_date?->format('d M Y') }}<br>
                    Purchase order <a href="{{ route('admin::quality.purchase.orders.format', $invoice->purchaseOrder) }}">{{ $invoice->purchaseOrder?->po_number }}</a><br>
                    Printed {{ now()->format('d M Y H:i') }}
                </p>
            </div>
        </div>

        <table>
            <thead>
                <tr>
                    <th>Part</th>
                    <th class="num">PO qty</th>
                    <th class="num">Already billed</th>
                    <th class="num">This invoice</th>
                    <th class="num">Still open</th>
                    <th>Batches</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($invoice->lines as $line)
                    @php
                        $ordered = (float) ($line->purchaseOrderLine?->ordered_qty ?? 0);
                        $billed = $line->purchaseOrderLine?->invoicedQuantity() ?? 0;
                        $others = max(0, $billed - (float) $line->invoiced_qty);
                        $open = max(0, $ordered - $billed);
                        $over = $billed - $ordered > 0.0005;
                    @endphp
                    <tr>
                        <td>
                            <strong>{{ $line->part?->part_number }}</strong><br>
                            <span style="color:#57534e;">{{ $line->part?->name }}</span>
                        </td>
                        <td class="num">{{ Qty::format($ordered) }}</td>
                        <td class="num">{{ Qty::format($others) }}</td>
                        <td class="num">{{ Qty::format($line->invoiced_qty) }}</td>
                        <td class="num {{ $over ? 'warn' : '' }}">{{ $over ? 'Over by '.Qty::format($billed - $ordered) : Qty::format($open) }}</td>
                        <td>
                            @forelse ($line->lots as $lot)
                                <div>{{ $lot->lot_number }} · {{ Qty::format($lot->qty) }}</div>
                            @empty
                                —
                            @endforelse
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <p class="note">
            One purchase order can have many invoices. One invoice line can have many batches, and each batch quantity is independent as long as the batches add up to the invoiced quantity and stay inside the open purchase-order quantity.
        </p>
        @if ($invoice->remarks)
            <p class="note">{{ $invoice->remarks }}</p>
        @endif
    </article>
</body>
</html>
