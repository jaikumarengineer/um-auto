@php
    use Modules\Quality\Support\Qty;
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $order->po_number }} · Purchase order</title>
    <style>
        :root { color-scheme: light; }
        * { box-sizing: border-box; }
        body { margin: 0; font-family: Georgia, "Times New Roman", serif; color: #1c1917; background: #f5f5f4; }
        .toolbar { display: flex; justify-content: space-between; gap: 12px; max-width: 920px; margin: 0 auto; padding: 16px 20px 0; font-family: ui-sans-serif, system-ui, sans-serif; }
        a, button { font: inherit; }
        .toolbar a, .toolbar button { border: 1px solid #d6d3d1; background: white; border-radius: 999px; padding: 8px 14px; text-decoration: none; color: #1c1917; cursor: pointer; }
        .sheet { max-width: 920px; margin: 16px auto 40px; background: white; padding: 40px 44px; box-shadow: 0 12px 40px rgba(0,0,0,.06); }
        .kicker { letter-spacing: .16em; text-transform: uppercase; font-size: 11px; color: #78716c; font-family: ui-sans-serif, system-ui, sans-serif; }
        h1 { margin: 6px 0 0; font-size: 32px; font-weight: 500; }
        .grid { display: grid; grid-template-columns: 1fr 1fr; gap: 24px; margin-top: 28px; }
        .label { font-family: ui-sans-serif, system-ui, sans-serif; font-size: 11px; letter-spacing: .08em; text-transform: uppercase; color: #78716c; }
        table { width: 100%; border-collapse: collapse; margin-top: 28px; font-family: ui-sans-serif, system-ui, sans-serif; font-size: 13px; }
        th { text-align: left; font-size: 11px; letter-spacing: .06em; text-transform: uppercase; color: #78716c; border-bottom: 1px solid #e7e5e4; padding: 8px 6px; }
        td { border-bottom: 1px solid #f5f5f4; padding: 10px 6px; vertical-align: top; }
        .num { text-align: right; font-variant-numeric: tabular-nums; }
        .note { margin-top: 28px; font-family: ui-sans-serif, system-ui, sans-serif; font-size: 12px; color: #57534e; }
        .invoices { margin-top: 8px; }
        @media print {
            body { background: white; }
            .toolbar { display: none; }
            .sheet { box-shadow: none; margin: 0; max-width: none; }
        }
    </style>
</head>
<body>
    <div class="toolbar">
        <a href="{{ route('admin::quality.purchase.orders') }}">Back to list</a>
        <div style="display:flex; gap:8px;">
            @if (in_array($order->status, ['open', 'partial']))
                <a href="{{ route('admin::quality.purchase.invoices.create', ['purchase_order' => $order->id]) }}">Book invoice</a>
            @endif
            <button type="button" onclick="window.print()">Print</button>
        </div>
    </div>
    <article class="sheet">
        <p class="kicker">{{ config('app.name') }} · Purchase order</p>
        <h1>{{ $order->po_number }}</h1>
        <p class="kicker" style="margin-top:8px;">Manual register · {{ $order->status }} · {{ $order->currency }}</p>

        <div class="grid">
            <div>
                <p class="label">Vendor</p>
                <p style="margin:4px 0 0; font-size:18px;">{{ $order->vendor?->name }}</p>
                <p style="margin:4px 0 0; font-family: ui-sans-serif, system-ui, sans-serif; font-size:13px; color:#44403c;">
                    {{ $order->vendor?->code }}<br>
                    {{ $order->vendor?->address }}<br>
                    {{ $order->vendor?->gstin }}
                </p>
            </div>
            <div>
                <p class="label">Dates</p>
                <p style="margin:4px 0 0; font-family: ui-sans-serif, system-ui, sans-serif; font-size:14px;">
                    PO date {{ $order->po_date?->format('d M Y') }}<br>
                    Expected {{ $order->expected_date?->format('d M Y') ?: '—' }}<br>
                    Printed {{ now()->format('d M Y H:i') }}
                </p>
            </div>
        </div>

        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>Part</th>
                    <th class="num">Ordered</th>
                    <th class="num">Invoiced</th>
                    <th class="num">Balance</th>
                    <th class="num">Rate</th>
                    <th class="num">Amount</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($order->lines as $line)
                    <tr>
                        <td>{{ $line->line_no }}</td>
                        <td>
                            <strong>{{ $line->part?->part_number }}</strong><br>
                            <span style="color:#57534e;">{{ $line->part?->name }}</span>
                        </td>
                        <td class="num">{{ Qty::format($line->ordered_qty) }} {{ $line->uom }}</td>
                        <td class="num">{{ Qty::format($line->invoicedQuantity()) }}</td>
                        <td class="num">{{ Qty::format($line->remainingQuantity()) }}</td>
                        <td class="num">{{ Qty::money($line->unit_price) }}</td>
                        <td class="num">{{ Qty::money($line->amount()) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <p class="note">
            Ordered {{ Qty::format($order->orderedQuantity()) }} · invoiced {{ Qty::format($order->invoicedQuantity()) }} · balance {{ Qty::format($order->balanceQuantity()) }}.
            Several invoices can be booked against this order. Each invoice can carry more than one batch, and every batch keeps its own number.
        </p>

        @if ($order->remarks)
            <p class="note">{{ $order->remarks }}</p>
        @endif

        @if ($order->invoices->isNotEmpty())
            <p class="label invoices">Invoices against this order</p>
            <table>
                <thead>
                    <tr>
                        <th>Invoice</th>
                        <th>Date</th>
                        <th>Batches</th>
                        <th class="num">Qty</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($order->invoices as $invoice)
                        <tr>
                            <td><a href="{{ route('admin::quality.purchase.invoices.format', $invoice) }}">{{ $invoice->invoice_number }}</a></td>
                            <td>{{ $invoice->invoice_date?->format('d M Y') }}</td>
                            <td>
                                @foreach ($invoice->lines as $invoiceLine)
                                    @foreach ($invoiceLine->lots as $lot)
                                        {{ $lot->lot_number }} ({{ Qty::format($lot->qty) }})@if (! $loop->last), @endif
                                    @endforeach
                                @endforeach
                            </td>
                            <td class="num">{{ Qty::format($invoice->invoicedQuantity()) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </article>
</body>
</html>
