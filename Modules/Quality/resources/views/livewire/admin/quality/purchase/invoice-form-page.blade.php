<?php

use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Modules\Quality\Models\PurchaseInvoice;
use Modules\Quality\Models\PurchaseOrder;
use Modules\Quality\Services\QualityWorkflow;
use Modules\Quality\Support\Qty;

new #[Layout('core::layouts.app')] class extends Component {
    public ?int $invoiceId = null;

    public bool $locked = false;

    public string $invoice_number = '';

    public string $purchase_order_id = '';

    public string $invoice_date = '';

    public string $remarks = '';

    public array $lines = [];

    public function mount(?int $invoice = null): void
    {
        $this->invoice_date = now()->toDateString();

        if ($invoice) {
            $this->fillFromInvoice(PurchaseInvoice::query()->with(['lines.lots', 'purchaseOrder.lines.part', 'purchaseOrder.lines.invoiceLines'])->findOrFail($invoice));

            return;
        }

        $purchaseOrder = request()->integer('purchase_order');

        if ($purchaseOrder) {
            $this->purchase_order_id = (string) $purchaseOrder;
            $this->loadOrderLines();
        }
    }

    public function updatedPurchaseOrderId(): void
    {
        if ($this->invoiceId) {
            return;
        }

        $this->loadOrderLines();
    }

    public function addLot(int $index): void
    {
        $this->lines[$index]['lots'][] = [
            'key' => (string) Str::uuid(),
            'lot_number' => '',
            'qty' => '',
        ];
    }

    public function removeLot(int $index, int $lotIndex): void
    {
        unset($this->lines[$index]['lots'][$lotIndex]);
        $this->lines[$index]['lots'] = array_values($this->lines[$index]['lots']);

        if ($this->lines[$index]['lots'] === []) {
            $this->addLot($index);
        }
    }

    public function save(QualityWorkflow $workflow): void
    {
        if ($this->locked) {
            return;
        }

        $this->validate([
            'invoice_number' => ['required', 'string', 'max:50', Rule::unique('purchase_invoices', 'invoice_number')->ignore($this->invoiceId)],
            'purchase_order_id' => ['required', 'exists:purchase_orders,id'],
            'invoice_date' => ['required', 'date'],
        ]);

        $invoice = $this->invoiceId ? PurchaseInvoice::query()->findOrFail($this->invoiceId) : null;

        $workflow->savePurchaseInvoice([
            'invoice_number' => $this->invoice_number,
            'purchase_order_id' => $this->purchase_order_id,
            'invoice_date' => $this->invoice_date,
            'remarks' => $this->remarks,
        ], $this->lines, $invoice);

        session()->flash('success', __('Purchase invoice saved. Quantities were checked against the purchase order.'));
        $this->redirectRoute('admin::quality.purchase.invoices', navigate: true);
    }

    public function with(): array
    {
        return [
            'orders' => PurchaseOrder::query()
                ->with('vendor')
                ->where(function ($query) {
                    $query->whereIn('status', ['open', 'partial', 'closed']);

                    if ($this->purchase_order_id !== '') {
                        $query->orWhere('id', $this->purchase_order_id);
                    }
                })
                ->orderByDesc('po_date')
                ->get(),
        ];
    }

    private function loadOrderLines(): void
    {
        $this->lines = [];

        if ($this->purchase_order_id === '') {
            return;
        }

        $order = PurchaseOrder::query()->with(['lines.part', 'lines.invoiceLines'])->find($this->purchase_order_id);

        if (! $order) {
            return;
        }

        foreach ($order->lines as $line) {
            $this->lines[] = $this->blankLine($line, $line->invoicedQuantity($this->invoiceId));
        }
    }

    private function fillFromInvoice(PurchaseInvoice $invoice): void
    {
        $this->invoiceId = $invoice->id;
        $this->locked = $invoice->hasInspectionReports();
        $this->invoice_number = $invoice->invoice_number;
        $this->purchase_order_id = (string) $invoice->purchase_order_id;
        $this->invoice_date = $invoice->invoice_date?->toDateString() ?? '';
        $this->remarks = (string) $invoice->remarks;
        $existing = $invoice->lines->keyBy('purchase_order_line_id');

        foreach ($invoice->purchaseOrder->lines as $line) {
            $match = $existing->get($line->id);
            $row = $this->blankLine($line, $line->invoicedQuantity($invoice->id));
            $row['invoiced_qty'] = $match ? (float) $match->invoiced_qty : '';
            $row['lots'] = $match && $match->lots->isNotEmpty()
                ? $match->lots->map(fn ($lot) => [
                    'key' => (string) Str::uuid(),
                    'lot_number' => $lot->lot_number,
                    'qty' => (float) $lot->qty,
                ])->all()
                : $row['lots'];
            $this->lines[] = $row;
        }
    }

    private function blankLine($line, float $already): array
    {
        return [
            'key' => (string) Str::uuid(),
            'purchase_order_line_id' => $line->id,
            'part_label' => $line->part?->label(),
            'uom' => $line->uom,
            'ordered_qty' => (float) $line->ordered_qty,
            'already_qty' => $already,
            'invoiced_qty' => '',
            'lots' => [[
                'key' => (string) Str::uuid(),
                'lot_number' => '',
                'qty' => '',
            ]],
        ];
    }
}; ?>

<div>
    <x-core::ui.page-header
        :title="$invoiceId ? __('Edit purchase invoice') : __('Book purchase invoice')"
        :subtitle="__('Quantity is checked against the open balance on the purchase order.')"
        :breadcrumbs="[
            admin_breadcrumb_home(),
            ['label' => __('Purchase invoices'), 'route' => 'admin::quality.purchase.invoices'],
            ['label' => $invoiceId ? $invoice_number : __('Create')],
        ]"
    />

    @if ($locked)
        <div class="mb-4 rounded-2xl border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-900 dark:border-amber-700 dark:bg-amber-950/40 dark:text-amber-100">
            {{ __('These batches are locked and cannot be changed.') }}
        </div>
    @endif

    <form wire:submit="save" class="space-y-4">
        <div class="admin-card grid gap-4 p-4 md:grid-cols-3">
            <flux:input wire:model="invoice_number" :label="__('Invoice number')" required :disabled="$locked" />
            <x-core::forms.select2
                wire:model.live="purchase_order_id"
                :label="__('Purchase order')"
                :options="$orders->mapWithKeys(fn ($order) => [$order->id => $order->po_number.' · '.($order->vendor?->optionLabel() ?? '—')])"
                :placeholder="__('Select')"
                :allow-clear="false"
                :disabled="$locked || (bool) $invoiceId"
            />
            <flux:input wire:model="invoice_date" type="date" :label="__('Invoice date')" required :disabled="$locked" />
            <div class="md:col-span-3">
                <flux:textarea wire:model="remarks" :label="__('Remarks')" rows="2" :disabled="$locked" />
            </div>
        </div>

        @error('lines') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
        @error('purchase_order_id') <p class="text-sm text-red-600">{{ $message }}</p> @enderror

        @foreach ($lines as $index => $line)
            @php
                $current = (float) ($line['invoiced_qty'] !== '' ? $line['invoiced_qty'] : 0);
                $open = max(0, (float) $line['ordered_qty'] - (float) $line['already_qty'] - $current);
            @endphp
            <section class="admin-card p-4" wire:key="invoice-line-{{ $line['key'] }}">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <p class="font-medium">{{ $line['part_label'] }}</p>
                        <p class="mt-1 text-xs text-zinc-500">
                            {{ __('Ordered :ordered · other invoices :already · still open :open :uom', [
                                'ordered' => Qty::format($line['ordered_qty']),
                                'already' => Qty::format($line['already_qty']),
                                'open' => Qty::format($open),
                                'uom' => $line['uom'],
                            ]) }}
                        </p>
                    </div>
                    <div class="w-40">
                        <flux:input wire:model.live="lines.{{ $index }}.invoiced_qty" type="number" step="0.001" min="0" :label="__('This invoice')" :disabled="$locked" />
                        @error('lines.'.$index.'.invoiced_qty') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                </div>

                <div class="mt-4 space-y-2">
                    <div class="flex items-center justify-between">
                        <p class="text-sm font-medium">{{ __('Batches / lots') }}</p>
                        @unless ($locked)
                            <flux:button type="button" size="sm" variant="ghost" icon="plus" wire:click="addLot({{ $index }})">{{ __('Add batch') }}</flux:button>
                        @endunless
                    </div>
                    @error('lines.'.$index.'.lots') <p class="text-xs text-red-600">{{ $message }}</p> @enderror
                    @foreach ($line['lots'] as $lotIndex => $lot)
                        <div class="grid gap-2 md:grid-cols-12" wire:key="lot-{{ $lot['key'] }}">
                            <div class="md:col-span-5">
                                <flux:input wire:model="lines.{{ $index }}.lots.{{ $lotIndex }}.lot_number" :label="$lotIndex === 0 ? __('Lot number') : null" :placeholder="__('Unique lot number')" :disabled="$locked" />
                                @error('lines.'.$index.'.lots.'.$lotIndex.'.lot_number') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                            </div>
                            <div class="md:col-span-3">
                                <flux:input wire:model="lines.{{ $index }}.lots.{{ $lotIndex }}.qty" type="number" step="0.001" min="0" :label="$lotIndex === 0 ? __('Lot qty') : null" :disabled="$locked" />
                            </div>
                            <div class="flex items-end md:col-span-2">
                                @unless ($locked)
                                    <flux:button type="button" variant="ghost" icon="trash" wire:click="removeLot({{ $index }}, {{ $lotIndex }})" />
                                @endunless
                            </div>
                        </div>
                    @endforeach
                </div>
            </section>
        @endforeach

        <div class="flex justify-end gap-2">
            <flux:button variant="ghost" :href="route('admin::quality.purchase.invoices')" wire:navigate>{{ __('Cancel') }}</flux:button>
            @unless ($locked)
                <flux:button type="submit" variant="primary" wire:loading.attr="disabled">{{ __('Save invoice') }}</flux:button>
            @endunless
        </div>
    </form>
</div>
