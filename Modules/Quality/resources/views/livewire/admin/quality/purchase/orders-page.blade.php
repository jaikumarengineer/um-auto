<?php

use App\Concerns\NotifiesWithSweetAlert;
use App\Concerns\WithSorting;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Livewire\WithPagination;
use Modules\Quality\Models\PurchaseOrder;
use Modules\Quality\Services\QualityWorkflow;
use Modules\Quality\Support\Qty;

new #[Layout('core::layouts.app')] class extends Component {
    use NotifiesWithSweetAlert;
    use WithPagination;
    use WithSorting;

    public string $search = '';

    public string $statusFilter = '';

    public int $perPage = 10;

    public string $sortBy = 'po_date';

    public string $sortDirection = 'desc';

    protected array $sortable = ['po_number', 'po_date', 'expected_date', 'status', 'id'];

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

    public function deleteOrder(int $id): void
    {
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
                ->with(['vendor', 'lines.invoiceLines', 'creator'])
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
            <flux:button variant="primary" icon="plus" icon:variant="outline" :href="route('admin::quality.purchase.orders.create')" wire:navigate>
                {{ __('New purchase order') }}
            </flux:button>
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
                    <th class="px-4 py-3 text-left text-xs font-semibold text-zinc-500">{{ __('Approval') }}</th>
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
                        <td class="px-4 py-3"><x-quality::status-pill :status="$order->approval_status ?? 'approved'" /></td>
                        <td class="px-4 py-3 text-sm">{{ $order->creator?->full_name ?: '—' }}</td>
                        <td class="px-4 py-3 text-right">
                            <x-core::ui.actions-menu>
                                <flux:menu.item icon="eye" icon:variant="outline" :href="route('admin::quality.purchase.orders.show', $order)" wire:navigate>
                                    {{ __('View') }}
                                </flux:menu.item>
                                <flux:menu.item icon="document-text" icon:variant="outline" :href="route('admin::quality.purchase.orders.format', $order)">
                                    {{ __('Format') }}
                                </flux:menu.item>
                                <flux:menu.item icon="pencil-square" icon:variant="outline" :href="route('admin::quality.purchase.orders.edit', $order)" wire:navigate>
                                    {{ __('Edit') }}
                                </flux:menu.item>
                                @if (in_array($order->status, ['open', 'partial']) && ($order->approval_status ?? 'approved') === 'approved')
                                    <flux:menu.item icon="plus" icon:variant="outline" :href="route('admin::quality.purchase.invoices.create', ['purchase_order' => $order->id])" wire:navigate>
                                        {{ __('Book invoice') }}
                                    </flux:menu.item>
                                @endif
                                <flux:menu.separator />
                                <x-core::ui.confirm-button as="menu-item" method="deleteOrder" :params="[$order->id]" />
                            </x-core::ui.actions-menu>
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
</div>
