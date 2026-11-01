<?php

use App\Concerns\NotifiesWithSweetAlert;
use App\Services\RecordApproval;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Modules\Quality\Models\PurchaseOrder;
use Modules\Quality\Services\QualityWorkflow;
use Modules\Quality\Support\Qty;

new #[Layout('core::layouts.app')] class extends Component {
    use NotifiesWithSweetAlert;

    private const MODULE = 'quality.purchase.orders';

    public PurchaseOrder $order;

    public string $rejectionReason = '';

    public bool $showAcceptedStatusModal = false;

    public string $acceptedStatus = RecordApproval::PENDING;

    public string $acceptedStatusReason = '';

    public function mount(PurchaseOrder $order): void
    {
        $this->order = $order->load(['vendor', 'lines.part', 'creator', 'approver', 'invoices', 'activities.actor']);
    }

    public function approve(): void
    {
        try {
            $this->order = app(QualityWorkflow::class)->approvePurchaseOrder($this->order);
            $this->notifySuccess(__('Purchase order approved.'));
        } catch (ValidationException $exception) {
            $this->notifyError($exception->validator->errors()->first() ?: __('Could not approve this order.'));
        }
    }

    public function reject(): void
    {
        try {
            $this->order = app(QualityWorkflow::class)->rejectPurchaseOrder($this->order, $this->rejectionReason ?: null);
            $this->notifySuccess(__('Purchase order rejected.'));
        } catch (ValidationException $exception) {
            $this->notifyError($exception->validator->errors()->first() ?: __('Could not reject this order.'));
        }
    }

    public function openAcceptedStatus(): void
    {
        abort_unless(auth()->user()?->canModule('quality.approvals', 'edit'), 403);

        $this->acceptedStatus = (string) ($this->order->approval_status ?: RecordApproval::PENDING);
        $this->acceptedStatusReason = (string) ($this->order->rejection_reason ?? '');
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
            $this->order = app(QualityWorkflow::class)->updatePurchaseOrderAcceptedStatus(
                $this->order,
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
            'canView' => module_can(self::MODULE, 'view'),
            'canEdit' => module_can(self::MODULE, 'edit'),
            'canDelete' => module_can(self::MODULE, 'delete'),
            'canApprove' => module_can('quality.approvals', 'edit'),
            'isPending' => app(RecordApproval::class)->isAwaitingDecision($this->order),
            'acceptedStatusLabels' => RecordApproval::statusLabels(),
            'activities' => $this->order->activities()->with('actor')->latest('id')->get(),
        ];
    }
}; ?>

<div class="space-y-6">
    <x-core::ui.page-header
        :title="$order->po_number"
        :subtitle="__('Purchase order details')"
        :breadcrumbs="[
            admin_breadcrumb_home(),
            ['label' => __('Purchase orders'), 'route' => 'admin::quality.purchase.orders'],
            ['label' => $order->po_number],
        ]"
    >
        <x-slot:actions>
            <flux:button variant="ghost" icon="arrow-left" :href="route('admin::quality.purchase.orders')" wire:navigate>
                {{ __('Back') }}
            </flux:button>
            @if ($canView)
                <flux:button variant="ghost" icon="document-text" :href="route('admin::quality.purchase.orders.format', $order)">
                    {{ __('Format') }}
                </flux:button>
            @endif
            @if ($canEdit)
                <flux:button variant="primary" icon="pencil-square" :href="route('admin::quality.purchase.orders.edit', $order)" wire:navigate>
                    {{ __('Edit') }}
                </flux:button>
            @endif
        </x-slot:actions>
    </x-core::ui.page-header>

    <div class="grid gap-4 lg:grid-cols-3">
        <div class="admin-card space-y-3 p-5 lg:col-span-2">
            <div class="grid gap-3 sm:grid-cols-2">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-zinc-500">{{ __('Vendor') }}</p>
                    <p class="mt-1 text-sm">{{ $order->vendor?->optionLabel() ?? '—' }}</p>
                </div>
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-zinc-500">{{ __('Status') }}</p>
                    <div class="mt-1"><x-quality::status-pill :status="$order->status" /></div>
                </div>
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-zinc-500">{{ __('PO date') }}</p>
                    <p class="mt-1 text-sm">{{ $order->po_date?->format('d M Y') }}</p>
                </div>
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-zinc-500">{{ __('Expected') }}</p>
                    <p class="mt-1 text-sm">{{ $order->expected_date?->format('d M Y') ?: '—' }}</p>
                </div>
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-zinc-500">{{ __('Created by') }}</p>
                    <div class="mt-1">
                        <x-quality::user-avatars :users="[$order->creator, $order->approver]" />
                    </div>
                </div>
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-zinc-500">{{ __('Accepted Status') }}</p>
                    <div class="mt-1 flex items-center gap-1.5">
                        <x-quality::status-pill :status="$order->approval_status ?? 'approved'" />
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
            @if ($order->remarks)
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-zinc-500">{{ __('Remarks') }}</p>
                    <p class="mt-1 text-sm whitespace-pre-wrap">{{ $order->remarks }}</p>
                </div>
            @endif
        </div>

        <div class="admin-card space-y-3 p-5">
            <p class="text-sm font-semibold">{{ __('Totals') }}</p>
            <div class="space-y-2 text-sm">
                <div class="flex justify-between gap-3"><span>{{ __('Ordered') }}</span><span class="tabular-nums">{{ Qty::format($order->orderedQuantity()) }}</span></div>
                <div class="flex justify-between gap-3"><span>{{ __('Invoiced') }}</span><span class="tabular-nums">{{ Qty::format($order->invoicedQuantity()) }}</span></div>
                <div class="flex justify-between gap-3"><span>{{ __('Balance') }}</span><span class="tabular-nums">{{ Qty::format($order->balanceQuantity()) }}</span></div>
            </div>

            @if ($canApprove && $isPending)
                <div class="space-y-2 border-t border-zinc-200 pt-3 dark:border-zinc-700">
                    <p class="text-sm font-semibold">{{ __('Accepted Status') }}</p>
                    <flux:textarea wire:model="rejectionReason" :label="__('Rejection reason (optional)')" rows="2" />
                    <div class="flex flex-wrap gap-2">
                        <flux:button variant="primary" wire:click="approve">{{ __('Approve') }}</flux:button>
                        <flux:button variant="danger" wire:click="reject">{{ __('Reject') }}</flux:button>
                        <flux:button variant="ghost" wire:click="openAcceptedStatus">{{ __('Set status…') }}</flux:button>
                    </div>
                </div>
            @elseif ($order->approver)
                <p class="border-t border-zinc-200 pt-3 text-xs text-zinc-500 dark:border-zinc-700">
                    {{ __('Decided by :name on :date', [
                        'name' => $order->approver->full_name,
                        'date' => $order->approved_at?->format('d M Y H:i') ?? '—',
                    ]) }}
                </p>
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
                    <th class="px-4 py-3 text-left text-xs font-semibold text-zinc-500">{{ __('Line') }}</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-zinc-500">{{ __('Part') }}</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-zinc-500">{{ __('Qty') }}</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-zinc-500">{{ __('UOM') }}</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-zinc-500">{{ __('Unit price') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                @foreach ($order->lines as $line)
                    <tr>
                        <td class="px-4 py-3 text-sm">{{ $line->line_no }}</td>
                        <td class="px-4 py-3 text-sm">{{ $line->part?->part_number }} — {{ $line->part?->name }}</td>
                        <td class="px-4 py-3 text-sm tabular-nums">{{ Qty::format($line->ordered_qty) }}</td>
                        <td class="px-4 py-3 text-sm">{{ $line->uom }}</td>
                        <td class="px-4 py-3 text-sm tabular-nums">{{ Qty::format($line->unit_price) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
