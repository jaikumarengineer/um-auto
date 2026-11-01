<?php

use App\Concerns\AuthorizesModule;
use App\Concerns\NotifiesWithSweetAlert;
use App\Concerns\WithSorting;
use App\Livewire\Forms\CountryForm;
use App\Models\Country;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new #[Layout('core::layouts.app')] class extends Component {
    use AuthorizesModule;
    use NotifiesWithSweetAlert;
    use WithPagination;
    use WithSorting;

    private const MODULE = 'grouping';

    public CountryForm $form;

    public string $search = '';

    public string $statusFilter = '';

    public int $perPage = 15;

    public string $sortBy = 'nicename';

    public string $sortDirection = 'asc';

    public bool $showEditFlyout = false;

    protected array $sortable = [
        'id',
        'iso',
        'name',
        'nicename',
        'iso3',
        'phonecode',
        'timezone',
        'status',
        'created_at',
    ];

    public function mount(): void
    {
        $this->search = (string) request('search', '');
    }

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

    public function clearFilters(): void
    {
        $this->reset(['search', 'statusFilter']);
        $this->resetPage();
    }

    public function editCountry(int $id): void
    {
        $this->authorizeModule(self::MODULE, 'edit');

        $this->form->setCountry(Country::query()->findOrFail($id));
        $this->form->resetValidation();
        $this->showEditFlyout = true;
    }

    public function closeEditFlyout(): void
    {
        $this->showEditFlyout = false;
        $this->form->reset();
        $this->form->resetValidation();
    }

    public function updatedShowEditFlyout(bool $value): void
    {
        if (! $value) {
            $this->form->reset();
            $this->form->resetValidation();
        }
    }

    public function saveCountry(): void
    {
        $this->form->update();
        $this->showEditFlyout = false;
        $this->form->reset();
        $this->notifySuccess(__('Country updated successfully.'));
    }

    public function toggleStatus(int $id): void
    {
        $this->authorizeModule(self::MODULE, 'edit');

        $country = Country::query()->findOrFail($id);
        $country->update(['status' => ! $country->status]);
        $this->notifySuccess(__('Country status updated.'));
    }

    public function deleteCountry(int $id): void
    {
        $this->authorizeModule(self::MODULE, 'delete');

        Country::query()->findOrFail($id)->delete();
        $this->notifySuccess(__('Country deleted successfully.'));
    }

    public function with(): array
    {
        $countries = $this->applySorting(
            Country::query()
                ->when($this->search, function ($query) {
                    $term = '%'.$this->search.'%';

                    $query->where(function ($query) use ($term) {
                        $query->where('name', 'like', $term)
                            ->orWhere('nicename', 'like', $term)
                            ->orWhere('iso', 'like', $term)
                            ->orWhere('iso3', 'like', $term)
                            ->orWhere('phonecode', 'like', $term)
                            ->orWhere('timezone', 'like', $term);
                    });
                })
                ->when($this->statusFilter !== '', fn ($query) => $query->where('status', (bool) $this->statusFilter))
        )->paginate($this->perPage);

        return [
            'countries' => $countries,
            'canView' => module_can(self::MODULE, 'view'),
            'canAdd' => module_can(self::MODULE, 'add'),
            'canEdit' => module_can(self::MODULE, 'edit'),
            'canDelete' => module_can(self::MODULE, 'delete'),
        ];
    }
}; ?>

<div>
    <x-core::ui.page-header
        class="admin-fade-in"
        :title="__('Countries')"
        :subtitle="__('Showing the list of countries')"
        :breadcrumbs="[
            ['label' => __('Dashboard'), 'route' => 'admin::dashboard'],
            ['label' => __('Grouping')],
            ['label' => __('Countries')],
        ]"
    />

    {{-- Filters --}}
    <div class="mb-4 admin-card p-3 admin-fade-in admin-fade-in-delay-1">
        <div class="flex flex-col gap-3 lg:flex-row lg:items-center">
            <div class="min-w-0 flex-1">
                <flux:input
                    wire:model.live.debounce.300ms="search"
                    placeholder="{{ __('Search by name, ISO, phone, or timezone…') }}"
                    icon="magnifying-glass"
                />
            </div>

            <div class="grid grid-cols-2 gap-2 sm:flex sm:shrink-0 sm:items-center">
                <flux:select wire:model.live="statusFilter" class="w-full sm:w-36">
                    <flux:select.option value="">{{ __('All Status') }}</flux:select.option>
                    <flux:select.option value="1">{{ __('Active') }}</flux:select.option>
                    <flux:select.option value="0">{{ __('Inactive') }}</flux:select.option>
                </flux:select>

                <flux:select wire:model.live="perPage" class="w-full sm:w-32">
                    <flux:select.option value="15">15 / page</flux:select.option>
                    <flux:select.option value="25">25 / page</flux:select.option>
                    <flux:select.option value="50">50 / page</flux:select.option>
                    <flux:select.option value="100">100 / page</flux:select.option>
                </flux:select>
            </div>
        </div>
    </div>

    {{-- Table --}}
    <div class="overflow-x-auto overflow-hidden admin-card admin-fade-in admin-fade-in-delay-2">
        <table class="min-w-full divide-y divide-zinc-200 dark:divide-zinc-700">
            <thead class="bg-[var(--admin-surface-muted)]">
                <tr>
                    <x-core::ui.sortable-th column="id" label="#" />
                    <x-core::ui.sortable-th column="iso" :label="__('ISO')" />
                    <x-core::ui.sortable-th column="nicename" :label="__('Country')" />
                    <x-core::ui.sortable-th column="phonecode" :label="__('Phone')" />
                    <x-core::ui.sortable-th column="timezone" :label="__('Timezone')" />
                    <x-core::ui.sortable-th column="status" :label="__('Status')" align="center" />
                    <th class="px-4 py-3 text-right text-xs font-semibold text-zinc-500 dark:text-zinc-400">
                        {{ __('Actions') }}
                    </th>
                </tr>
            </thead>
            <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                @forelse ($countries as $country)
                    <tr class="transition-colors hover:bg-zinc-50 dark:hover:bg-zinc-800/40" wire:key="country-{{ $country->id }}">
                        <td class="px-4 py-3 text-sm text-zinc-500 dark:text-zinc-400">{{ $country->id }}</td>
                        <td class="px-4 py-3">
                            <span class="inline-flex items-center rounded-md bg-zinc-100 px-2 py-0.5 font-mono text-xs font-semibold text-zinc-700 dark:bg-zinc-700 dark:text-zinc-200">
                                {{ $country->iso }}
                            </span>
                            @if ($country->iso3)
                                <span class="ms-1 text-xs text-zinc-400">{{ $country->iso3 }}</span>
                            @endif
                        </td>
                        <td class="px-4 py-3">
                            <p class="text-sm font-medium">{{ $country->nicename }}</p>
                            <p class="text-xs text-zinc-500 dark:text-zinc-400">{{ $country->name }}</p>
                        </td>
                        <td class="px-4 py-3 text-sm text-zinc-700 dark:text-zinc-300">
                            @if ($country->phonecode)
                                +{{ $country->phonecode }}
                            @else
                                <span class="text-zinc-400">—</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-sm text-zinc-700 dark:text-zinc-300">
                            <p>{{ $country->timezone ?: '—' }}</p>
                            @if ($country->utc)
                                <p class="text-xs text-zinc-500 dark:text-zinc-400">{{ $country->utc }}</p>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-center">
                            @if ($canEdit)
                                <button type="button" wire:click="toggleStatus({{ $country->id }})" class="focus:outline-none" title="{{ __('Toggle status') }}">
                                    @if ($country->status)
                                        <span class="inline-flex items-center gap-1 rounded-full bg-green-100 px-2.5 py-0.5 text-xs font-medium text-green-700 dark:bg-green-900/40 dark:text-green-300">
                                            <span class="h-1.5 w-1.5 rounded-full bg-green-500"></span> {{ __('Active') }}
                                        </span>
                                    @else
                                        <span class="inline-flex items-center gap-1 rounded-full bg-zinc-100 px-2.5 py-0.5 text-xs font-medium text-zinc-500 dark:bg-zinc-700 dark:text-zinc-400">
                                            <span class="h-1.5 w-1.5 rounded-full bg-zinc-400"></span> {{ __('Inactive') }}
                                        </span>
                                    @endif
                                </button>
                            @else
                                @if ($country->status)
                                    <span class="inline-flex items-center gap-1 rounded-full bg-green-100 px-2.5 py-0.5 text-xs font-medium text-green-700 dark:bg-green-900/40 dark:text-green-300">
                                        <span class="h-1.5 w-1.5 rounded-full bg-green-500"></span> {{ __('Active') }}
                                    </span>
                                @else
                                    <span class="inline-flex items-center gap-1 rounded-full bg-zinc-100 px-2.5 py-0.5 text-xs font-medium text-zinc-500 dark:bg-zinc-700 dark:text-zinc-400">
                                        <span class="h-1.5 w-1.5 rounded-full bg-zinc-400"></span> {{ __('Inactive') }}
                                    </span>
                                @endif
                            @endif
                        </td>
                        <td class="px-4 py-3 text-right">
                            <div class="flex items-center justify-end">
                                @if ($canEdit || $canDelete)
                                    <x-core::ui.actions-menu>
                                        @if ($canEdit)
                                            <flux:menu.item
                                                as="button"
                                                type="button"
                                                icon="pencil-square"
                                                icon:variant="outline"
                                                wire:click="editCountry({{ $country->id }})"
                                            >
                                                {{ __('Edit') }}
                                            </flux:menu.item>

                                            <flux:menu.item
                                                as="button"
                                                type="button"
                                                :icon="$country->status ? 'no-symbol' : 'check-circle'"
                                                icon:variant="outline"
                                                wire:click="toggleStatus({{ $country->id }})"
                                            >
                                                {{ $country->status ? __('Deactivate') : __('Activate') }}
                                            </flux:menu.item>
                                        @endif

                                        @if ($canDelete)
                                            @if ($canEdit)
                                                <flux:menu.separator />
                                            @endif

                                            <x-core::ui.confirm-button
                                                as="menu-item"
                                                method="deleteCountry"
                                                :params="[$country->id]"
                                                :title="__('Are you sure?')"
                                                :text="__('Once deleted, you will not be able to recover this country.')"
                                                :confirm-text="__('Yes, delete it!')"
                                                :cancel-text="__('Cancel')"
                                                :menu-label="__('Delete')"
                                            />
                                        @endif
                                    </x-core::ui.actions-menu>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-4 py-12 text-center">
                            <div class="flex flex-col items-center gap-2 text-zinc-400 dark:text-zinc-500">
                                <flux:icon.globe-alt class="h-10 w-10 opacity-40" />
                                <p class="text-sm">{{ __('No countries found.') }}</p>
                                @if ($search || $statusFilter !== '')
                                    <flux:button variant="ghost" size="sm" wire:click="clearFilters">
                                        {{ __('Clear filters') }}
                                    </flux:button>
                                @endif
                            </div>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <x-core::ui.pagination :paginator="$countries" />

    {{-- Edit flyout --}}
    <flux:modal
        wire:model="showEditFlyout"
        variant="flyout"
        class="md:min-w-[28rem] lg:min-w-[32rem] !rounded-s-3xl !ps-8 !pe-8 dark:!bg-[var(--admin-surface)]"
    >
        <form wire:submit="saveCountry" class="flex h-full flex-col gap-6">
            <div class="pe-8">
                <flux:heading size="lg">{{ __('Edit country') }}</flux:heading>
                <flux:subheading>
                    {{ filled($form->nicename) ? $form->nicename : __('Update country details.') }}
                </flux:subheading>
            </div>

            <div class="flex-1 space-y-4 overflow-y-auto px-1 pe-2">
                <div class="grid grid-cols-2 gap-3">
                    <flux:input wire:model="form.iso" :label="__('ISO')" maxlength="2" required />
                    <flux:input wire:model="form.iso3" :label="__('ISO3')" maxlength="3" />
                </div>

                <flux:input wire:model="form.nicename" :label="__('Display name')" required />
                <flux:input wire:model="form.name" :label="__('Official name')" required />

                <div class="grid grid-cols-2 gap-3">
                    <flux:input wire:model="form.phonecode" :label="__('Phone code')" />
                    <flux:input wire:model="form.numcode" type="number" :label="__('Numeric code')" />
                </div>

                <flux:input wire:model="form.region_id" type="number" :label="__('Region ID')" />
                <flux:input wire:model="form.timezone" :label="__('Timezone')" placeholder="Asia/Kolkata" />
                <flux:input wire:model="form.utcname" :label="__('UTC name')" placeholder="(UTC+05:30) Asia/Kolkata" />

                <div class="grid grid-cols-2 gap-3">
                    <flux:input wire:model="form.utc" :label="__('UTC')" placeholder="UTC+05:30" />
                    <flux:input wire:model="form.abbreviations" :label="__('Abbreviation')" />
                </div>

                <flux:switch wire:model="form.status" :label="__('Active')" />
            </div>

            <div class="flex items-center justify-end gap-2 border-t border-zinc-200 pt-4 dark:border-zinc-700">
                <flux:button type="button" variant="ghost" wire:click="closeEditFlyout">
                    {{ __('Cancel') }}
                </flux:button>
                <flux:button type="submit" variant="primary" wire:loading.attr="disabled">
                    {{ __('Save changes') }}
                </flux:button>
            </div>
        </form>
    </flux:modal>
</div>
