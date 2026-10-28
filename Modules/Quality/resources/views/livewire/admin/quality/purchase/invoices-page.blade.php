<?php

use App\Concerns\NotifiesWithSweetAlert;
use App\Concerns\WithSorting;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Livewire\WithPagination;
use Modules\Quality\Models\PurchaseInvoice;
use Modules\Quality\Services\QualityWorkflow;
use Modules\Quality\Support\Qty;

new #[Layout('core::layouts.app')] class extends Component {
    use NotifiesWithSweetAlert;
    use WithPagination;
    use WithSorting;

    public string $search = '';

    public int $perPage = 10;

    public string $sortBy = 'invoice_date';

    public string $sortDirection = 'desc';

    protected array $sortable = ['invoice_number', 'invoice_date', 'status', 'id'];

    public function updatingSearch(): void
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

    public function deleteInvoice(int $id): void
    {
        try {
            app(QualityWorkflow::class)->deletePurchaseInvoice(PurchaseInvoice::query()->findOrFail($id));
            $this->notifySuccess(__('Purchase invoice deleted.'));
        } catch (ValidationException $exception) {
            $this->notifyError($exception->validator->errors()->first() ?: __('This invoice cannot be deleted.'));
        }
    }

    public function with(): array
    {
        return [
            'invoices' => $this->applySorting(
                PurchaseInvoice::query()
                    ->with(['vendor', 'purchaseOrder', 'lines.lots'])
                    ->when($this->search !== '', function ($query) {
                        $term = '%'.$this->search.'%';
                        $query->where(function ($query) use ($term) {
                            $query->where('invoice_number', 'like', $term)
                                ->orWhereHas('purchaseOrder', fn ($order) => $order->where('po_number', 'like', $term))
                                ->orWhereHas('vendor', fn ($vendor) => $vendor->where('name', 'like', $term)->orWhere('code', 'like', $term))
                                ->orWhereHas('lines.lots', fn ($lot) => $lot->where('lot_number', 'like', $term));
                        });
                    })
            )->orderByDesc('id')->paginate($this->perPage),
        ];
    }
}; ?>

<div>
    <x-core::ui.page-header
        :title="__('Purchase invoices')"
        :subtitle="__('Several invoices can sit on one purchase order. Each line can carry many batches.')"
        :breadcrumbs="[
            admin_breadcrumb_home(),
            ['label' => __('Purchase invoices')],
        ]"
    >
        <x-slot:actions>
            <flux:button variant="primary" icon="plus" icon:variant="outline" :href="route('admin::quality.purchase.invoices.create')" wire:navigate>
                {{ __('Book invoice') }}
            </flux:button>
        </x-slot:actions>
    </x-core::ui.page-header>

    <div class="mb-4 admin-card p-3">
        <div class="flex flex-col gap-3 lg:flex-row lg:items-center">
            <div class="min-w-0 flex-1">
                <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" :placeholder="__('Search purchase invoice, vendor code, vendor name, PO, or batch')" />
            </div>
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
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-zinc-500">{{ __('Batch / lot') }}</th>
                    <x-core::ui.sortable-th column="invoice_number" :label="__('Purchase Invoice')" />
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-zinc-500">{{ __('Purchase order') }}</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-zinc-500">{{ __('Vendor') }}</th>
                    <x-core::ui.sortable-th column="invoice_date" :label="__('Date')" />
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-zinc-500">{{ __('Qty') }}</th>
                    <x-core::ui.sortable-th column="status" :label="__('Status')" />
                    <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wider text-zinc-500">{{ __('Actions') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                @forelse ($invoices as $invoice)
                    <tr class="hover:bg-zinc-50 dark:hover:bg-zinc-800/40" wire:key="pi-{{ $invoice->id }}">
                        <td class="px-4 py-3 text-sm font-medium">
                            @php
                                $lotNumbers = $invoice->lines->flatMap(fn ($line) => $line->lots)->pluck('lot_number')->filter()->values();
                            @endphp
                            @if ($lotNumbers->isEmpty())
                                —
                            @else
                                <div class="space-y-0.5">
                                    @foreach ($lotNumbers as $lotNumber)
                                        <div>{{ $lotNumber }}</div>
                                    @endforeach
                                </div>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-sm font-medium">{{ $invoice->invoice_number }}</td>
                        <td class="px-4 py-3 text-sm">{{ $invoice->purchaseOrder?->po_number }}</td>
                        <td class="px-4 py-3 text-sm">{{ $invoice->vendor?->optionLabel() ?? '—' }}</td>
                        <td class="px-4 py-3 text-sm">{{ $invoice->invoice_date?->format('d M Y') }}</td>
                        <td class="px-4 py-3 text-sm tabular-nums">{{ Qty::format($invoice->invoicedQuantity()) }}</td>
                        <td class="px-4 py-3"><x-quality::status-pill :status="$invoice->status" /></td>
                        <td class="px-4 py-3 text-right">
                            <x-core::ui.actions-menu>
                                <flux:menu.item icon="document-text" icon:variant="outline" :href="route('admin::quality.purchase.invoices.format', $invoice)">
                                    {{ __('Format') }}
                                </flux:menu.item>
                                <flux:menu.item icon="pencil-square" icon:variant="outline" :href="route('admin::quality.purchase.invoices.edit', $invoice)" wire:navigate>
                                    {{ __('Edit') }}
                                </flux:menu.item>
                                <flux:menu.separator />
                                <x-core::ui.confirm-button as="menu-item" method="deleteInvoice" :params="[$invoice->id]" />
                            </x-core::ui.actions-menu>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="px-4 py-12 text-center text-sm text-zinc-500">{{ __('No purchase invoices yet.') }}</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <x-core::ui.pagination :paginator="$invoices" />
</div>
