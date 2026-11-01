<?php

namespace Modules\Quality\Services;

use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Mail;
use Modules\Quality\Mail\QualityAlertMail;
use Modules\Quality\Models\IncomingLot;
use Modules\Quality\Models\InspectionReport;
use Modules\Quality\Models\PurchaseInvoice;
use Modules\Quality\Models\PurchaseOrder;
use Modules\Quality\Models\QualityAlert;
use Modules\Quality\Support\Qty;
use Modules\Quality\Support\QualityAlertCatalog;

class QualityDigest
{
    /**
     * @return array<int, QualityAlert>
     */
    public function generate(?CarbonInterface $date = null, bool $sendMail = true, string $cadence = 'daily'): array
    {
        if (! in_array($cadence, ['daily', 'weekly', 'monthly'], true)) {
            $cadence = 'daily';
        }

        $system = QualityAlertCatalog::systemPreferences();

        if (! $system['enabled']) {
            return [];
        }

        $day = Carbon::parse($date ?? now())->startOfDay();
        [$start, $end] = $this->period($day, $cadence);
        $messages = $this->buildMessages($cadence, $start, $end, (int) $system['overdue_days']);

        if ($messages === []) {
            return [];
        }

        QualityAlert::query()
            ->whereDate('alert_on', $day->toDateString())
            ->where('meta->cadence', $cadence)
            ->delete();

        $users = User::query()
            ->where('is_active', true)
            ->whereNotNull('email')
            ->where('email', '!=', '')
            ->get();

        $alerts = [];
        $whatsapp = trim((string) config('quality.alert_whatsapp'));

        foreach ($messages as $message) {
            foreach ($this->emailRecipientsFor($message['type'], $users, $system['fallback_email']) as $recipient) {
                $alert = QualityAlert::query()->create([
                    'channel' => 'email',
                    'alert_type' => $message['type'],
                    'recipient' => $recipient,
                    'subject' => $message['subject'],
                    'body' => $message['email'],
                    'status' => 'logged',
                    'alert_on' => $day->toDateString(),
                    'meta' => [
                        'cadence' => $cadence,
                        'as_of' => now()->format('d M Y H:i'),
                        'period_start' => $start->toDateString(),
                        'period_end' => $end->toDateString(),
                        'payload' => $message['payload'],
                    ],
                ]);

                if ($sendMail) {
                    $this->deliverEmail($alert, $message['payload']);
                }

                $alerts[] = $alert;
            }

            if ($whatsapp === '') {
                continue;
            }

            $alerts[] = QualityAlert::query()->create([
                'channel' => 'whatsapp',
                'alert_type' => $message['type'],
                'recipient' => $whatsapp,
                'subject' => $message['subject'],
                'body' => $message['whatsapp'],
                'status' => 'logged',
                'alert_on' => $day->toDateString(),
                'meta' => [
                    'cadence' => $cadence,
                    'as_of' => now()->format('d M Y H:i'),
                ],
            ]);
        }

        return $alerts;
    }

    /**
     * @param  Collection<int, User>  $users
     * @return list<string>
     */
    private function emailRecipientsFor(string $alertType, Collection $users, string $fallback): array
    {
        $subscribers = $users
            ->filter(fn (User $user) => $user->receivesQualityAlert($alertType))
            ->map(fn (User $user) => strtolower(trim((string) $user->email)))
            ->filter()
            ->unique()
            ->values()
            ->all();

        if ($subscribers !== []) {
            return $subscribers;
        }

        $fallback = strtolower(trim($fallback));

        return $fallback !== '' ? [$fallback] : [];
    }

    /**
     * @return array{0: CarbonInterface, 1: CarbonInterface}
     */
    private function period(CarbonInterface $day, string $cadence): array
    {
        $day = Carbon::parse($day)->startOfDay();

        return match ($cadence) {
            'weekly' => [$day->copy()->startOfWeek(), $day->copy()->endOfDay()],
            'monthly' => [$day->copy()->startOfMonth(), $day->copy()->endOfDay()],
            default => [$day->copy()->startOfDay(), $day->copy()->endOfDay()],
        };
    }

    private function periodLabel(CarbonInterface $start, CarbonInterface $end, string $cadence): string
    {
        if ($cadence === 'daily') {
            return $start->format('d M Y');
        }

        return $start->format('d M Y').' – '.$end->format('d M Y');
    }

    /**
     * Build every alert type with demo rows so layouts can be reviewed in one inbox.
     *
     * @return array<int, QualityAlert>
     */
    public function sendSamples(string $recipient, bool $sendMail = true, bool $writePreviews = true): array
    {
        $recipient = strtolower(trim($recipient));
        $period = now()->format('d M Y');
        $asOf = now()->format('d M Y H:i');
        $messages = $this->sampleMessages($period);
        $alerts = [];
        $previewDir = storage_path('app/email-samples');

        if ($writePreviews && ! is_dir($previewDir)) {
            mkdir($previewDir, 0775, true);
        }

        foreach ($messages as $message) {
            $alert = QualityAlert::query()->create([
                'channel' => 'email',
                'alert_type' => $message['type'],
                'recipient' => $recipient,
                'subject' => '[Sample] '.$message['subject'],
                'body' => $message['email'],
                'status' => 'logged',
                'alert_on' => now()->toDateString(),
                'meta' => [
                    'cadence' => 'sample',
                    'as_of' => $asOf,
                    'sample' => true,
                    'payload' => $message['payload'],
                ],
            ]);

            if ($writePreviews) {
                $html = (new QualityAlertMail($alert, $message['payload']))->render();
                $safe = str_replace(['.', '/'], '-', $message['type']);
                file_put_contents($previewDir.DIRECTORY_SEPARATOR.$safe.'.html', $html);
            }

            if ($sendMail) {
                $this->deliverEmail($alert, $message['payload']);
            }

            $alerts[] = $alert;
        }

        return $alerts;
    }

    /**
     * @return array<int, array{type: string, subject: string, email: string, whatsapp: string, payload: array<string, mixed>}>
     */
    public function sampleMessages(string $period): array
    {
        $purchaseDemo = [
            'overdue' => [
                'PO-1042 · Apex Castings · ordered 120 · invoiced 40 · balance 80 · expected 20 Oct 2026',
                'PO-1055 · Nova Forge · ordered 50 · invoiced 0 · balance 50 · expected 22 Oct 2026',
            ],
            'pending' => [
                'PO-1061 · Apex Castings · ordered 200 · invoiced 100 · balance 100 · expected 02 Nov 2026',
            ],
            'completed' => [
                'PO-1030 · Metro Metals · ordered 75 · invoiced 75 · balance 0',
            ],
            'raised' => 4,
            'invoices' => 3,
            'invoice_qty' => '215',
        ];

        $incomingDemo = [
            'overdue' => [
                'LOT-8891 · BRK-220 · Apex Castings · qty 500 · received 18 Oct 2026 09:40',
            ],
            'pending' => [
                'LOT-8902 · HSG-118 · Nova Forge · qty 120 · received 27 Oct 2026 14:15',
                'LOT-8908 · PIN-044 · Metro Metals · qty 80 · received 28 Oct 2026 08:05',
            ],
            'completed' => [
                'IR-4410 · LOT-8870 · BRK-220 · Pass',
                'IR-4412 · LOT-8875 · HSG-118 · Conditional',
            ],
            'received' => 5,
            'received_qty' => '900',
            'reports' => 2,
            'failed' => 0,
        ];

        $messages = [];

        foreach (array_keys(QualityAlertCatalog::STATUSES) as $status) {
            $messages[] = $this->statusMessage('po_vs_pi', $status, $period, $purchaseDemo[$status]);
            $messages[] = $this->statusMessage('incoming_inspection', $status, $period, $incomingDemo[$status]);
        }

        foreach (array_keys(QualityAlertCatalog::SUMMARIES) as $cadence) {
            $messages[] = $this->summaryMessage('po_vs_pi', $cadence, $period, $purchaseDemo, [
                'Purchase orders raised: '.$purchaseDemo['raised'],
                'Purchase invoices booked: '.$purchaseDemo['invoices'].' ('.$purchaseDemo['invoice_qty'].' qty)',
            ]);
            $messages[] = $this->summaryMessage('incoming_inspection', $cadence, $period, $incomingDemo, [
                'Batches received: '.$incomingDemo['received'].' ('.$incomingDemo['received_qty'].' qty)',
                'Inspection reports: '.$incomingDemo['reports'],
                'Failed inspections: '.$incomingDemo['failed'],
            ]);
        }

        return $messages;
    }

    /**
     * @return array<int, array{type: string, subject: string, email: string, whatsapp: string, payload: array<string, mixed>}>
     */
    private function buildMessages(string $cadence, CarbonInterface $start, CarbonInterface $end, int $overdueDays): array
    {
        $purchase = $this->purchaseSnapshot($start, $end);
        $incoming = $this->incomingSnapshot($start, $end, $overdueDays);
        $period = $this->periodLabel($start, $end, $cadence);
        $messages = [];

        if ($cadence === 'daily') {
            foreach (array_keys(QualityAlertCatalog::STATUSES) as $status) {
                $messages[] = $this->statusMessage('po_vs_pi', $status, $period, $purchase[$status]);
                $messages[] = $this->statusMessage('incoming_inspection', $status, $period, $incoming[$status]);
            }
        }

        $messages[] = $this->summaryMessage('po_vs_pi', $cadence, $period, $purchase, [
            'Purchase orders raised: '.$purchase['raised'],
            'Purchase invoices booked: '.$purchase['invoices'].' ('.$purchase['invoice_qty'].' qty)',
        ]);
        $messages[] = $this->summaryMessage('incoming_inspection', $cadence, $period, $incoming, [
            'Batches received: '.$incoming['received'].' ('.$incoming['received_qty'].' qty)',
            'Inspection reports: '.$incoming['reports'],
            'Failed inspections: '.$incoming['failed'],
        ]);

        return array_values(array_filter(
            $messages,
            fn (array $message) => QualityAlertCatalog::systemAllows($message['type']),
        ));
    }

    /**
     * @param  list<string>  $lines
     * @return array{type: string, subject: string, email: string, whatsapp: string, payload: array<string, mixed>}
     */
    private function statusMessage(string $report, string $status, string $period, array $lines): array
    {
        $type = "{$report}.{$status}";
        $heading = QualityAlertCatalog::alertTypes()[$type]['label'].' · '.$period;
        $scope = $status === 'completed' ? 'in this period' : 'now';
        $asOf = now()->format('d M Y H:i');
        $badge = ucfirst($status).' '.$scope.' ('.count($lines).')';
        $shown = array_slice($lines, 0, 25);
        $extra = count($lines) - count($shown);

        if ($extra > 0) {
            $shown[] = "… and {$extra} more.";
        }

        $payload = [
            'heading' => $heading,
            'as_of' => $asOf,
            'kind' => 'status',
            'status' => $status,
            'badge' => $badge,
            'preamble' => [],
            'sections' => [
                [
                    'title' => $badge,
                    'lines' => $shown,
                    'tone' => $status,
                ],
            ],
        ];

        return [
            'type' => $type,
            'subject' => $heading,
            'email' => $heading."\n".__('As of :time', ['time' => $asOf])."\n\n"
                .$badge."\n"
                .$this->lines($lines),
            'whatsapp' => $heading.': '.count($lines).'.',
            'payload' => $payload,
        ];
    }

    /**
     * @param  array<string, mixed>  $snapshot
     * @param  list<string>  $preamble
     * @return array{type: string, subject: string, email: string, whatsapp: string, payload: array<string, mixed>}
     */
    private function summaryMessage(string $report, string $cadence, string $period, array $snapshot, array $preamble): array
    {
        $type = "{$report}.summary.{$cadence}";
        $heading = QualityAlertCatalog::alertTypes()[$type]['label'].' · '.$period;
        $asOf = now()->format('d M Y H:i');

        $sections = [
            [
                'title' => 'Overdue now ('.count($snapshot['overdue']).')',
                'lines' => $this->shownLines($snapshot['overdue']),
                'tone' => 'overdue',
            ],
            [
                'title' => 'Pending now ('.count($snapshot['pending']).')',
                'lines' => $this->shownLines($snapshot['pending']),
                'tone' => 'pending',
            ],
            [
                'title' => 'Completed in this period ('.count($snapshot['completed']).')',
                'lines' => $this->shownLines($snapshot['completed']),
                'tone' => 'completed',
            ],
        ];

        $email = implode("\n", [
            $heading,
            __('As of :time', ['time' => $asOf]),
            '',
            implode("\n", $preamble),
            '',
            'Overdue now ('.count($snapshot['overdue']).')',
            $this->lines($snapshot['overdue']),
            '',
            'Pending now ('.count($snapshot['pending']).')',
            $this->lines($snapshot['pending']),
            '',
            'Completed in this period ('.count($snapshot['completed']).')',
            $this->lines($snapshot['completed']),
        ]);

        return [
            'type' => $type,
            'subject' => $heading,
            'email' => $email,
            'whatsapp' => $heading.': overdue '.count($snapshot['overdue']).', pending '.count($snapshot['pending']).', completed '.count($snapshot['completed']).'.',
            'payload' => [
                'heading' => $heading,
                'as_of' => $asOf,
                'kind' => 'summary',
                'status' => null,
                'badge' => QualityAlertCatalog::SUMMARIES[$cadence].' summary',
                'preamble' => $preamble,
                'sections' => $sections,
            ],
        ];
    }

    /**
     * @return array{overdue: list<string>, pending: list<string>, completed: list<string>, raised: int, invoices: int, invoice_qty: string}
     */
    private function purchaseSnapshot(CarbonInterface $start, CarbonInterface $end): array
    {
        $asOf = $end->copy()->startOfDay();
        $overdue = [];
        $pending = [];
        $completed = [];
        $raised = 0;

        $orders = PurchaseOrder::query()
            ->with(['vendor', 'lines.invoiceLines'])
            ->where('status', '!=', 'cancelled')
            ->get();

        foreach ($orders as $order) {
            if ($order->status === 'draft') {
                continue;
            }

            if ($order->po_date && $order->po_date->between($start, $end)) {
                $raised++;
            }

            $line = sprintf(
                '%s · %s · ordered %s · invoiced %s · balance %s%s',
                $order->po_number,
                $order->vendor?->name ?? '—',
                Qty::format($order->orderedQuantity()),
                Qty::format($order->invoicedQuantity()),
                Qty::format($order->balanceQuantity()),
                $order->expected_date ? ' · expected '.$order->expected_date->format('d M Y') : '',
            );

            if ($order->status === 'closed') {
                if ($order->updated_at && $order->updated_at->between($start, $end)) {
                    $completed[] = $line;
                }

                continue;
            }

            if ($order->expected_date && $order->expected_date->lt($asOf) && in_array($order->status, ['open', 'partial'], true)) {
                $overdue[] = $line;

                continue;
            }

            if (in_array($order->status, ['open', 'partial'], true) && $order->balanceQuantity() > 0) {
                $pending[] = $line;
            }
        }

        $invoices = PurchaseInvoice::query()
            ->with('lines')
            ->whereDate('invoice_date', '>=', $start->toDateString())
            ->whereDate('invoice_date', '<=', $end->toDateString())
            ->get();

        return [
            'overdue' => $overdue,
            'pending' => $pending,
            'completed' => $completed,
            'raised' => $raised,
            'invoices' => $invoices->count(),
            'invoice_qty' => Qty::format($invoices->sum(fn (PurchaseInvoice $invoice) => $invoice->invoicedQuantity())),
        ];
    }

    /**
     * @return array{overdue: list<string>, pending: list<string>, completed: list<string>, received: int, received_qty: string, reports: int, failed: int}
     */
    private function incomingSnapshot(CarbonInterface $start, CarbonInterface $end, int $overdueDays): array
    {
        $cutoff = $end->copy()->startOfDay()->subDays(max(0, $overdueDays - 1));
        $overdue = [];
        $pending = [];
        $received = 0;
        $receivedQty = 0.0;

        $lots = IncomingLot::query()->with(['part', 'vendor', 'reports'])->get();

        foreach ($lots as $lot) {
            if ($lot->received_at && $lot->received_at->between($start, $end)) {
                $received++;
                $receivedQty += (float) $lot->qty;
            }

            if ($lot->reports->isNotEmpty()) {
                continue;
            }

            $line = sprintf(
                '%s · %s · %s · qty %s · received %s',
                $lot->lot_number,
                $lot->part?->part_number ?? '—',
                $lot->vendor?->name ?? '—',
                Qty::format($lot->qty),
                $lot->received_at?->format('d M Y H:i') ?? '—',
            );

            if ($lot->received_at && $lot->received_at->lt($cutoff)) {
                $overdue[] = $line;
            } else {
                $pending[] = $line;
            }
        }

        $reports = InspectionReport::query()
            ->with(['lot.part'])
            ->whereBetween('inspected_at', [$start, $end])
            ->orderBy('inspected_at')
            ->get();

        $completed = $reports->map(fn (InspectionReport $report) => sprintf(
            '%s · %s · %s · %s',
            $report->report_number,
            $report->lot?->lot_number ?? '—',
            $report->lot?->part?->part_number ?? '—',
            str($report->result)->replace('_', ' ')->title(),
        ))->all();

        return [
            'overdue' => $overdue,
            'pending' => $pending,
            'completed' => $completed,
            'received' => $received,
            'received_qty' => Qty::format($receivedQty),
            'reports' => $reports->count(),
            'failed' => $reports->where('result', 'fail')->count(),
        ];
    }

    /**
     * @param  list<string>  $rows
     * @return list<string>
     */
    private function shownLines(array $rows): array
    {
        $shown = array_slice($rows, 0, 25);
        $extra = count($rows) - count($shown);

        if ($extra > 0) {
            $shown[] = "… and {$extra} more.";
        }

        return $shown;
    }

    /**
     * @param  list<string>  $rows
     */
    private function lines(array $rows): string
    {
        if ($rows === []) {
            return 'None.';
        }

        return implode("\n", $this->shownLines($rows));
    }

    /**
     * @param  array<string, mixed>|null  $payload
     */
    private function deliverEmail(QualityAlert $alert, ?array $payload = null): void
    {
        $payload ??= $alert->meta['payload'] ?? [
            'heading' => $alert->subject,
            'as_of' => now()->format('d M Y H:i'),
            'kind' => 'status',
            'status' => null,
            'badge' => '',
            'preamble' => [],
            'sections' => [
                [
                    'title' => $alert->subject,
                    'lines' => preg_split("/\r\n|\n|\r/", (string) $alert->body) ?: [],
                ],
            ],
        ];

        try {
            Mail::to($alert->recipient)->send(new QualityAlertMail($alert, $payload));

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
