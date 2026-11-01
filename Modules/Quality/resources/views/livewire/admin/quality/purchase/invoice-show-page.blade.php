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

    private const MODULE = 'quality.purchase.invoices';

    public PurchaseInvoice $invoice;

    public string $rejectionReason = '';

    public bool $showAcceptedStatusModal = false;

    public string $acceptedStatus = RecordApproval::PENDING;

    public string $acceptedStatusReason = '';

    public function mount(PurchaseInvoice $invoice): void
    {
        $this->invoice = $invoice->load([
            'vendor',
            'purchaseOrder',
            'lines.part',
            'lines.lots',
            'creator',
            'approver',
            'activities.actor',
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

    public function openAcceptedStatus(): void
    {
        abort_unless(auth()->user()?->canModule('quality.approvals', 'edit'), 403);

        $this->acceptedStatus = (string) ($this->invoice->approval_status ?: RecordApproval::PENDING);
        $this->acceptedStatusReason = (string) ($this->invoice->rejection_reason ?? '');
        $this->showAcceptedStatusModal = true;
    }

    public function saveAcceptedStatus(): void
    {
        abort_unless(auth()->user()?->canModule('quality.approvals', 'edit'), 403);

        $this->validate(array_merge(
            RecordApproval::statusValidationRules('acceptedStatus'),
            ['acceptedStatusReason' => ['nullable', 'string', 'max:2000']],
        ));

        try {
            $this->invoice = app(QualityWorkflow::class)->updatePurchaseInvoiceAcceptedStatus(
                $this->invoice,
                $this->acceptedStatus,
                $this->acceptedStatusReason ?: null,
            );
            $this->showAcceptedStatusModal = false;
            $this->notifySuccess(__('Accepted status updated.'));
        } catch (ValidationException $exception) {
            $this->notifyError($exception->validator->errors()->first() ?: __('Could not update accepted status.'));
        }
    }

    public function with(): array
    {
        return [
            'canEdit' => module_can(self::MODULE, 'edit'),
            'canDelete' => module_can(self::MODULE, 'delete'),
            'canApprove' => module_can('quality.approvals', 'edit'),
            'isPending' => app(RecordApproval::class)->isAwaitingDecision($this->invoice),
            'acceptedStatusLabels' => RecordApproval::statusLabels(),
            'activities' => $this->invoice->activities()->with('actor')->latest('id')->get(),
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
            @if ($canEdit)
                <flux:button variant="primary" icon="pencil-square" :href="route('admin::quality.purchase.invoices.edit', $invoice)" wire:navigate>
                    {{ __('Edit') }}
                </flux:button>
            @endif
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
                    <div class="mt-1">
                        <x-quality::user-avatars :users="[$invoice->creator, $invoice->approver]" />
                    </div>
                </div>
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-zinc-500">{{ __('Accepted Status') }}</p>
                    <div class="mt-1 flex items-center gap-1.5">
                        <x-quality::status-pill :status="$invoice->approval_status ?? 'approved'" />
                        @if ($canApprove)
                            <button
                                type="button"
                                class="rounded p-1 text-zinc-400 hover:bg-zinc-100 hover:text-zinc-700 dark:hover:bg-zinc-800"
                                wire:click="openAcceptedStatus"
                                title="{{ __('Edit accepted status') }}"
                            >
                                <flux:icon name="pencil-square" class="size-4" />
                            </button>
                        @endif
                    </div>
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
                <p class="text-sm font-semibold">{{ __('Accepted Status') }}</p>
                <flux:textarea wire:model="rejectionReason" :label="__('Rejection reason (optional)')" rows="2" />
                <div class="flex flex-wrap gap-2">
                    <flux:button variant="primary" wire:click="approve">{{ __('Approve') }}</flux:button>
                    <flux:button variant="danger" wire:click="reject">{{ __('Reject') }}</flux:button>
                    <flux:button variant="ghost" wire:click="openAcceptedStatus">{{ __('Set status…') }}</flux:button>
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

    <x-quality::activity-timeline :activities="$activities" />

    <flux:modal wire:model="showAcceptedStatusModal" class="max-w-md">
        <div class="space-y-4">
            <flux:heading size="lg">{{ __('Edit Accepted Status') }}</flux:heading>
            <flux:select wire:model="acceptedStatus" :label="__('Accepted Status')">
                @foreach ($acceptedStatusLabels as $value => $label)
                    <flux:select.option :value="$value">{{ $label }}</flux:select.option>
                @endforeach
            </flux:select>
            <flux:textarea wire:model="acceptedStatusReason" :label="__('Reason (optional)')" rows="3" />
            <div class="flex justify-end gap-2">
                <flux:button variant="ghost" wire:click="$set('showAcceptedStatusModal', false)">{{ __('Cancel') }}</flux:button>
                <flux:button variant="primary" wire:click="saveAcceptedStatus">{{ __('Save') }}</flux:button>
            </div>
        </div>
    </flux:modal>

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
