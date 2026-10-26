<?php

use App\Concerns\NotifiesWithSweetAlert;
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

    public string $search = '';

    public function updatingSearch(): void
    {
        $this->resetPage();
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
            'invoices' => PurchaseInvoice::query()
                ->with(['vendor', 'purchaseOrder', 'lines.lots'])
                ->when($this->search !== '', function ($query) {
                    $term = '%'.$this->search.'%';
                    $query->where(function ($query) use ($term) {
                        $query->where('invoice_number', 'like', $term)
                            ->orWhereHas('purchaseOrder', fn ($order) => $order->where('po_number', 'like', $term))
                            ->orWhereHas('lines.lots', fn ($lot) => $lot->where('lot_number', 'like', $term));
                    });
                })
                ->latest('invoice_date')
                ->latest('id')
                ->paginate(10),
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
        <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" :placeholder="__('Search invoice, PO, or batch number')" />
    </div>

    <div class="overflow-x-auto admin-card">
        <table class="min-w-full divide-y divide-zinc-200 dark:divide-zinc-700">
            <thead class="bg-[var(--admin-surface-muted)]">
                <tr>
                    @foreach ([__('Invoice'), __('Purchase order'), __('Vendor'), __('Date'), __('Qty'), __('Batches'), __('Status')] as $heading)
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-zinc-500">{{ $heading }}</th>
                    @endforeach
                    <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wider text-zinc-500">{{ __('Actions') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                @forelse ($invoices as $invoice)
                    <tr class="hover:bg-zinc-50 dark:hover:bg-zinc-800/40" wire:key="pi-{{ $invoice->id }}">
                        <td class="px-4 py-3 text-sm font-medium">{{ $invoice->invoice_number }}</td>
                        <td class="px-4 py-3 text-sm">{{ $invoice->purchaseOrder?->po_number }}</td>
                        <td class="px-4 py-3 text-sm">{{ $invoice->vendor?->name }}</td>
                        <td class="px-4 py-3 text-sm">{{ $invoice->invoice_date?->format('d M Y') }}</td>
                        <td class="px-4 py-3 text-sm tabular-nums">{{ Qty::format($invoice->invoicedQuantity()) }}</td>
                        <td class="px-4 py-3 text-sm">
                            {{ $invoice->lines->sum(fn ($line) => $line->lots->count()) }}
                        </td>
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

    @if ($invoices->hasPages())
        <div class="mt-4">{{ $invoices->links() }}</div>
    @endif
</div>
