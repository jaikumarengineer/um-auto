<?php

use App\Concerns\NotifiesWithSweetAlert;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Livewire\WithPagination;
use Modules\Quality\Models\QualityAlert;
use Modules\Quality\Services\QualityDigest;

new #[Layout('core::layouts.app')] class extends Component {
    use NotifiesWithSweetAlert;
    use WithPagination;

    public string $channel = '';

    public string $type = '';

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

        if ($user?->isPurchase() && ! $user->isMaster()) {
            return [
                'purchase_summary' => __('Day summary'),
                'po_vs_pi' => __('PO vs PI'),
                'overdue_po' => __('Overdue PO'),
            ];
        }

        if ($user?->isIncoming() && ! $user->isMaster()) {
            return [
                'incoming_summary' => __('Day summary'),
                'uninspected_lots' => __('Waiting inspection'),
                'quality_hold' => __('Quality hold'),
            ];
        }

        return [
            'daily_summary' => __('Day summary'),
            'purchase_summary' => __('Purchase summary'),
            'incoming_summary' => __('Incoming summary'),
            'po_vs_pi' => __('PO vs PI'),
            'overdue_po' => __('Overdue PO'),
            'uninspected_lots' => __('Waiting inspection'),
            'quality_hold' => __('Quality hold'),
        ];
    }

    public function generate(QualityDigest $digest): void
    {
        abort_unless(auth()->user()?->isMaster(), 403);

        $digest->generate(now());
        $this->notifySuccess(__('Today’s email and WhatsApp alerts are ready.'));
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
            'alerts' => QualityAlert::query()
                ->whereIn('alert_type', array_keys($types))
                ->when($this->channel !== '', fn ($query) => $query->where('channel', $this->channel))
                ->when($this->type !== '', fn ($query) => $query->where('alert_type', $this->type))
                ->latest('id')
                ->paginate(12),
            'email' => config('quality.alert_email'),
            'whatsapp' => config('quality.alert_whatsapp'),
            'alertTypes' => $types,
            'canSend' => (bool) auth()->user()?->isMaster(),
        ];
    }
}; ?>

<div>
    <x-core::ui.page-header
        :title="__('Alerts')"
        :subtitle="__('Day summary, PO versus PI, overdue orders, batches waiting inspection, and quality holds.')"
        :breadcrumbs="[
            admin_breadcrumb_home(),
            ['label' => __('Alerts')],
        ]"
    >
        <x-slot:actions>
            @if ($canSend)
                <flux:button variant="primary" icon="bell-alert" wire:click="generate" wire:loading.attr="disabled">
                    {{ __('Build today’s alerts') }}
                </flux:button>
            @endif
        </x-slot:actions>
    </x-core::ui.page-header>

    <div class="mb-4 admin-card p-4 text-sm text-zinc-600 dark:text-zinc-300">
        <p>{{ __('Email goes to :email through the mailer in settings. WhatsApp to :phone is stored here so you can read the message. Connect a WhatsApp provider when you want it delivered.', ['email' => $email, 'phone' => $whatsapp]) }}</p>
        <p class="mt-2">{{ __('The same digest runs every day at 18:00 with `php artisan quality:daily-digest`, or from the scheduler.') }}</p>
    </div>

    <div class="mb-4 admin-card p-3">
        <div class="grid gap-3 md:grid-cols-2">
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
                    @foreach ([__('When'), __('Channel'), __('Alert'), __('To'), __('Status')] as $heading)
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-zinc-500">{{ $heading }}</th>
                    @endforeach
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
    @if ($alerts->hasPages()) <div class="mt-4">{{ $alerts->links() }}</div> @endif

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
