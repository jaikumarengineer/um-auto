<?php

namespace Modules\Quality\Services;

use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Modules\Quality\Models\IncomingLot;
use Modules\Quality\Models\InspectionReport;
use Modules\Quality\Models\PurchaseInvoice;
use Modules\Quality\Models\PurchaseOrder;
use Modules\Quality\Models\QualityAlert;
use Modules\Quality\Support\Qty;

class QualityDigest
{
    /**
     * @return array<int, QualityAlert>
     */
    public function generate(?CarbonInterface $date = null, bool $sendMail = true): array
    {
        $day = Carbon::parse($date ?? now())->startOfDay();
        $snapshot = $this->snapshot($day);

        QualityAlert::query()->whereDate('alert_on', $day->toDateString())->delete();

        $alerts = [];

        foreach ($this->messages($snapshot) as $message) {
            foreach (['email', 'whatsapp'] as $channel) {
                $alert = QualityAlert::query()->create([
                    'channel' => $channel,
                    'alert_type' => $message['type'],
                    'recipient' => $channel === 'email'
                        ? (string) config('quality.alert_email')
                        : (string) config('quality.alert_whatsapp'),
                    'subject' => $message['subject'],
                    'body' => $channel === 'email' ? $message['email'] : $message['whatsapp'],
                    'status' => 'logged',
                    'alert_on' => $day->toDateString(),
                    'meta' => ['as_of' => $snapshot['as_of']],
                ]);

                if ($channel === 'email' && $sendMail) {
                    $this->deliverEmail($alert);
                }

                $alerts[] = $alert;
            }
        }

        return $alerts;
    }

    /**
     * @return array<string, mixed>
     */
    public function snapshot(CarbonInterface $day): array
    {
        $day = Carbon::parse($day)->startOfDay();
        $end = $day->copy()->endOfDay();

        $orders = PurchaseOrder::query()->with(['vendor', 'lines.invoiceLines'])->where('status', '!=', 'cancelled')->get();
        $openOrders = $orders->filter(fn (PurchaseOrder $order) => in_array($order->status, ['open', 'partial', 'draft'], true));
        $overdue = $orders->filter(fn (PurchaseOrder $order) => $order->isOverdue())->values();

        $balances = $orders
            ->filter(fn (PurchaseOrder $order) => $order->status !== 'draft' && $order->balanceQuantity() > 0)
            ->map(fn (PurchaseOrder $order) => [
                'po' => $order->po_number,
                'vendor' => $order->vendor?->name,
                'ordered' => Qty::format($order->orderedQuantity()),
                'invoiced' => Qty::format($order->invoicedQuantity()),
                'balance' => Qty::format($order->balanceQuantity()),
                'status' => $order->status,
            ])
            ->values()
            ->all();

        $invoicesToday = PurchaseInvoice::query()->whereDate('invoice_date', $day)->get();
        $lotsToday = IncomingLot::query()->with('part')->whereBetween('received_at', [$day, $end])->get();
        $reportsToday = InspectionReport::query()->whereBetween('inspected_at', [$day, $end])->get();
        $waiting = IncomingLot::query()->with('part')->whereDoesntHave('reports')->orderBy('received_at')->get();
        $holds = IncomingLot::query()->with('part')->where('status', 'rejected')->get();

        return [
            'date' => $day->toDateString(),
            'as_of' => now()->format('d M Y H:i'),
            'pos_created' => PurchaseOrder::query()->whereDate('po_date', $day)->count(),
            'invoices_today' => $invoicesToday->count(),
            'invoice_qty_today' => Qty::format($invoicesToday->sum(fn (PurchaseInvoice $invoice) => $invoice->invoicedQuantity())),
            'lots_today' => $lotsToday->count(),
            'lot_qty_today' => Qty::format($lotsToday->sum(fn (IncomingLot $lot) => (float) $lot->qty)),
            'reports_today' => $reportsToday->count(),
            'failed_today' => $reportsToday->where('result', 'fail')->count(),
            'open_orders' => $openOrders->count(),
            'overdue' => $overdue->map(fn (PurchaseOrder $order) => $order->po_number.' ('.$order->vendor?->name.')')->all(),
            'balances' => $balances,
            'waiting' => $waiting->map(fn (IncomingLot $lot) => $lot->lot_number.' · '.($lot->part?->part_number ?? '').' · '.Qty::format($lot->qty))->all(),
            'holds' => $holds->map(fn (IncomingLot $lot) => $lot->lot_number.' · '.($lot->part?->part_number ?? ''))->all(),
        ];
    }

    /**
     * @param  array<string, mixed>  $snapshot
     * @return array<int, array{type: string, subject: string, email: string, whatsapp: string}>
     */
    private function messages(array $snapshot): array
    {
        $date = Carbon::parse($snapshot['date'])->format('d M Y');
        $balanceLines = collect($snapshot['balances'])
            ->map(fn (array $row) => "{$row['po']} · ordered {$row['ordered']} · invoiced {$row['invoiced']} · balance {$row['balance']}")
            ->implode("\n");

        $summaryEmail = implode("\n", array_filter([
            "UM Auto incoming quality — day summary for {$date}",
            "As of {$snapshot['as_of']}",
            '',
            "Purchase orders raised: {$snapshot['pos_created']}",
            "Purchase invoices booked: {$snapshot['invoices_today']} ({$snapshot['invoice_qty_today']} qty)",
            "Batches received: {$snapshot['lots_today']} ({$snapshot['lot_qty_today']} qty)",
            "Inspection reports: {$snapshot['reports_today']}",
            "Failed inspections today: {$snapshot['failed_today']}",
            "Open purchase orders: {$snapshot['open_orders']}",
            'Lots still waiting inspection: '.count($snapshot['waiting']),
            'Rejected lots on hold: '.count($snapshot['holds']),
        ]));

        $poEmail = "PO vs purchase invoice till {$snapshot['as_of']}\n\n".($balanceLines !== ''
            ? $balanceLines
            : 'Every open purchase order is fully covered by invoices.');

        $waitingEmail = "Batches received and not yet inspected\n\n".($snapshot['waiting'] === []
            ? 'No batches are waiting.'
            : implode("\n", $snapshot['waiting']));

        $overdueEmail = "Purchase orders past the expected date\n\n".($snapshot['overdue'] === []
            ? 'No overdue purchase orders.'
            : implode("\n", $snapshot['overdue']));

        $holdEmail = "Rejected batches still on quality hold\n\n".($snapshot['holds'] === []
            ? 'No batches are on hold.'
            : implode("\n", $snapshot['holds']));

        $purchaseEmail = implode("\n", [
            "Purchase day summary for {$date}",
            "As of {$snapshot['as_of']}",
            '',
            "Purchase orders raised: {$snapshot['pos_created']}",
            "Purchase invoices booked: {$snapshot['invoices_today']} ({$snapshot['invoice_qty_today']} qty)",
            "Open purchase orders: {$snapshot['open_orders']}",
            'Overdue purchase orders: '.count($snapshot['overdue']),
        ]);

        $incomingEmail = implode("\n", [
            "Incoming day summary for {$date}",
            "As of {$snapshot['as_of']}",
            '',
            "Batches received: {$snapshot['lots_today']} ({$snapshot['lot_qty_today']} qty)",
            "Inspection reports: {$snapshot['reports_today']}",
            "Failed inspections today: {$snapshot['failed_today']}",
            'Lots still waiting inspection: '.count($snapshot['waiting']),
            'Rejected lots on hold: '.count($snapshot['holds']),
        ]);

        return [
            [
                'type' => 'daily_summary',
                'subject' => "Day summary · {$date}",
                'email' => $summaryEmail,
                'whatsapp' => "UM Auto {$date}: POs {$snapshot['pos_created']}, invoices {$snapshot['invoices_today']} ({$snapshot['invoice_qty_today']}), lots {$snapshot['lots_today']}, inspections {$snapshot['reports_today']}, fails {$snapshot['failed_today']}, waiting ".count($snapshot['waiting']).'.',
            ],
            [
                'type' => 'purchase_summary',
                'subject' => "Purchase day summary · {$date}",
                'email' => $purchaseEmail,
                'whatsapp' => "Purchase {$date}: POs {$snapshot['pos_created']}, invoices {$snapshot['invoices_today']} ({$snapshot['invoice_qty_today']}), open {$snapshot['open_orders']}, overdue ".count($snapshot['overdue']).'.',
            ],
            [
                'type' => 'incoming_summary',
                'subject' => "Incoming day summary · {$date}",
                'email' => $incomingEmail,
                'whatsapp' => "Incoming {$date}: lots {$snapshot['lots_today']} ({$snapshot['lot_qty_today']}), inspections {$snapshot['reports_today']}, fails {$snapshot['failed_today']}, waiting ".count($snapshot['waiting']).'.',
            ],
            [
                'type' => 'po_vs_pi',
                'subject' => "PO vs PI · {$date}",
                'email' => $poEmail,
                'whatsapp' => 'PO vs PI: '.(count($snapshot['balances']) === 0
                    ? 'all covered.'
                    : count($snapshot['balances']).' POs still have a balance. '.collect($snapshot['balances'])->take(3)->map(fn ($row) => $row['po'].' bal '.$row['balance'])->implode(', ')),
            ],
            [
                'type' => 'uninspected_lots',
                'subject' => "Lots waiting inspection · {$date}",
                'email' => $waitingEmail,
                'whatsapp' => count($snapshot['waiting']) === 0
                    ? 'No batches are waiting for inspection.'
                    : 'Waiting inspection: '.implode(', ', array_slice($snapshot['waiting'], 0, 4)),
            ],
            [
                'type' => 'overdue_po',
                'subject' => "Overdue purchase orders · {$date}",
                'email' => $overdueEmail,
                'whatsapp' => $snapshot['overdue'] === []
                    ? 'No overdue purchase orders.'
                    : 'Overdue POs: '.implode(', ', $snapshot['overdue']),
            ],
            [
                'type' => 'quality_hold',
                'subject' => "Quality hold · {$date}",
                'email' => $holdEmail,
                'whatsapp' => $snapshot['holds'] === []
                    ? 'No rejected batches on hold.'
                    : 'Quality hold: '.implode(', ', $snapshot['holds']),
            ],
        ];
    }

    private function deliverEmail(QualityAlert $alert): void
    {
        try {
            Mail::raw($alert->body, function ($message) use ($alert) {
                $message->to($alert->recipient)->subject($alert->subject);
            });

            $alert->update(['status' => 'sent']);
        } catch (\Throwable $exception) {
            $alert->update([
                'status' => 'logged',
                'meta' => array_merge($alert->meta ?? [], [
                    'mail_error' => $exception->getMessage(),
                ]),
            ]);
        }
    }
}
