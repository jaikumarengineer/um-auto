<?php

use App\Concerns\NotifiesWithSweetAlert;
use App\Services\RecordApproval;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Modules\Quality\Models\PurchaseInvoice;
use Modules\Quality\Services\QualityWorkflow;
use Modules\Quality\Support\Qty;

new #[Layout('core::layouts.app')] class extends Component {
    use NotifiesWithSweetAlert;

    public PurchaseInvoice $invoice;

    public string $rejectionReason = '';

    public function mount(PurchaseInvoice $invoice): void
    {
        $this->invoice = $invoice->load([
            'vendor',
            'purchaseOrder',
            'lines.part',
            'lines.lots',
            'creator',
            'approver',
        ]);
    }

    public function approve(): void
    {
        try {
            $this->invoice = app(QualityWorkflow::class)->approvePurchaseInvoice($this->invoice);
            $this->notifySuccess(__('Purchase invoice approved.'));
        } catch (ValidationException $exception) {
            $this->notifyError($exception->validator->errors()->first() ?: __('Could not approve this invoice.'));
        }
    }

    public function reject(): void
    {
        try {
            $this->invoice = app(QualityWorkflow::class)->rejectPurchaseInvoice($this->invoice, $this->rejectionReason ?: null);
            $this->notifySuccess(__('Purchase invoice rejected.'));
        } catch (ValidationException $exception) {
            $this->notifyError($exception->validator->errors()->first() ?: __('Could not reject this invoice.'));
        }
    }

    public function with(): array
    {
        return [
            'canApprove' => auth()->user()?->canModule('quality.approvals', 'edit') ?? false,
            'isPending' => ($this->invoice->approval_status ?? RecordApproval::APPROVED) === RecordApproval::PENDING,
        ];
    }
}; ?>

<div class="space-y-6">
    <x-core::ui.page-header
        :title="$invoice->invoice_number"
        :subtitle="__('Purchase invoice details')"
        :breadcrumbs="[
            admin_breadcrumb_home(),
            ['label' => __('Purchase invoices'), 'route' => 'admin::quality.purchase.invoices'],
            ['label' => $invoice->invoice_number],
        ]"
    >
        <x-slot:actions>
            <flux:button variant="ghost" icon="arrow-left" :href="route('admin::quality.purchase.invoices')" wire:navigate>
                {{ __('Back') }}
            </flux:button>
            <flux:button variant="ghost" icon="document-text" :href="route('admin::quality.purchase.invoices.format', $invoice)">
                {{ __('Format') }}
            </flux:button>
            <flux:button variant="primary" icon="pencil-square" :href="route('admin::quality.purchase.invoices.edit', $invoice)" wire:navigate>
                {{ __('Edit') }}
            </flux:button>
        </x-slot:actions>
    </x-core::ui.page-header>

    <div class="grid gap-4 lg:grid-cols-3">
        <div class="admin-card space-y-3 p-5 lg:col-span-2">
            <div class="grid gap-3 sm:grid-cols-2">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-zinc-500">{{ __('Purchase order') }}</p>
                    <p class="mt-1 text-sm">
                        @if ($invoice->purchaseOrder)
                            <a class="underline" href="{{ route('admin::quality.purchase.orders.show', $invoice->purchaseOrder) }}" wire:navigate>
                                {{ $invoice->purchaseOrder->po_number }}
                            </a>
                        @else
                            —
                        @endif
                    </p>
                </div>
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-zinc-500">{{ __('Vendor') }}</p>
                    <p class="mt-1 text-sm">{{ $invoice->vendor?->optionLabel() ?? '—' }}</p>
                </div>
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-zinc-500">{{ __('Date') }}</p>
                    <p class="mt-1 text-sm">{{ $invoice->invoice_date?->format('d M Y') }}</p>
                </div>
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-zinc-500">{{ __('Qty') }}</p>
                    <p class="mt-1 text-sm tabular-nums">{{ Qty::format($invoice->invoicedQuantity()) }}</p>
                </div>
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-zinc-500">{{ __('Created by') }}</p>
                    <p class="mt-1 text-sm">{{ $invoice->creator?->full_name ?: '—' }}</p>
                </div>
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-zinc-500">{{ __('Approval') }}</p>
                    <div class="mt-1"><x-quality::status-pill :status="$invoice->approval_status ?? 'approved'" /></div>
                </div>
            </div>
            @if ($invoice->remarks)
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-zinc-500">{{ __('Remarks') }}</p>
                    <p class="mt-1 text-sm whitespace-pre-wrap">{{ $invoice->remarks }}</p>
                </div>
            @endif
        </div>

        <div class="admin-card space-y-3 p-5">
            @if ($canApprove && $isPending)
                <p class="text-sm font-semibold">{{ __('Approval by Admin or Manager') }}</p>
                <flux:textarea wire:model="rejectionReason" :label="__('Rejection reason (optional)')" rows="2" />
                <div class="flex flex-wrap gap-2">
                    <flux:button variant="primary" wire:click="approve">{{ __('Approve') }}</flux:button>
                    <flux:button variant="danger" wire:click="reject">{{ __('Reject') }}</flux:button>
                </div>
            @elseif ($invoice->approver)
                <p class="text-xs text-zinc-500">
                    {{ __('Decided by :name on :date', [
                        'name' => $invoice->approver->full_name,
                        'date' => $invoice->approved_at?->format('d M Y H:i') ?? '—',
                    ]) }}
                </p>
            @else
                <p class="text-sm text-zinc-500">{{ __('No approval action needed.') }}</p>
            @endif
        </div>
    </div>

    <div class="overflow-x-auto admin-card">
        <table class="min-w-full divide-y divide-zinc-200 dark:divide-zinc-700">
            <thead class="bg-[var(--admin-surface-muted)]">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-zinc-500">{{ __('Part') }}</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-zinc-500">{{ __('Invoiced qty') }}</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-zinc-500">{{ __('Batches / lots') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                @foreach ($invoice->lines as $line)
                    <tr>
                        <td class="px-4 py-3 text-sm">{{ $line->part?->part_number }} — {{ $line->part?->name }}</td>
                        <td class="px-4 py-3 text-sm tabular-nums">{{ Qty::format($line->invoiced_qty) }}</td>
                        <td class="px-4 py-3 text-sm">
                            {{ $line->lots->pluck('lot_number')->filter()->implode(', ') ?: '—' }}
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
