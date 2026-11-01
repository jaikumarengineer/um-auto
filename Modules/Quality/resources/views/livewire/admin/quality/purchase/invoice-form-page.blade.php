<?php

use App\Concerns\AuthorizesModule;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Modules\Quality\Models\PurchaseInvoice;
use Modules\Quality\Models\PurchaseOrder;
use Modules\Quality\Services\QualityWorkflow;
use Modules\Quality\Support\Qty;

new #[Layout('core::layouts.app')] class extends Component {
    use AuthorizesModule;

    private const MODULE = 'quality.purchase.invoices';

    public ?int $invoiceId = null;

    public bool $locked = false;

    public string $invoice_number = '';

    public string $purchase_order_id = '';

    public string $invoice_date = '';

    public string $remarks = '';

    public array $lines = [];

    public function mount(?int $invoice = null): void
    {
        $this->authorizeModule(self::MODULE, $invoice ? 'edit' : 'add');

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

        $this->syncInvoicedQty($index);
    }

    public function updated($name, $value): void
    {
        if (preg_match('/^lines\.(\d+)\.lots\.\d+\.qty$/', (string) $name, $matches)) {
            $this->syncInvoicedQty((int) $matches[1]);
        }
    }

    public function save(QualityWorkflow $workflow): void
    {
        if ($this->locked) {
            return;
        }

        foreach (array_keys($this->lines) as $index) {
            $this->syncInvoicedQty((int) $index);
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
        $orders = PurchaseOrder::query()
            ->with('vendor')
            ->where(function ($query) {
                $query->whereIn('status', ['open', 'partial', 'closed']);

                if ($this->purchase_order_id !== '') {
                    $query->orWhere('id', $this->purchase_order_id);
                }
            })
            ->orderByDesc('po_date')
            ->get();

        $selectedOrder = null;

        if ($this->purchase_order_id !== '') {
            $selectedOrder = PurchaseOrder::query()
                ->with(['vendor', 'lines.invoiceLines'])
                ->find($this->purchase_order_id);
        }

        return [
            'orders' => $orders,
            'selectedOrder' => $selectedOrder,
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
        $part = $line->part;

        return [
            'key' => (string) Str::uuid(),
            'purchase_order_line_id' => $line->id,
            'part_number' => (string) ($part?->part_number ?? ''),
            'part_name' => (string) ($part?->name ?? ''),
            'part_label' => $part?->label() ?? __('Unknown part'),
            'uom' => $line->uom,
            'unit_price' => (float) ($line->unit_price ?? 0),
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

    private function syncInvoicedQty(int $index): void
    {
        if (! isset($this->lines[$index])) {
            return;
        }

        $total = collect($this->lines[$index]['lots'] ?? [])
            ->sum(fn (array $lot) => Qty::num($lot['qty'] ?? 0));

        $this->lines[$index]['invoiced_qty'] = $total > 0 ? $total : '';
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
        <div class="admin-card grid gap-4 p-4 md:grid-cols-2 xl:grid-cols-3">
            <flux:input wire:model="invoice_number" :label="__('Invoice number')" required :disabled="$locked" />
            <div class="min-w-0">
                <x-core::forms.select2
                    wire:model.live="purchase_order_id"
                    :label="__('Purchase order')"
                    :options="$orders->mapWithKeys(fn ($order) => [$order->id => $order->po_number.' · '.($order->vendor?->optionLabel() ?? '—')])"
                    :placeholder="__('Select')"
                    :allow-clear="false"
                    :disabled="$locked || (bool) $invoiceId"
                />
            </div>
            <flux:input wire:model="invoice_date" type="date" :label="__('Invoice date')" required :disabled="$locked" />
            <div class="md:col-span-2 xl:col-span-3">
                <flux:textarea wire:model="remarks" :label="__('Remarks')" rows="2" :disabled="$locked" />
            </div>
        </div>

        @if ($selectedOrder)
            <div class="admin-card grid gap-4 p-4 sm:grid-cols-2 xl:grid-cols-4" wire:key="po-summary-{{ $selectedOrder->id }}">
                <div>
                    <p class="text-xs font-medium text-zinc-500">{{ __('Purchase order') }}</p>
                    <p class="mt-1 font-semibold tabular-nums text-zinc-900 dark:text-zinc-100">{{ $selectedOrder->po_number }}</p>
                </div>
                <div>
                    <p class="text-xs font-medium text-zinc-500">{{ __('Vendor') }}</p>
                    <p class="mt-1 font-medium text-zinc-900 dark:text-zinc-100">
                        {{ $selectedOrder->vendor?->optionLabel() ?? '—' }}
                    </p>
                </div>
                <div>
                    <p class="text-xs font-medium text-zinc-500">{{ __('PO date') }}</p>
                    <p class="mt-1 text-zinc-900 dark:text-zinc-100">{{ $selectedOrder->po_date?->format('Y-m-d') ?? '—' }}</p>
                </div>
                <div>
                    <p class="text-xs font-medium text-zinc-500">{{ __('Expected date') }}</p>
                    <p class="mt-1 text-zinc-900 dark:text-zinc-100">
                        {{ $selectedOrder->expected_date?->format('Y-m-d') ?? '—' }}
                        @if ($selectedOrder->isOverdue())
                            <span class="ml-1 text-xs font-semibold text-red-600 dark:text-red-400">{{ __('Overdue') }}</span>
                        @endif
                    </p>
                </div>
                <div>
                    <p class="text-xs font-medium text-zinc-500">{{ __('Status') }}</p>
                    <p class="mt-1 capitalize text-zinc-900 dark:text-zinc-100">{{ $selectedOrder->status }}</p>
                </div>
                <div>
                    <p class="text-xs font-medium text-zinc-500">{{ __('Currency') }}</p>
                    <p class="mt-1 text-zinc-900 dark:text-zinc-100">{{ $selectedOrder->currency ?: '—' }}</p>
                </div>
                <div class="sm:col-span-2">
                    <p class="text-xs font-medium text-zinc-500">{{ __('Quantity') }}</p>
                    <p class="mt-1 tabular-nums text-zinc-900 dark:text-zinc-100">
                        {{ __('Ordered :ordered · invoiced :invoiced · balance :balance', [
                            'ordered' => Qty::format($selectedOrder->orderedQuantity()),
                            'invoiced' => Qty::format($selectedOrder->invoicedQuantity()),
                            'balance' => Qty::format($selectedOrder->balanceQuantity()),
                        ]) }}
                    </p>
                </div>
                @if (filled($selectedOrder->remarks))
                    <div class="sm:col-span-2 xl:col-span-4">
                        <p class="text-xs font-medium text-zinc-500">{{ __('PO remarks') }}</p>
                        <p class="mt-1 whitespace-pre-wrap text-sm text-zinc-700 dark:text-zinc-300">{{ $selectedOrder->remarks }}</p>
                    </div>
                @endif
            </div>
        @endif

        @error('lines') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
        @error('purchase_order_id') <p class="text-sm text-red-600">{{ $message }}</p> @enderror

        <div class="space-y-4" wire:key="invoice-parts-{{ $purchase_order_id ?: 'none' }}">
            @if ($purchase_order_id === '')
                <div class="admin-card border-dashed p-6 text-center text-sm text-zinc-500">
                    {{ __('Select a purchase order to load its parts.') }}
                </div>
            @elseif ($lines === [])
                <div class="admin-card border-dashed p-6 text-center text-sm text-zinc-500">
                    {{ __('This purchase order has no parts yet.') }}
                </div>
            @else
                <div class="flex items-center justify-between gap-2 px-1">
                    <h2 class="text-sm font-semibold text-zinc-800 dark:text-zinc-100">
                        {{ __('Parts on this purchase order') }}
                        <span class="font-normal text-zinc-500">({{ count($lines) }})</span>
                    </h2>
                </div>

                @foreach ($lines as $index => $line)
                    @php
                        $current = collect($line['lots'] ?? [])->sum(fn ($lot) => Qty::num($lot['qty'] ?? 0));
                        $open = max(0, (float) $line['ordered_qty'] - (float) $line['already_qty'] - $current);
                    @endphp
                    <section class="admin-card p-4" wire:key="invoice-line-{{ $line['key'] }}">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div>
                                <p class="text-xs uppercase tracking-wide text-zinc-500">{{ __('Part') }}</p>
                                <p class="font-medium">
                                    @if (($line['part_number'] ?? '') !== '')
                                        <span class="tabular-nums">{{ $line['part_number'] }}</span>
                                        <span class="text-zinc-400">·</span>
                                        {{ $line['part_name'] ?: $line['part_label'] }}
                                    @else
                                        {{ $line['part_label'] }}
                                    @endif
                                </p>
                                <p class="mt-1 text-xs text-zinc-500">
                                    {{ __('Ordered :ordered · other invoices :already · still open :open :uom', [
                                        'ordered' => Qty::format($line['ordered_qty']),
                                        'already' => Qty::format($line['already_qty']),
                                        'open' => Qty::format($open),
                                        'uom' => $line['uom'],
                                    ]) }}
                                    @if (($line['unit_price'] ?? 0) > 0)
                                        <span class="text-zinc-400">·</span>
                                        {{ __('Rate :rate', [
                                            'rate' => number_format((float) $line['unit_price'], 2).($selectedOrder?->currency ? ' '.$selectedOrder->currency : ''),
                                        ]) }}
                                    @endif
                                </p>
                            </div>
                            <div class="min-w-28 text-right">
                                <p class="font-bold">{{ __('Total Lot Qty') }}</p>
                                <p class="mt-1 text-lg font-semibold tabular-nums">{{ Qty::format($current) }} <span class="text-sm font-normal text-zinc-500">{{ $line['uom'] }}</span></p>
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
                                        <flux:input wire:model.live="lines.{{ $index }}.lots.{{ $lotIndex }}.qty" type="number" step="0.001" min="0" :label="$lotIndex === 0 ? __('Lot qty') : null" :disabled="$locked" />
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
            @endif
        </div>

        <div class="flex justify-end gap-2">
            <flux:button variant="ghost" :href="route('admin::quality.purchase.invoices')" wire:navigate>{{ __('Cancel') }}</flux:button>
            @unless ($locked)
                <flux:button type="submit" variant="primary" wire:loading.attr="disabled" wire:target="save">{{ __('Save invoice') }}</flux:button>
            @endunless
        </div>
    </form>
</div>
