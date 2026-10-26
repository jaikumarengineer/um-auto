<?php

use App\Concerns\NotifiesWithSweetAlert;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Modules\Quality\Models\ApprovedVendor;
use Modules\Quality\Models\IncomingPart;
use Modules\Quality\Models\PurchaseOrder;
use Modules\Quality\Services\QualityWorkflow;

new #[Layout('core::layouts.app')] class extends Component {
    use NotifiesWithSweetAlert;

    public ?int $orderId = null;

    public string $po_number = '';

    public string $vendor_id = '';

    public string $po_date = '';

    public string $expected_date = '';

    public string $status = 'open';

    public string $currency = 'INR';

    public string $remarks = '';

    public array $lines = [];

    public function mount(?int $order = null): void
    {
        $this->po_date = now()->toDateString();
        $this->addLine();

        if (! $order) {
            return;
        }

        $model = PurchaseOrder::query()->with('lines')->findOrFail($order);
        $this->orderId = $model->id;
        $this->po_number = $model->po_number;
        $this->vendor_id = (string) $model->vendor_id;
        $this->po_date = $model->po_date?->toDateString() ?? '';
        $this->expected_date = $model->expected_date?->toDateString() ?? '';
        $this->status = $model->status;
        $this->currency = $model->currency;
        $this->remarks = (string) $model->remarks;
        $this->lines = $model->lines->map(fn ($line) => [
            'key' => (string) Str::uuid(),
            'id' => $line->id,
            'part_id' => (string) $line->part_id,
            'ordered_qty' => (float) $line->ordered_qty,
            'uom' => $line->uom,
            'unit_price' => (float) $line->unit_price,
        ])->all();
    }

    public function addLine(): void
    {
        $this->lines[] = [
            'key' => (string) Str::uuid(),
            'id' => null,
            'part_id' => '',
            'ordered_qty' => '',
            'uom' => 'PCS',
            'unit_price' => '',
        ];
    }

    public function removeLine(int $index): void
    {
        unset($this->lines[$index]);
        $this->lines = array_values($this->lines);

        if ($this->lines === []) {
            $this->addLine();
        }
    }

    public function updatedLines(mixed $value, string $key): void
    {
        if (! str_ends_with($key, '.part_id') || $value === '') {
            return;
        }

        $index = (int) explode('.', $key)[0];
        $part = IncomingPart::query()->find($value);

        if ($part) {
            $this->lines[$index]['uom'] = $part->uom;
        }
    }

    public function save(QualityWorkflow $workflow): void
    {
        $this->validate([
            'po_number' => ['required', 'string', 'max:50', Rule::unique('purchase_orders', 'po_number')->ignore($this->orderId)],
            'vendor_id' => ['required', 'exists:approved_vendors,id'],
            'po_date' => ['required', 'date'],
            'expected_date' => ['nullable', 'date'],
            'status' => ['required', 'in:draft,open,partial,closed,cancelled'],
            'currency' => ['required', 'string', 'max:8'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.part_id' => ['required', 'exists:incoming_parts,id'],
            'lines.*.ordered_qty' => ['required', 'numeric', 'gt:0'],
            'lines.*.unit_price' => ['required', 'numeric', 'gte:0'],
            'lines.*.uom' => ['required', 'string', 'max:20'],
        ]);

        $order = $this->orderId ? PurchaseOrder::query()->findOrFail($this->orderId) : null;

        $workflow->savePurchaseOrder([
            'po_number' => $this->po_number,
            'vendor_id' => $this->vendor_id,
            'po_date' => $this->po_date,
            'expected_date' => $this->expected_date,
            'status' => $this->status,
            'currency' => $this->currency,
            'remarks' => $this->remarks,
        ], $this->lines, $order);

        session()->flash('success', __('Purchase order saved.'));
        $this->redirectRoute('admin::quality.purchase.orders', navigate: true);
    }

    public function with(): array
    {
        return [
            'vendors' => ApprovedVendor::query()->where('status', 'approved')->orderBy('name')->get(),
            'parts' => IncomingPart::query()->where('is_active', true)->orderBy('part_number')->get(),
        ];
    }
}; ?>

<div>
    <x-core::ui.page-header
        :title="$orderId ? __('Edit purchase order') : __('New purchase order')"
        :subtitle="__('Partial and closed follow the invoiced quantity.')"
        :breadcrumbs="[
            admin_breadcrumb_home(),
            ['label' => __('Purchase orders'), 'route' => 'admin::quality.purchase.orders'],
            ['label' => $orderId ? $po_number : __('Create')],
        ]"
    />

    <form wire:submit="save" class="space-y-4">
        <div class="admin-card grid gap-4 p-4 md:grid-cols-2 xl:grid-cols-3">
            <flux:input wire:model="po_number" :label="__('PO number')" required />
            <x-core::forms.select2
                wire:model="vendor_id"
                :label="__('Approved vendor')"
                :options="$vendors->mapWithKeys(fn ($vendor) => [$vendor->id => $vendor->code.' · '.$vendor->name])"
                :placeholder="__('Select')"
                :allow-clear="false"
            />
            <flux:input wire:model="currency" :label="__('Currency')" />
            <flux:input wire:model="po_date" type="date" :label="__('PO date')" required />
            <flux:input wire:model="expected_date" type="date" :label="__('Expected date')" />
            <x-core::forms.select2
                wire:model="status"
                :label="__('Status')"
                :options="collect(['draft', 'open', 'partial', 'closed', 'cancelled'])->mapWithKeys(fn ($option) => [$option => (string) str($option)->title()])"
                :allow-clear="false"
            />
            <div class="md:col-span-2 xl:col-span-3">
                <flux:textarea wire:model="remarks" :label="__('Remarks')" rows="2" />
            </div>
        </div>

        <div class="admin-card p-4">
            <div class="mb-3 flex items-center justify-between">
                <flux:heading size="lg">{{ __('Lines') }}</flux:heading>
                <flux:button type="button" variant="ghost" icon="plus" wire:click="addLine">{{ __('Add line') }}</flux:button>
            </div>
            @error('lines') <p class="mb-3 text-sm text-red-600">{{ $message }}</p> @enderror
            <div class="space-y-3">
                @foreach ($lines as $index => $line)
                    <div class="grid gap-3 rounded-2xl border border-zinc-200 p-3 dark:border-zinc-700 md:grid-cols-12" wire:key="line-{{ $line['key'] }}">
                        <div class="md:col-span-5">
                            <x-core::forms.select2
                                wire:key="po-part-{{ $line['key'] }}"
                                wire:model.live="lines.{{ $index }}.part_id"
                                :label="__('Part')"
                                :options="$parts->mapWithKeys(fn ($part) => [$part->id => $part->part_number.' · '.$part->name])"
                                :placeholder="__('Select')"
                                :allow-clear="false"
                            />
                        </div>
                        <div class="md:col-span-2">
                            <flux:input wire:model="lines.{{ $index }}.ordered_qty" type="number" step="0.001" min="0" :label="__('Qty')" />
                        </div>
                        <div class="md:col-span-1">
                            <flux:input wire:model="lines.{{ $index }}.uom" :label="__('UOM')" />
                        </div>
                        <div class="md:col-span-2">
                            <flux:input wire:model="lines.{{ $index }}.unit_price" type="number" step="0.01" min="0" :label="__('Rate')" />
                        </div>
                        <div class="flex items-end md:col-span-2">
                            <flux:button type="button" variant="ghost" icon="trash" wire:click="removeLine({{ $index }})">{{ __('Remove') }}</flux:button>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

        <div class="flex justify-end gap-2">
            <flux:button variant="ghost" :href="route('admin::quality.purchase.orders')" wire:navigate>{{ __('Cancel') }}</flux:button>
            <flux:button type="submit" variant="primary" wire:loading.attr="disabled">{{ __('Save purchase order') }}</flux:button>
        </div>
    </form>
</div>
