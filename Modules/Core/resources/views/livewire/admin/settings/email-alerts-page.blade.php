<?php

use App\Facades\Setting;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Modules\Quality\Support\QualityAlertCatalog;

new #[Layout('core::layouts.app')] class extends Component {
    public bool $enabled = true;

    /** @var list<string> */
    public array $departmentChoices = [];

    public bool $departmentsAll = true;

    /** @var list<string> */
    public array $statusChoices = [];

    public bool $statusesAll = true;

    /** @var list<string> */
    public array $reportChoices = [];

    public bool $reportsAll = true;

    /** @var list<string> */
    public array $summaryChoices = [];

    public bool $summariesAll = true;

    public string $send_time = '18:00';

    public int $overdue_days = 1;

    public string $fallback_email = '';

    public function mount(): void
    {
        $prefs = QualityAlertCatalog::systemPreferences();
        $this->enabled = $prefs['enabled'];
        $this->send_time = $prefs['send_time'];
        $this->overdue_days = $prefs['overdue_days'];
        $this->fallback_email = $prefs['fallback_email'];

        $this->hydrateGroup('departments', $prefs['departments'], array_keys(QualityAlertCatalog::DEPARTMENTS));
        $this->hydrateGroup('statuses', $prefs['statuses'], array_keys(QualityAlertCatalog::STATUSES));
        $this->hydrateGroup('reports', $prefs['reports'], array_keys(QualityAlertCatalog::REPORTS));
        $this->hydrateGroup('summaries', $prefs['summaries'], array_keys(QualityAlertCatalog::SUMMARIES));
    }

    public function updatedDepartmentsAll(bool $value): void
    {
        $this->applyAllToggle('departments', $value, array_keys(QualityAlertCatalog::DEPARTMENTS));
    }

    public function updatedDepartmentChoices(): void
    {
        $this->syncAllFlag('departments', array_keys(QualityAlertCatalog::DEPARTMENTS));
    }

    public function updatedStatusesAll(bool $value): void
    {
        $this->applyAllToggle('statuses', $value, array_keys(QualityAlertCatalog::STATUSES));
    }

    public function updatedStatusChoices(): void
    {
        $this->syncAllFlag('statuses', array_keys(QualityAlertCatalog::STATUSES));
    }

    public function updatedReportsAll(bool $value): void
    {
        $this->applyAllToggle('reports', $value, array_keys(QualityAlertCatalog::REPORTS));
    }

    public function updatedReportChoices(): void
    {
        $this->syncAllFlag('reports', array_keys(QualityAlertCatalog::REPORTS));
    }

    public function updatedSummariesAll(bool $value): void
    {
        $this->applyAllToggle('summaries', $value, array_keys(QualityAlertCatalog::SUMMARIES));
    }

    public function updatedSummaryChoices(): void
    {
        $this->syncAllFlag('summaries', array_keys(QualityAlertCatalog::SUMMARIES));
    }

    public function save(): void
    {
        if (preg_match('/^\d{2}:\d{2}:\d{2}$/', $this->send_time) === 1) {
            $this->send_time = substr($this->send_time, 0, 5);
        }

        $this->validate([
            'enabled' => ['boolean'],
            'departmentChoices' => ['array'],
            'departmentChoices.*' => ['string', 'in:purchase,incoming'],
            'statusChoices' => ['array'],
            'statusChoices.*' => ['string', 'in:overdue,pending,completed'],
            'reportChoices' => ['array'],
            'reportChoices.*' => ['string', 'in:po_vs_pi,incoming_inspection'],
            'summaryChoices' => ['array'],
            'summaryChoices.*' => ['string', 'in:daily,weekly,monthly'],
            'send_time' => ['required', 'date_format:H:i'],
            'overdue_days' => ['required', 'integer', 'min:1', 'max:30'],
            'fallback_email' => ['nullable', 'email', 'max:255'],
        ]);

        $prefs = QualityAlertCatalog::normalize([
            'enabled' => $this->enabled,
            'departments' => QualityAlertCatalog::collapseFromUi($this->departmentChoices, array_keys(QualityAlertCatalog::DEPARTMENTS)),
            'statuses' => QualityAlertCatalog::collapseFromUi($this->statusChoices, array_keys(QualityAlertCatalog::STATUSES)),
            'reports' => QualityAlertCatalog::collapseFromUi($this->reportChoices, array_keys(QualityAlertCatalog::REPORTS)),
            'summaries' => QualityAlertCatalog::collapseFromUi($this->summaryChoices, array_keys(QualityAlertCatalog::SUMMARIES)),
        ]);

        Setting::setMany([
            'enabled' => $prefs['enabled'],
            'departments' => $prefs['departments'],
            'statuses' => $prefs['statuses'],
            'reports' => $prefs['reports'],
            'summaries' => $prefs['summaries'],
            'send_time' => $this->send_time,
            'overdue_days' => $this->overdue_days,
            'fallback_email' => trim($this->fallback_email),
        ], 'email_alerts');

        $this->hydrateGroup('departments', $prefs['departments'], array_keys(QualityAlertCatalog::DEPARTMENTS));
        $this->hydrateGroup('statuses', $prefs['statuses'], array_keys(QualityAlertCatalog::STATUSES));
        $this->hydrateGroup('reports', $prefs['reports'], array_keys(QualityAlertCatalog::REPORTS));
        $this->hydrateGroup('summaries', $prefs['summaries'], array_keys(QualityAlertCatalog::SUMMARIES));

        $this->dispatch(
            'SweetMessage',
            type: 'success',
            title: __('Success'),
            message: __('Email alert settings saved.'),
        );
    }

    /**
     * @param  list<string>  $stored
     * @param  list<string>  $particulars
     */
    private function hydrateGroup(string $group, array $stored, array $particulars): void
    {
        $choices = QualityAlertCatalog::expandForUi($stored, $particulars);

        match ($group) {
            'departments' => [$this->departmentChoices, $this->departmentsAll] = [$choices, $stored === ['all'] || count($choices) === count($particulars)],
            'statuses' => [$this->statusChoices, $this->statusesAll] = [$choices, $stored === ['all'] || count($choices) === count($particulars)],
            'reports' => [$this->reportChoices, $this->reportsAll] = [$choices, $stored === ['all'] || count($choices) === count($particulars)],
            'summaries' => [$this->summaryChoices, $this->summariesAll] = [$choices, $stored === ['all'] || count($choices) === count($particulars)],
            default => null,
        };
    }

    /**
     * @param  list<string>  $particulars
     */
    private function applyAllToggle(string $group, bool $value, array $particulars): void
    {
        $choices = $this->choicesFor($group);
        $isComplete = $particulars !== []
            && count(array_intersect($choices, $particulars)) === count($particulars);

        if ($value) {
            if (! $isComplete) {
                $this->setChoices($group, $particulars);
            }

            return;
        }

        // Only clear when All was covering every option. If a particular was
        // unchecked first, syncAllFlag flips All off — keep the remaining ticks.
        if ($isComplete) {
            $this->setChoices($group, []);
        }
    }

    /**
     * @param  list<string>  $particulars
     */
    private function syncAllFlag(string $group, array $particulars): void
    {
        $all = $particulars !== []
            && count(array_intersect($this->choicesFor($group), $particulars)) === count($particulars);

        match ($group) {
            'departments' => $this->departmentsAll = $all,
            'statuses' => $this->statusesAll = $all,
            'reports' => $this->reportsAll = $all,
            'summaries' => $this->summariesAll = $all,
            default => null,
        };
    }

    /**
     * @return list<string>
     */
    private function choicesFor(string $group): array
    {
        return match ($group) {
            'departments' => $this->departmentChoices,
            'statuses' => $this->statusChoices,
            'reports' => $this->reportChoices,
            'summaries' => $this->summaryChoices,
            default => [],
        };
    }

    /**
     * @param  list<string>  $choices
     */
    private function setChoices(string $group, array $choices): void
    {
        match ($group) {
            'departments' => $this->departmentChoices = array_values($choices),
            'statuses' => $this->statusChoices = array_values($choices),
            'reports' => $this->reportChoices = array_values($choices),
            'summaries' => $this->summaryChoices = array_values($choices),
            default => null,
        };
    }
}; ?>

@php
    $departmentOptions = QualityAlertCatalog::departmentLabels();
    $statusOptions = QualityAlertCatalog::statusLabels();
    $reportOptions = QualityAlertCatalog::reportLabels();
    $summaryOptions = QualityAlertCatalog::summaryLabels();
@endphp

<div class="space-y-6">
    <x-core::ui.page-header
        :title="__('Email alerts')"
        :subtitle="__('Choose which Purchase and Incoming emails go out. Who receives them is set on each user.')"
        :breadcrumbs="[
            ['label' => __('Dashboard'), 'route' => 'admin::dashboard'],
            ['label' => __('Settings')],
            ['label' => __('Email alerts')],
        ]"
    />

    <form wire:submit="save" class="w-full space-y-6">
        <div class="admin-card overflow-hidden">
            <div class="space-y-5 px-6 py-6">
                <flux:switch wire:model.live="enabled" :label="__('Send quality email alerts')" />
                <p class="text-sm text-zinc-500">
                    {{ __('Daily status mail and the day summary run at the time below. Weekly summaries run on Sunday and cover that week. Monthwise summaries run on the last day and cover that month. The scheduler uses `php artisan quality:digest`.') }}
                </p>
                <div class="grid gap-5 sm:grid-cols-2 xl:grid-cols-3">
                    <flux:input wire:model="send_time" type="time" :label="__('Send time')" required />
                    <flux:input
                        wire:model="overdue_days"
                        type="number"
                        min="1"
                        max="30"
                        :label="__('Inspection overdue after (days)')"
                        :description="__('A received batch with no inspection report becomes overdue after this many days.')"
                    />
                    <flux:input
                        wire:model="fallback_email"
                        type="email"
                        :label="__('Fallback inbox')"
                        :description="__('Used only when no user is subscribed to that alert.')"
                    />
                </div>
            </div>
        </div>

        <div class="admin-card overflow-hidden">
            <div class="border-b border-zinc-200 px-6 py-4 dark:border-zinc-700">
                <flux:heading size="lg">{{ __('What to send') }}</flux:heading>
                <flux:subheading>
                    {{ __('Tick All to select every option in a group, or pick specific ones. Clearing a group stops that mail. PO vs PI belongs to Purchase. Incoming inspection reports belong to Incoming.') }}
                </flux:subheading>
            </div>
            <div class="grid grid-cols-1 gap-6 px-6 py-6 lg:grid-cols-2 xl:grid-cols-4">
                <x-core::forms.alert-checks :legend="__('Departments')" :hint="__('All, one department, or both.')">
                    <label class="flex cursor-pointer items-center gap-2.5 text-sm text-zinc-700 dark:text-zinc-300">
                        <input type="checkbox" wire:model.live="departmentsAll" class="size-4 shrink-0 rounded border-zinc-300 text-[var(--admin-primary)] focus:ring-[var(--admin-primary)]">
                        <span class="font-medium">{{ __('All departments') }}</span>
                    </label>
                    @foreach ($departmentOptions as $value => $label)
                        <label class="flex cursor-pointer items-center gap-2.5 text-sm text-zinc-700 dark:text-zinc-300">
                            <input type="checkbox" value="{{ $value }}" wire:model.live="departmentChoices" class="size-4 shrink-0 rounded border-zinc-300 text-[var(--admin-primary)] focus:ring-[var(--admin-primary)]">
                            <span>{{ $label }}</span>
                        </label>
                    @endforeach
                </x-core::forms.alert-checks>

                <x-core::forms.alert-checks :legend="__('Status')" :hint="__('Overdue, pending, and completed. Daily emails only.')">
                    <label class="flex cursor-pointer items-center gap-2.5 text-sm text-zinc-700 dark:text-zinc-300">
                        <input type="checkbox" wire:model.live="statusesAll" class="size-4 shrink-0 rounded border-zinc-300 text-[var(--admin-primary)] focus:ring-[var(--admin-primary)]">
                        <span class="font-medium">{{ __('All statuses') }}</span>
                    </label>
                    @foreach ($statusOptions as $value => $label)
                        <label class="flex cursor-pointer items-center gap-2.5 text-sm text-zinc-700 dark:text-zinc-300">
                            <input type="checkbox" value="{{ $value }}" wire:model.live="statusChoices" class="size-4 shrink-0 rounded border-zinc-300 text-[var(--admin-primary)] focus:ring-[var(--admin-primary)]">
                            <span>{{ $label }}</span>
                        </label>
                    @endforeach
                </x-core::forms.alert-checks>

                <x-core::forms.alert-checks :legend="__('Reports')" :hint="__('PO vs PI and incoming inspection reports.')">
                    <label class="flex cursor-pointer items-center gap-2.5 text-sm text-zinc-700 dark:text-zinc-300">
                        <input type="checkbox" wire:model.live="reportsAll" class="size-4 shrink-0 rounded border-zinc-300 text-[var(--admin-primary)] focus:ring-[var(--admin-primary)]">
                        <span class="font-medium">{{ __('All reports') }}</span>
                    </label>
                    @foreach ($reportOptions as $value => $label)
                        <label class="flex cursor-pointer items-center gap-2.5 text-sm text-zinc-700 dark:text-zinc-300">
                            <input type="checkbox" value="{{ $value }}" wire:model.live="reportChoices" class="size-4 shrink-0 rounded border-zinc-300 text-[var(--admin-primary)] focus:ring-[var(--admin-primary)]">
                            <span>{{ $label }}</span>
                        </label>
                    @endforeach
                </x-core::forms.alert-checks>

                <x-core::forms.alert-checks :legend="__('Summaries')" :hint="__('Day wise, weekly, and monthwise.')">
                    <label class="flex cursor-pointer items-center gap-2.5 text-sm text-zinc-700 dark:text-zinc-300">
                        <input type="checkbox" wire:model.live="summariesAll" class="size-4 shrink-0 rounded border-zinc-300 text-[var(--admin-primary)] focus:ring-[var(--admin-primary)]">
                        <span class="font-medium">{{ __('All summaries') }}</span>
                    </label>
                    @foreach ($summaryOptions as $value => $label)
                        <label class="flex cursor-pointer items-center gap-2.5 text-sm text-zinc-700 dark:text-zinc-300">
                            <input type="checkbox" value="{{ $value }}" wire:model.live="summaryChoices" class="size-4 shrink-0 rounded border-zinc-300 text-[var(--admin-primary)] focus:ring-[var(--admin-primary)]">
                            <span>{{ $label }}</span>
                        </label>
                    @endforeach
                </x-core::forms.alert-checks>
            </div>
            <div class="flex justify-end border-t border-zinc-200 bg-[var(--admin-surface-muted)] px-6 py-4 dark:border-zinc-700">
                <flux:button variant="primary" type="submit" wire:loading.attr="disabled">{{ __('Save Settings') }}</flux:button>
            </div>
        </div>
    </form>
</div>
