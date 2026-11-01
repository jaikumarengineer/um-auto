<?php

use App\Concerns\AuthorizesModule;
use App\Concerns\NotifiesWithSweetAlert;
use App\Concerns\WithSorting;
use App\Services\RecordApproval;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Livewire\WithPagination;
use Modules\Quality\Models\InspectionReport;
use Modules\Quality\Services\QualityWorkflow;
use Modules\Quality\Support\Qty;

new #[Layout('core::layouts.app')] class extends Component {
    use AuthorizesModule;
    use NotifiesWithSweetAlert;
    use WithPagination;
    use WithSorting;

    private const MODULE = 'quality.incoming.reports';

    public string $search = '';

    public string $resultFilter = '';

    public string $sortBy = 'inspected_at';

    public string $sortDirection = 'desc';

    protected array $sortable = ['report_number', 'inspected_qty', 'sample_size', 'inspected_at', 'result'];

    public bool $showAcceptedStatusModal = false;

    public ?int $acceptedStatusReportId = null;

    public string $acceptedStatus = RecordApproval::PENDING;

    public string $acceptedStatusReason = '';

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingResultFilter(): void
    {
        $this->resetPage();
    }

    public function openAcceptedStatus(int $id): void
    {
        abort_unless(auth()->user()?->canModule('quality.approvals', 'edit'), 403);

        $report = InspectionReport::query()->findOrFail($id);
        $this->acceptedStatusReportId = $report->id;
        $this->acceptedStatus = (string) ($report->approval_status ?: RecordApproval::PENDING);
        $this->acceptedStatusReason = (string) ($report->rejection_reason ?? '');
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
            $report = InspectionReport::query()->findOrFail($this->acceptedStatusReportId);
            app(QualityWorkflow::class)->updateInspectionReportAcceptedStatus(
                $report,
                $this->acceptedStatus,
                $this->acceptedStatusReason ?: null,
            );
            $this->showAcceptedStatusModal = false;
            $this->notifySuccess(__('Accepted status updated.'));
        } catch (ValidationException $exception) {
            $this->notifyError($exception->validator->errors()->first() ?: __('Could not update accepted status.'));
        }
    }

    public function deleteReport(int $id, QualityWorkflow $workflow): void
    {
        $this->authorizeModule(self::MODULE, 'delete');

        $workflow->deleteInspectionReport(InspectionReport::query()->findOrFail($id));
        $this->notifySuccess(__('Inspection report deleted.'));
    }

    public function with(): array
    {
        return [
            'reports' => $this->applySorting(
                InspectionReport::query()
                    ->with(['lot.part', 'standard', 'creator', 'approver'])
                    ->when($this->search !== '', function ($query) {
                        $term = '%'.$this->search.'%';
                        $query->where(function ($query) use ($term) {
                            $query->where('report_number', 'like', $term)
                                ->orWhereHas('lot', fn ($lot) => $lot->where('lot_number', 'like', $term))
                                ->orWhereHas('lot.part', fn ($part) => $part->where('part_number', 'like', $term));
                        });
                    })
                    ->when($this->resultFilter !== '', fn ($query) => $query->where('result', $this->resultFilter))
            )->paginate(12),
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
        :title="__('Incoming Quality Reports')"
        :subtitle="__('Showing the list of incoming quality reports')"
        :breadcrumbs="[
            admin_breadcrumb_home(),
            ['label' => __('Incoming Quality Reports')],
        ]"
    >
        <x-slot:actions>
            @if ($canAdd)
                <flux:button variant="primary" icon="plus" :href="route('admin::quality.incoming.reports.create')" wire:navigate>
                    {{ __('New report') }}
                </flux:button>
            @endif
        </x-slot:actions>
    </x-core::ui.page-header>

    <div class="mb-4 admin-card p-3">
        <div class="flex flex-col gap-3 lg:flex-row">
            <div class="min-w-0 flex-1">
                <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" :placeholder="__('Search report, lot, or part')" />
            </div>
            <flux:select wire:model.live="resultFilter" class="lg:w-44">
                <flux:select.option value="">{{ __('All results') }}</flux:select.option>
                @foreach (['pass', 'conditional', 'fail'] as $result)
                    <flux:select.option :value="$result">{{ str($result)->title() }}</flux:select.option>
                @endforeach
            </flux:select>
        </div>
    </div>

    <div class="overflow-x-auto admin-card">
        <table class="min-w-full divide-y divide-zinc-200 dark:divide-zinc-700">
            <thead class="bg-[var(--admin-surface-muted)]">
                <tr>
                    <x-core::ui.sortable-th column="report_number" :label="__('Report')" />
                    <th class="px-4 py-3 text-left text-xs font-semibold text-zinc-500">{{ __('Batch') }}</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-zinc-500">{{ __('Part') }}</th>
                    <x-core::ui.sortable-th column="inspected_qty" :label="__('Qty')" />
                    <x-core::ui.sortable-th column="sample_size" :label="__('Sample')" />
                    <x-core::ui.sortable-th column="inspected_at" :label="__('When')" />
                    <th class="px-4 py-3 text-left text-xs font-semibold text-zinc-500">{{ __('Remarks') }}</th>
                    <x-core::ui.sortable-th column="result" :label="__('Result')" />
                    <th class="px-4 py-3 text-left text-xs font-semibold text-zinc-500">{{ __('Accepted Status') }}</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-zinc-500">{{ __('Created by') }}</th>
                    <th></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                @forelse ($reports as $report)
                    <tr wire:key="report-{{ $report->id }}">
                        <td class="px-4 py-3 text-sm font-medium">{{ $report->report_number }}</td>
                        <td class="px-4 py-3 text-sm">{{ $report->lot?->lot_number }}</td>
                        <td class="px-4 py-3 text-sm">{{ $report->lot?->part?->part_number }}</td>
                        <td class="px-4 py-3 text-sm tabular-nums">{{ Qty::format($report->inspected_qty) }}</td>
                        <td class="px-4 py-3 text-sm">{{ $report->sample_size }}</td>
                        <td class="px-4 py-3 text-sm">{{ $report->inspected_at?->format('d M Y H:i') }}</td>
                        <x-core::ui.long-text-cell :value="$report->remarks" />
                        <td class="px-4 py-3"><x-quality::status-pill :status="$report->result" /></td>
                        <td class="px-4 py-3">
                            <div class="flex items-center gap-1.5">
                                <x-quality::status-pill :status="$report->approval_status ?? 'approved'" />
                                @if ($canEditAcceptedStatus)
                                    <button
                                        type="button"
                                        class="rounded p-1 text-zinc-400 hover:bg-zinc-100 hover:text-zinc-700 dark:hover:bg-zinc-800"
                                        wire:click="openAcceptedStatus({{ $report->id }})"
                                        title="{{ __('Edit accepted status') }}"
                                    >
                                        <flux:icon name="pencil-square" class="size-4" />
                                    </button>
                                @endif
                            </div>
                        </td>
                        <td class="px-4 py-3">
                            <x-quality::user-avatars :users="[$report->creator, $report->approver]" />
                        </td>
                        <td class="px-4 py-3 text-right">
                            @if ($canView || $canEdit || $canDelete)
                                <x-core::ui.actions-menu>
                                    @if ($canView)
                                        <flux:menu.item icon="eye" icon:variant="outline" :href="route('admin::quality.incoming.reports.show', $report)" wire:navigate>
                                            {{ __('View') }}
                                        </flux:menu.item>
                                    @endif
                                    @if ($canEdit)
                                        <flux:menu.item icon="pencil-square" icon:variant="outline" :href="route('admin::quality.incoming.reports.edit', $report)" wire:navigate>
                                            {{ __('Edit') }}
                                        </flux:menu.item>
                                    @endif
                                    @if ($canDelete)
                                        <flux:menu.separator />
                                        <x-core::ui.confirm-button as="menu-item" method="deleteReport" :params="[$report->id]" />
                                    @endif
                                </x-core::ui.actions-menu>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="11" class="px-4 py-12 text-center text-sm text-zinc-500">{{ __('No inspection reports yet.') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <x-core::ui.pagination :paginator="$reports" />

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
