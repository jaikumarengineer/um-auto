<?php

use App\Concerns\NotifiesWithSweetAlert;
use App\Concerns\WithSorting;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Livewire\WithPagination;
use Modules\Quality\Models\QualityAlert;
use Modules\Quality\Services\QualityDigest;
use Modules\Quality\Support\QualityAlertCatalog;

new #[Layout('core::layouts.app')] class extends Component {
    use NotifiesWithSweetAlert;
    use WithPagination;
    use WithSorting;

    public string $channel = '';

    public string $type = '';

    public string $cadence = 'daily';

    public string $sortBy = 'id';

    public string $sortDirection = 'desc';

    protected array $sortable = ['alert_on', 'channel', 'subject', 'recipient', 'status', 'id'];

    public bool $showBody = false;

    public ?QualityAlert $openAlert = null;

    public function updatingChannel(): void
    {
        $this->resetPage();
    }

    public function updatingType(): void
    {
        $this->resetPage();
    }

    /**
     * @return array<string, string>
     */
    public function allowedTypes(): array
    {
        $user = auth()->user();
        $department = match (true) {
            $user?->isPurchase() && ! $user->isMaster() => 'purchase',
            $user?->isIncoming() && ! $user->isMaster() => 'incoming',
            default => null,
        };

        return QualityAlertCatalog::labelsFor($department, includeLegacy: true);
    }

    public function generate(QualityDigest $digest): void
    {
        abort_unless(auth()->user()?->isMaster(), 403);

        if (! in_array($this->cadence, ['daily', 'weekly', 'monthly'], true)) {
            $this->cadence = 'daily';
        }

        $digest->generate(now(), sendMail: true, cadence: $this->cadence);
        $this->notifySuccess(__('Alerts for this period are ready.'));
    }

    public function read(int $id): void
    {
        $alert = QualityAlert::query()->findOrFail($id);
        abort_unless(array_key_exists($alert->alert_type, $this->allowedTypes()), 403);

        $this->openAlert = $alert;
        $this->showBody = true;
    }

    public function with(): array
    {
        $types = $this->allowedTypes();

        return [
            'alerts' => $this->applySorting(
                QualityAlert::query()
                    ->whereIn('alert_type', array_keys($types))
                    ->when($this->channel !== '', fn ($query) => $query->where('channel', $this->channel))
                    ->when($this->type !== '', fn ($query) => $query->where('alert_type', $this->type))
            )->paginate(12),
            'whatsapp' => config('quality.alert_whatsapp'),
            'alertTypes' => $types,
            'canSend' => (bool) auth()->user()?->isMaster(),
        ];
    }
}; ?>

<div>
    <x-core::ui.page-header
        :title="__('Alerts')"
        :subtitle="__('Showing the list of alerts')"
        :breadcrumbs="[
            admin_breadcrumb_home(),
            ['label' => __('Alerts')],
        ]"
    >
        <x-slot:actions>
            @if ($canSend)
                <flux:button variant="primary" icon="bell-alert" wire:click="generate" wire:loading.attr="disabled">
                    {{ __('Build alerts') }}
                </flux:button>
            @endif
        </x-slot:actions>
    </x-core::ui.page-header>

    <div class="mb-4 admin-card p-4 text-sm text-zinc-600 dark:text-zinc-300">
        <p>{{ __('Email goes to subscribed users through the mailer in settings. If nobody is subscribed, it uses the fallback inbox. WhatsApp to :phone is stored here so you can read the message.', ['phone' => $whatsapp]) }}</p>
        <p class="mt-2">{{ __('Daily mail runs at the time in Email alert settings. Weekly summaries run Sunday, monthwise summaries on the last day, via `php artisan quality:digest`.') }}</p>
    </div>

    <div class="mb-4 admin-card p-3">
        <div class="grid gap-3 md:grid-cols-3">
            @if ($canSend)
                <flux:select wire:model="cadence">
                    <flux:select.option value="daily">{{ __('Day wise') }}</flux:select.option>
                    <flux:select.option value="weekly">{{ __('Weekly') }}</flux:select.option>
                    <flux:select.option value="monthly">{{ __('Monthwise') }}</flux:select.option>
                </flux:select>
            @endif
            <flux:select wire:model.live="channel">
                <flux:select.option value="">{{ __('Email and WhatsApp') }}</flux:select.option>
                <flux:select.option value="email">{{ __('Email') }}</flux:select.option>
                <flux:select.option value="whatsapp">{{ __('WhatsApp') }}</flux:select.option>
            </flux:select>
            <flux:select wire:model.live="type">
                <flux:select.option value="">{{ __('All alerts') }}</flux:select.option>
                @foreach ($alertTypes as $value => $label)
                    <flux:select.option :value="$value">{{ $label }}</flux:select.option>
                @endforeach
            </flux:select>
        </div>
    </div>

    <div class="overflow-x-auto admin-card">
        <table class="min-w-full divide-y divide-zinc-200 dark:divide-zinc-700">
            <thead class="bg-[var(--admin-surface-muted)]">
                <tr>
                    <x-core::ui.sortable-th column="alert_on" :label="__('When')" />
                    <x-core::ui.sortable-th column="channel" :label="__('Channel')" />
                    <x-core::ui.sortable-th column="subject" :label="__('Alert')" />
                    <x-core::ui.sortable-th column="recipient" :label="__('To')" />
                    <x-core::ui.sortable-th column="status" :label="__('Status')" />
                    <th></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                @forelse ($alerts as $alert)
                    <tr wire:key="alert-{{ $alert->id }}">
                        <td class="px-4 py-3 text-sm">{{ $alert->alert_on?->format('d M Y') }}</td>
                        <td class="px-4 py-3 text-sm">{{ str($alert->channel)->title() }}</td>
                        <td class="px-4 py-3 text-sm">{{ $alert->subject }}</td>
                        <td class="px-4 py-3 text-sm">{{ $alert->recipient }}</td>
                        <td class="px-4 py-3"><x-quality::status-pill :status="$alert->status" /></td>
                        <td class="px-4 py-3 text-right">
                            <flux:button size="sm" variant="ghost" wire:click="read({{ $alert->id }})">{{ __('Read') }}</flux:button>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-4 py-12 text-center text-sm text-zinc-500">{{ __('No alerts yet. Build today’s digest to see them.') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <x-core::ui.pagination :paginator="$alerts" />

    <flux:modal wire:model="showBody" class="max-w-xl">
        @if ($openAlert)
            <div class="space-y-3">
                <flux:heading size="lg">{{ $openAlert->subject }}</flux:heading>
                <p class="text-sm text-zinc-500">{{ str($openAlert->channel)->title() }} · {{ $openAlert->recipient }}</p>
                <pre class="whitespace-pre-wrap rounded-2xl bg-[var(--admin-surface-muted)] p-4 text-sm">{{ $openAlert->body }}</pre>
            </div>
        @endif
    </flux:modal>
</div>
