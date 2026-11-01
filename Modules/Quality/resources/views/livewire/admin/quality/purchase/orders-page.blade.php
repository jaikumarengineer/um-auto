<?php

use App\Concerns\AuthorizesModule;
use App\Concerns\NotifiesWithSweetAlert;
use App\Concerns\WithSorting;
use App\Services\RecordApproval;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Livewire\WithPagination;
use Modules\Quality\Models\PurchaseOrder;
use Modules\Quality\Services\QualityWorkflow;
use Modules\Quality\Support\Qty;

new #[Layout('core::layouts.app')] class extends Component {
    use AuthorizesModule;
    use NotifiesWithSweetAlert;
    use WithPagination;
    use WithSorting;

    private const MODULE = 'quality.purchase.orders';

    public string $search = '';

    public string $statusFilter = '';

    public int $perPage = 10;

    public string $sortBy = 'po_date';

    public string $sortDirection = 'desc';

    protected array $sortable = ['po_number', 'po_date', 'expected_date', 'status', 'id'];

    public bool $showAcceptedStatusModal = false;

    public ?int $acceptedStatusOrderId = null;

    public string $acceptedStatus = RecordApproval::PENDING;

    public string $acceptedStatusReason = '';

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingStatusFilter(): void
    {
        $this->resetPage();
    }

    public function updatingPerPage(): void
    {
        $this->resetPage();
    }

    public function updatedPerPage(): void
    {
        if (! in_array($this->perPage, [10, 25, 50], true)) {
            $this->perPage = 10;
        }
    }

    public function openAcceptedStatus(int $id): void
    {
        abort_unless(auth()->user()?->canModule('quality.approvals', 'edit'), 403);

        $order = PurchaseOrder::query()->findOrFail($id);
        $this->acceptedStatusOrderId = $order->id;
        $this->acceptedStatus = (string) ($order->approval_status ?: RecordApproval::PENDING);
        $this->acceptedStatusReason = (string) ($order->rejection_reason ?? '');
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
            $order = PurchaseOrder::query()->findOrFail($this->acceptedStatusOrderId);
            app(QualityWorkflow::class)->updatePurchaseOrderAcceptedStatus(
                $order,
                $this->acceptedStatus,
                $this->acceptedStatusReason ?: null,
            );
            $this->showAcceptedStatusModal = false;
            $this->notifySuccess(__('Accepted status updated.'));
        } catch (ValidationException $exception) {
            $this->notifyError($exception->validator->errors()->first() ?: __('Could not update accepted status.'));
        }
    }

    public function deleteOrder(int $id): void
    {
        $this->authorizeModule(self::MODULE, 'delete');

        try {
            app(QualityWorkflow::class)->deletePurchaseOrder(PurchaseOrder::query()->findOrFail($id));
            $this->notifySuccess(__('Purchase order deleted.'));
        } catch (ValidationException $exception) {
            $this->notifyError($exception->validator->errors()->first() ?: __('This purchase order cannot be deleted.'));
        }
    }

    public function with(): array
    {
        $orders = $this->applySorting(
            PurchaseOrder::query()
                ->with(['vendor', 'lines.invoiceLines', 'creator', 'approver'])
                ->when($this->search !== '', function ($query) {
                    $term = '%'.$this->search.'%';
                    $query->where(function ($query) use ($term) {
                        $query->where('po_number', 'like', $term)
                            ->orWhereHas('vendor', fn ($vendor) => $vendor->where('name', 'like', $term)->orWhere('code', 'like', $term));
                    });
                })
                ->when($this->statusFilter !== '', fn ($query) => $query->where('status', $this->statusFilter))
        )->orderByDesc('id')->paginate($this->perPage);

        return [
            'orders' => $orders,
            'source' => config('quality.po_source', 'manual'),
            'canView' => module_can(self::MODULE, 'view'),
            'canAdd' => module_can(self::MODULE, 'add'),
            'canEdit' => module_can(self::MODULE, 'edit'),
            'canDelete' => module_can(self::MODULE, 'delete'),
            'canBookInvoice' => module_can('quality.purchase.invoices', 'add'),
            'canEditAcceptedStatus' => module_can('quality.approvals', 'edit'),
            'acceptedStatusLabels' => RecordApproval::statusLabels(),
        ];
    }
}; ?>

<div>
    <x-core::ui.page-header
        class="admin-fade-in"
        :title="__('Purchase orders')"
        :subtitle="__('Showing the list of purchase orders')"
        :breadcrumbs="[
            admin_breadcrumb_home(),
            ['label' => __('Purchase orders')],
        ]"
    >
        <x-slot:actions>
            @if ($canAdd)
                <flux:button variant="primary" icon="plus" icon:variant="outline" :href="route('admin::quality.purchase.orders.create')" wire:navigate>
                    {{ __('New purchase order') }}
                </flux:button>
            @endif
        </x-slot:actions>
    </x-core::ui.page-header>

    <div class="mb-4 admin-card p-4 admin-fade-in">
        <p class="text-sm text-zinc-600 dark:text-zinc-300">
            {{ __('Source: :source. Quantities stay here until invoices and batches are booked against them.', ['source' => str($source)->title()]) }}
        </p>
    </div>

    <div class="mb-4 admin-card p-3">
        <div class="flex flex-col gap-3 lg:flex-row lg:items-center">
            <div class="min-w-0 flex-1">
                <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" :placeholder="__('Search PO number, vendor name, or vendor code')" />
            </div>
            <flux:select wire:model.live="statusFilter" class="w-full lg:w-44">
                <flux:select.option value="">{{ __('All statuses') }}</flux:select.option>
                @foreach (['draft', 'open', 'partial', 'closed', 'cancelled'] as $status)
                    <flux:select.option :value="$status">{{ str($status)->title() }}</flux:select.option>
                @endforeach
            </flux:select>
            <flux:select wire:model.live="perPage" class="w-full lg:w-32">
                <flux:select.option value="10">10 / page</flux:select.option>
                <flux:select.option value="25">25 / page</flux:select.option>
                <flux:select.option value="50">50 / page</flux:select.option>
            </flux:select>
        </div>
    </div>

    <div class="overflow-x-auto admin-card">
        <table class="min-w-full divide-y divide-zinc-200 dark:divide-zinc-700">
            <thead class="bg-[var(--admin-surface-muted)]">
                <tr>
                    <x-core::ui.sortable-th column="po_number" :label="__('PO')" />
                    <th class="px-4 py-3 text-left text-xs font-semibold text-zinc-500">{{ __('Vendor') }}</th>
                    <x-core::ui.sortable-th column="po_date" :label="__('Date')" />
                    <x-core::ui.sortable-th column="expected_date" :label="__('Expected')" />
                    <th class="px-4 py-3 text-left text-xs font-semibold text-zinc-500">{{ __('Ordered') }}</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-zinc-500">{{ __('Invoiced') }}</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-zinc-500">{{ __('Balance') }}</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-zinc-500">{{ __('Remarks') }}</th>
                    <x-core::ui.sortable-th column="status" :label="__('Status')" />
                    <th class="px-4 py-3 text-left text-xs font-semibold text-zinc-500">{{ __('Accepted Status') }}</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-zinc-500">{{ __('Created by') }}</th>
                    <th class="px-4 py-3 text-right text-xs font-semibold text-zinc-500">{{ __('Actions') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                @forelse ($orders as $order)
                    <tr class="hover:bg-zinc-50 dark:hover:bg-zinc-800/40" wire:key="po-{{ $order->id }}">
                        <td class="px-4 py-3 text-sm font-medium">{{ $order->po_number }}</td>
                        <td class="px-4 py-3 text-sm">{{ $order->vendor?->optionLabel() ?? '—' }}</td>
                        <td class="px-4 py-3 text-sm">{{ $order->po_date?->format('d M Y') }}</td>
                        <td class="px-4 py-3 text-sm {{ $order->isOverdue() ? 'text-red-600' : '' }}">{{ $order->expected_date?->format('d M Y') ?: '—' }}</td>
                        <td class="px-4 py-3 text-sm tabular-nums">{{ Qty::format($order->orderedQuantity()) }}</td>
                        <td class="px-4 py-3 text-sm tabular-nums">{{ Qty::format($order->invoicedQuantity()) }}</td>
                        <td class="px-4 py-3 text-sm tabular-nums">{{ Qty::format($order->balanceQuantity()) }}</td>
                        <x-core::ui.long-text-cell :value="$order->remarks" />
                        <td class="px-4 py-3"><x-quality::status-pill :status="$order->status" /></td>
                        <td class="px-4 py-3">
                            <div class="flex items-center gap-1.5">
                                <x-quality::status-pill :status="$order->approval_status ?? 'approved'" />
                                @if ($canEditAcceptedStatus)
                                    <button
                                        type="button"
                                        class="rounded p-1 text-zinc-400 hover:bg-zinc-100 hover:text-zinc-700 dark:hover:bg-zinc-800"
                                        wire:click="openAcceptedStatus({{ $order->id }})"
                                        title="{{ __('Edit accepted status') }}"
                                    >
                                        <flux:icon name="pencil-square" class="size-4" />
                                    </button>
                                @endif
                            </div>
                        </td>
                        <td class="px-4 py-3">
                            <x-quality::user-avatars :users="[$order->creator, $order->approver]" />
                        </td>
                        <td class="px-4 py-3 text-right">
                            @if ($canView || $canEdit || $canDelete || ($canBookInvoice && in_array($order->status, ['open', 'partial']) && ($order->approval_status ?? 'approved') === 'approved'))
                                <x-core::ui.actions-menu>
                                    @if ($canView)
                                        <flux:menu.item icon="eye" icon:variant="outline" :href="route('admin::quality.purchase.orders.show', $order)" wire:navigate>
                                            {{ __('View') }}
                                        </flux:menu.item>
                                        <flux:menu.item icon="document-text" icon:variant="outline" :href="route('admin::quality.purchase.orders.format', $order)">
                                            {{ __('Format') }}
                                        </flux:menu.item>
                                    @endif
                                    @if ($canEdit)
                                        <flux:menu.item icon="pencil-square" icon:variant="outline" :href="route('admin::quality.purchase.orders.edit', $order)" wire:navigate>
                                            {{ __('Edit') }}
                                        </flux:menu.item>
                                    @endif
                                    @if ($canBookInvoice && in_array($order->status, ['open', 'partial']) && ($order->approval_status ?? 'approved') === 'approved')
                                        <flux:menu.item icon="plus" icon:variant="outline" :href="route('admin::quality.purchase.invoices.create', ['purchase_order' => $order->id])" wire:navigate>
                                            {{ __('Book invoice') }}
                                        </flux:menu.item>
                                    @endif
                                    @if ($canDelete)
                                        <flux:menu.separator />
                                        <x-core::ui.confirm-button as="menu-item" method="deleteOrder" :params="[$order->id]" />
                                    @endif
                                </x-core::ui.actions-menu>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="12" class="px-4 py-12 text-center text-sm text-zinc-500">{{ __('No purchase orders yet.') }}</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <x-core::ui.pagination :paginator="$orders" />

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
</div>
