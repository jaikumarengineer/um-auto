<?php

use App\Concerns\AuthorizesModule;
use App\Concerns\NotifiesWithSweetAlert;
use App\Concerns\WithSorting;
use App\Services\RecordApproval;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Livewire\WithPagination;
use Modules\Quality\Models\PurchaseInvoice;
use Modules\Quality\Services\QualityWorkflow;
use Modules\Quality\Support\Qty;

new #[Layout('core::layouts.app')] class extends Component {
    use AuthorizesModule;
    use NotifiesWithSweetAlert;
    use WithPagination;
    use WithSorting;

    private const MODULE = 'quality.purchase.invoices';

    public string $search = '';

    public int $perPage = 10;

    public string $sortBy = 'invoice_date';

    public string $sortDirection = 'desc';

    protected array $sortable = ['invoice_number', 'invoice_date', 'status', 'id'];

    public bool $showAcceptedStatusModal = false;

    public ?int $acceptedStatusInvoiceId = null;

    public string $acceptedStatus = RecordApproval::PENDING;

    public string $acceptedStatusReason = '';

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

    public function openAcceptedStatus(int $id): void
    {
        abort_unless(auth()->user()?->canModule('quality.approvals', 'edit'), 403);

        $invoice = PurchaseInvoice::query()->findOrFail($id);
        $this->acceptedStatusInvoiceId = $invoice->id;
        $this->acceptedStatus = (string) ($invoice->approval_status ?: RecordApproval::PENDING);
        $this->acceptedStatusReason = (string) ($invoice->rejection_reason ?? '');
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
            $invoice = PurchaseInvoice::query()->findOrFail($this->acceptedStatusInvoiceId);
            app(QualityWorkflow::class)->updatePurchaseInvoiceAcceptedStatus(
                $invoice,
                $this->acceptedStatus,
                $this->acceptedStatusReason ?: null,
            );
            $this->showAcceptedStatusModal = false;
            $this->notifySuccess(__('Accepted status updated.'));
        } catch (ValidationException $exception) {
            $this->notifyError($exception->validator->errors()->first() ?: __('Could not update accepted status.'));
        }
    }

    public function deleteInvoice(int $id): void
    {
        $this->authorizeModule(self::MODULE, 'delete');

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
                    ->with(['vendor', 'purchaseOrder', 'lines.lots', 'creator', 'approver'])
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
            'canView' => module_can(self::MODULE, 'view'),
            'canAdd' => module_can(self::MODULE, 'add'),
            'canEdit' => module_can(self::MODULE, 'edit'),
            'canDelete' => module_can(self::MODULE, 'delete'),
            'canEditAcceptedStatus' => module_can('quality.approvals', 'edit'),
            'acceptedStatusLabels' => RecordApproval::statusLabels(),
        ];
    }
}; ?>

<div>
    <x-core::ui.page-header
        :title="__('Purchase invoices')"
        :subtitle="__('Showing the list of purchase invoices')"
        :breadcrumbs="[
            admin_breadcrumb_home(),
            ['label' => __('Purchase invoices')],
        ]"
    >
        <x-slot:actions>
            @if ($canAdd)
                <flux:button variant="primary" icon="plus" icon:variant="outline" :href="route('admin::quality.purchase.invoices.create')" wire:navigate>
                    {{ __('Book invoice') }}
                </flux:button>
            @endif
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
                    <th class="px-4 py-3 text-left text-xs font-semibold text-zinc-500">{{ __('Batch / lot') }}</th>
                    <x-core::ui.sortable-th column="invoice_number" :label="__('Purchase Invoice')" />
                    <th class="px-4 py-3 text-left text-xs font-semibold text-zinc-500">{{ __('Purchase order') }}</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-zinc-500">{{ __('Vendor') }}</th>
                    <x-core::ui.sortable-th column="invoice_date" :label="__('Date')" />
                    <th class="px-4 py-3 text-left text-xs font-semibold text-zinc-500">{{ __('Qty') }}</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-zinc-500">{{ __('Remarks') }}</th>
                    <x-core::ui.sortable-th column="status" :label="__('Status')" />
                    <th class="px-4 py-3 text-left text-xs font-semibold text-zinc-500">{{ __('Accepted Status') }}</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-zinc-500">{{ __('Created by') }}</th>
                    <th class="px-4 py-3 text-right text-xs font-semibold text-zinc-500">{{ __('Actions') }}</th>
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
                        <x-core::ui.long-text-cell :value="$invoice->remarks" />
                        <td class="px-4 py-3"><x-quality::status-pill :status="$invoice->status" /></td>
                        <td class="px-4 py-3">
                            <div class="flex items-center gap-1.5">
                                <x-quality::status-pill :status="$invoice->approval_status ?? 'approved'" />
                                @if ($canEditAcceptedStatus)
                                    <button
                                        type="button"
                                        class="rounded p-1 text-zinc-400 hover:bg-zinc-100 hover:text-zinc-700 dark:hover:bg-zinc-800"
                                        wire:click="openAcceptedStatus({{ $invoice->id }})"
                                        title="{{ __('Edit accepted status') }}"
                                    >
                                        <flux:icon name="pencil-square" class="size-4" />
                                    </button>
                                @endif
                            </div>
                        </td>
                        <td class="px-4 py-3">
                            <x-quality::user-avatars :users="[$invoice->creator, $invoice->approver]" />
                        </td>
                        <td class="px-4 py-3 text-right">
                            @if ($canView || $canEdit || $canDelete)
                                <x-core::ui.actions-menu>
                                    @if ($canView)
                                        <flux:menu.item icon="eye" icon:variant="outline" :href="route('admin::quality.purchase.invoices.show', $invoice)" wire:navigate>
                                            {{ __('View') }}
                                        </flux:menu.item>
                                        <flux:menu.item icon="document-text" icon:variant="outline" :href="route('admin::quality.purchase.invoices.format', $invoice)">
                                            {{ __('Format') }}
                                        </flux:menu.item>
                                    @endif
                                    @if ($canEdit)
                                        <flux:menu.item icon="pencil-square" icon:variant="outline" :href="route('admin::quality.purchase.invoices.edit', $invoice)" wire:navigate>
                                            {{ __('Edit') }}
                                        </flux:menu.item>
                                    @endif
                                    @if ($canDelete)
                                        <flux:menu.separator />
                                        <x-core::ui.confirm-button as="menu-item" method="deleteInvoice" :params="[$invoice->id]" />
                                    @endif
                                </x-core::ui.actions-menu>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="11" class="px-4 py-12 text-center text-sm text-zinc-500">{{ __('No purchase invoices yet.') }}</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <x-core::ui.pagination :paginator="$invoices" />

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
