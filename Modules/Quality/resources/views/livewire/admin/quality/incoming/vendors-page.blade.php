<?php

use App\Concerns\NotifiesWithSweetAlert;
use App\Concerns\WithSorting;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Livewire\WithPagination;
use Modules\Quality\Models\ApprovedVendor;

new #[Layout('core::layouts.app')] class extends Component {
    use NotifiesWithSweetAlert;
    use WithPagination;
    use WithSorting;

    public string $search = '';

    public string $statusFilter = '';

    public string $sortBy = 'name';

    public string $sortDirection = 'asc';

    protected array $sortable = ['code', 'name', 'contact_name', 'purchase_orders_count', 'status'];

    public bool $showFlyout = false;

    public ?int $vendorId = null;

    public string $code = '';

    public string $name = '';

    public string $contact_name = '';

    public string $email = '';

    public string $phone = '';

    public string $gstin = '';

    public string $address = '';

    public string $status = 'approved';

    public string $notes = '';

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function createVendor(): void
    {
        $this->resetForm();
        $this->showFlyout = true;
    }

    public function editVendor(int $id): void
    {
        $vendor = ApprovedVendor::query()->findOrFail($id);
        $this->vendorId = $vendor->id;
        $this->code = $vendor->code;
        $this->name = $vendor->name;
        $this->contact_name = (string) $vendor->contact_name;
        $this->email = (string) $vendor->email;
        $this->phone = (string) $vendor->phone;
        $this->gstin = (string) $vendor->gstin;
        $this->address = (string) $vendor->address;
        $this->status = $vendor->status;
        $this->notes = (string) $vendor->notes;
        $this->showFlyout = true;
    }

    public function saveVendor(): void
    {
        $this->code = strtoupper(trim($this->code));

        $data = $this->validate([
            'code' => ['required', 'string', 'max:40', 'regex:/^V-\d{4,}$/', Rule::unique('approved_vendors', 'code')->ignore($this->vendorId)],
            'name' => ['required', 'string', 'max:160'],
            'contact_name' => ['nullable', 'string', 'max:160'],
            'email' => ['nullable', 'email', 'max:160'],
            'phone' => ['nullable', 'string', 'max:40'],
            'gstin' => ['nullable', 'string', 'max:20'],
            'address' => ['nullable', 'string'],
            'status' => ['required', 'in:approved,pending,blocked'],
            'notes' => ['nullable', 'string'],
        ], [
            'code.regex' => __('Vendor code must look like V-1001.'),
            'code.unique' => __('This vendor code is already used.'),
        ]);

        $data['approved_on'] = $data['status'] === 'approved' ? now()->toDateString() : null;
        $vendor = $this->vendorId ? ApprovedVendor::query()->findOrFail($this->vendorId) : new ApprovedVendor();

        if ($vendor->exists && $vendor->status === 'approved' && $data['status'] === 'approved') {
            $data['approved_on'] = $vendor->approved_on;
        }

        $vendor->fill($data)->save();
        $this->showFlyout = false;
        $this->notifySuccess(__('Vendor saved.'));
    }

    public function deleteVendor(int $id): void
    {
        $vendor = ApprovedVendor::query()->withCount('purchaseOrders')->findOrFail($id);

        if ($vendor->purchase_orders_count > 0) {
            $this->notifyError(__('This vendor is already in use and cannot be deleted.'));

            return;
        }

        $vendor->delete();
        $this->notifySuccess(__('Vendor deleted.'));
    }

    public function with(): array
    {
        return [
            'vendors' => $this->applySorting(
                ApprovedVendor::query()
                    ->withCount('purchaseOrders')
                    ->when($this->search !== '', function ($query) {
                        $term = '%'.$this->search.'%';
                        $query->where(fn ($query) => $query->where('name', 'like', $term)->orWhere('code', 'like', $term)->orWhere('email', 'like', $term));
                    })
                    ->when($this->statusFilter !== '', fn ($query) => $query->where('status', $this->statusFilter))
            )->paginate(12),
        ];
    }

    private function resetForm(): void
    {
        $this->reset(['vendorId', 'code', 'name', 'contact_name', 'email', 'phone', 'gstin', 'address', 'notes']);
        $this->status = 'approved';
        $this->resetValidation();
    }
}; ?>

<div>
    <x-core::ui.page-header
        :title="__('Approved vendors')"
        :subtitle="auth()->user()?->isMaster() ? __('Only approved vendors can be used on a purchase order.') : __('Vendors cleared to supply incoming material.')"
        :breadcrumbs="[
            admin_breadcrumb_home(),
            ['label' => __('Approved vendors')],
        ]"
    >
        <x-slot:actions>
            <flux:button variant="primary" icon="plus" wire:click="createVendor">{{ __('Add vendor') }}</flux:button>
        </x-slot:actions>
    </x-core::ui.page-header>

    <div class="mb-4 admin-card p-3">
        <div class="flex flex-col gap-3 lg:flex-row">
            <div class="min-w-0 flex-1">
                <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" :placeholder="__('Search code or name')" />
            </div>
            <flux:select wire:model.live="statusFilter" class="lg:w-44">
                <flux:select.option value="">{{ __('All statuses') }}</flux:select.option>
                @foreach (['approved', 'pending', 'blocked'] as $status)
                    <flux:select.option :value="$status">{{ str($status)->title() }}</flux:select.option>
                @endforeach
            </flux:select>
        </div>
    </div>

    <div class="overflow-x-auto admin-card">
        <table class="min-w-full divide-y divide-zinc-200 dark:divide-zinc-700">
            <thead class="bg-[var(--admin-surface-muted)]">
                <tr>
                    <x-core::ui.sortable-th column="code" :label="__('Code')" />
                    <x-core::ui.sortable-th column="name" :label="__('Vendor')" />
                    <x-core::ui.sortable-th column="contact_name" :label="__('Contact')" />
                    @if (auth()->user()?->isMaster())
                        <x-core::ui.sortable-th column="purchase_orders_count" :label="__('POs')" />
                    @endif
                    <x-core::ui.sortable-th column="status" :label="__('Status')" />
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                @forelse ($vendors as $vendor)
                    <tr wire:key="vendor-{{ $vendor->id }}">
                        <td class="px-4 py-3 text-sm font-medium">{{ $vendor->code }}</td>
                        <td class="px-4 py-3 text-sm">{{ $vendor->name }}</td>
                        <td class="px-4 py-3 text-sm">{{ $vendor->contact_name }}<br><span class="text-xs text-zinc-500">{{ $vendor->email }}</span></td>
                        @if (auth()->user()?->isMaster())
                            <td class="px-4 py-3 text-sm">{{ $vendor->purchase_orders_count }}</td>
                        @endif
                        <td class="px-4 py-3"><x-quality::status-pill :status="$vendor->status" /></td>
                        <td class="px-4 py-3 text-right">
                            <x-core::ui.actions-menu>
                                <flux:menu.item icon="pencil-square" icon:variant="outline" wire:click="editVendor({{ $vendor->id }})">{{ __('Edit') }}</flux:menu.item>
                                <flux:menu.separator />
                                <x-core::ui.confirm-button as="menu-item" method="deleteVendor" :params="[$vendor->id]" />
                            </x-core::ui.actions-menu>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="{{ auth()->user()?->isMaster() ? 6 : 5 }}" class="px-4 py-12 text-center text-sm text-zinc-500">{{ __('No vendors yet.') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <x-core::ui.pagination :paginator="$vendors" />

    <flux:modal wire:model="showFlyout" variant="flyout" class="md:min-w-[28rem] !rounded-s-3xl !ps-8 !pe-8">
        <form wire:submit="saveVendor" class="flex h-full flex-col gap-4">
            <flux:heading size="lg">{{ $vendorId ? __('Edit vendor') : __('Add vendor') }}</flux:heading>
            <div class="flex-1 space-y-3 pe-2">
                <flux:input wire:model="code" :label="__('Code')" required :placeholder="__('V-1001')" :description="__('Enter a code like V-1001.')" />
                @if ($showFlyout)
                    <x-core::forms.select2
                        wire:key="vendor-status-{{ $vendorId ?? 'new' }}"
                        wire:model="status"
                        :label="__('Status')"
                        :options="collect(['approved', 'pending', 'blocked'])->mapWithKeys(fn ($option) => [$option => (string) str($option)->title()])"
                        :allow-clear="false"
                    />
                @endif
                <flux:input wire:model="name" :label="__('Name')" required />
                <flux:input wire:model="contact_name" :label="__('Contact')" />
                <flux:input wire:model="email" type="email" :label="__('Email')" />
                <flux:input wire:model="phone" :label="__('Phone')" />
                <flux:input wire:model="gstin" :label="__('GSTIN')" />
                <flux:textarea wire:model="address" :label="__('Address')" rows="2" />
                <flux:textarea wire:model="notes" :label="__('Notes')" rows="2" />
            </div>
            <div class="flex justify-end gap-2 border-t border-zinc-200 pt-4 dark:border-zinc-700">
                <flux:button type="button" variant="ghost" wire:click="$set('showFlyout', false)">{{ __('Cancel') }}</flux:button>
                <flux:button type="submit" variant="primary">{{ __('Save') }}</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
