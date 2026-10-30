<?php

use App\Concerns\NotifiesWithSweetAlert;
use App\Services\RecordApproval;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Modules\Quality\Models\InspectionReport;
use Modules\Quality\Models\InspectionResult;
use Modules\Quality\Services\QualityWorkflow;
use Modules\Quality\Support\InspectionReadings;
use Modules\Quality\Support\Qty;

new #[Layout('core::layouts.app')] class extends Component {
    use NotifiesWithSweetAlert;

    public InspectionReport $report;

    public string $rejectionReason = '';

    public function deleteReport(QualityWorkflow $workflow): void
    {
        abort_unless(auth()->user()?->isMaster(), 403);

        $workflow->deleteInspectionReport($this->report);

        session()->flash('success', __('Inspection report deleted.'));
        $this->redirectRoute('admin::quality.incoming.reports', navigate: true);
    }

    public function approve(): void
    {
        try {
            $this->report = app(QualityWorkflow::class)->approveInspectionReport($this->report);
            $this->notifySuccess(__('Inspection report approved.'));
        } catch (ValidationException $exception) {
            $this->notifyError($exception->validator->errors()->first() ?: __('Could not approve this report.'));
        }
    }

    public function reject(): void
    {
        try {
            $this->report = app(QualityWorkflow::class)->rejectInspectionReport($this->report, $this->rejectionReason ?: null);
            $this->notifySuccess(__('Inspection report rejected.'));
        } catch (ValidationException $exception) {
            $this->notifyError($exception->validator->errors()->first() ?: __('Could not reject this report.'));
        }
    }

    public function mount(InspectionReport $report): void
    {
        $this->report = $report->load([
            'lot.part',
            'lot.vendor',
            'standard.characteristics',
            'samplingPlan',
            'results.characteristic',
            'inspector',
            'verifier',
            'creator',
            'approver',
        ]);
    }

    public function with(): array
    {
        return [
            'canApprove' => auth()->user()?->canModule('quality.approvals', 'edit') ?? false,
            'isPending' => ($this->report->approval_status ?? RecordApproval::APPROVED) === RecordApproval::PENDING,
        ];
    }

    public function sampleCount(): int
    {
        return max(1, (int) $this->report->sample_size);
    }

    public function hasReadings(): bool
    {
        return $this->report->results->contains(
            fn (InspectionResult $result) => is_array($result->supplier_readings) || is_array($result->umac_readings)
        );
    }

    /**
     * @return list<string>
     */
    public function readings(InspectionResult $result, string $side): array
    {
        $values = $side === 'supplier' ? $result->supplier_readings : $result->umac_readings;

        return InspectionReadings::pad(is_array($values) ? $values : [], $this->sampleCount());
    }

    public function cellState(InspectionResult $result, string $value): string
    {
        $limits = $this->limits($result);

        return InspectionReadings::state($value, $limits['min'], $limits['max']);
    }

    public function sideStatus(InspectionResult $result, string $side): string
    {
        $limits = $this->limits($result);

        if (! InspectionReadings::hasLimits($limits['min'], $limits['max'])) {
            $stored = $side === 'supplier' ? $result->supplier_result : $result->umac_result;

            return InspectionReadings::manualStatus($stored);
        }

        return InspectionReadings::status($this->readings($result, $side), $limits['min'], $limits['max']);
    }

    public function overallStatus(string $side): string
    {
        $statuses = $this->report->results
            ->map(fn (InspectionResult $result) => $this->sideStatus($result, $side))
            ->all();

        return InspectionReadings::rollup($statuses);
    }

    public function statusLabel(string $status): string
    {
        return InspectionReadings::label($status);
    }

    public function decisionLabel(?string $decision): string
    {
        return match ($decision) {
            'non_conforming' => __('NON CONFORMING'),
            'aud' => __('A.U.D.'),
            'conforming' => __('CONFORMING'),
            default => '—',
        };
    }

    /**
     * @return array{min: ?float, max: ?float}
     */
    private function limits(InspectionResult $result): array
    {
        return InspectionReadings::limits(
            $result->characteristic?->tolerance_min,
            $result->characteristic?->tolerance_max,
            $result->specification,
        );
    }
}; ?>

<div>
    <x-core::ui.page-header
        :title="$report->report_number"
        :subtitle="($report->lot?->lot_number).' · '.($report->lot?->part?->label())"
        :breadcrumbs="[
            admin_breadcrumb_home(),
            ['label' => __('Incoming Quality Reports'), 'route' => 'admin::quality.incoming.reports'],
            ['label' => $report->report_number],
        ]"
    >
        <x-slot:actions>
            {{-- 
            <flux:button size="sm" icon="arrow-down-tray" :href="route('admin::quality.incoming.reports.pdf', $report)">{{ __('Generate PDF') }}</flux:button>
            <flux:button size="sm" icon="arrow-down-tray" :href="route('admin::quality.incoming.reports.excel', $report)">{{ __('Generate Excel') }}</flux:button>
            --}}
            @if (auth()->user()?->isMaster())
                <flux:button size="sm" icon="pencil-square" :href="route('admin::quality.incoming.reports.edit', $report)" wire:navigate>{{ __('Edit') }}</flux:button>
                <x-core::ui.confirm-button method="deleteReport" />
            @endif
            <x-quality::status-pill :status="$report->approval_status ?? 'approved'" />
            <x-quality::status-pill :status="$report->result" />
        </x-slot:actions>
    </x-core::ui.page-header>

    @if ($canApprove && $isPending)
        <div class="admin-card mb-4 space-y-3 p-4">
            <p class="text-sm font-semibold">{{ __('Approval by Admin or Manager') }}</p>
            <p class="text-sm text-zinc-500">{{ __('Created by :name', ['name' => $report->creator?->full_name ?: __('Unknown')]) }}</p>
            <flux:textarea wire:model="rejectionReason" :label="__('Rejection reason (optional)')" rows="2" />
            <div class="flex flex-wrap gap-2">
                <flux:button variant="primary" wire:click="approve">{{ __('Approve') }}</flux:button>
                <flux:button variant="danger" wire:click="reject">{{ __('Reject') }}</flux:button>
            </div>
        </div>
    @endif

    <div class="space-y-4">
        <div class="overflow-x-auto rounded-lg border border-zinc-300 bg-white text-zinc-900">
            <table class="iir-sheet iir-header">
                <tbody>
                    <tr>
                        <th class="iir-title" colspan="6">{{ __('INCOMING INSPECTION REPORT') }}</th>
                        <td class="iir-meta">
                            DOC. NO. F/QA/02<br>
                            DOC. DATE 01.01.16<br>
                            REV. NO/DATE 01/23.03.21
                        </td>
                    </tr>
                    <tr>
                        <th>{{ __('Material / PART NAME') }}</th>
                        <td class="iir-fixed" colspan="2">{{ $report->lot?->part?->name }}</td>
                        <th>{{ __('BILL No.') }}</th>
                        <td class="iir-fixed" colspan="3">{{ $report->bill_no ?: '—' }}</td>
                    </tr>
                    <tr>
                        <th>{{ __('Material / Part Code') }}</th>
                        <td class="iir-fixed" colspan="2">{{ $report->lot?->part?->part_number }}</td>
                        <th>{{ __('DATE') }}</th>
                        <td class="iir-fixed" colspan="3">{{ $report->inspected_at?->format('d M Y H:i') }}</td>
                    </tr>
                    <tr>
                        <th>{{ __('Batch') }}</th>
                        <td class="iir-fixed" colspan="2">{{ $report->lot?->lot_number }}</td>
                        <th>{{ __('SUPPLIER') }}</th>
                        <td class="iir-fixed" colspan="3">{{ $report->lot?->vendor?->name }}</td>
                    </tr>
                    <tr>
                        <th>{{ __('ASSY. NAME') }}</th>
                        <td class="iir-fixed" colspan="2">{{ $report->assembly_name ?: '—' }}</td>
                        <th>{{ __('SUPPLIER Code') }}</th>
                        <td class="iir-fixed" colspan="3">{{ $report->lot?->vendor?->code }}</td>
                    </tr>
                    <tr>
                        <th>{{ __('PROCESS') }}</th>
                        <td class="iir-fixed" colspan="2">{{ $report->process ?: '—' }}</td>
                        <th>{{ __('SAMPLE SIZE') }}</th>
                        <td class="iir-fixed" colspan="3">
                            {{ $report->sample_size }}
                            @if ($report->samplingPlan)
                                <span class="iir-note">{{ $report->samplingPlan->code }} · {{ __('lot') }} {{ $report->samplingPlan->bandLabel() }}</span>
                            @endif
                        </td>
                    </tr>
                    <tr>
                        <th>{{ __('LOT SIZE') }}</th>
                        <td class="iir-fixed" colspan="2">{{ Qty::format($report->inspected_qty) }}</td>
                        <th>{{ __('QTY. REJECTED') }}</th>
                        <td class="iir-fixed" colspan="3">{{ Qty::format($report->rejected_qty) }}</td>
                    </tr>
                    <tr>
                        <th>{{ __('QTY. ACCEPTED') }}</th>
                        <td class="iir-fixed" colspan="2">{{ Qty::format($report->accepted_qty) }}</td>
                        <th>{{ __('Standard') }}</th>
                        <td class="iir-fixed" colspan="3">{{ $report->standard?->code }} · {{ $report->standard?->title }}</td>
                    </tr>
                </tbody>
            </table>
        </div>

        @if ($report->images)
            <div class="admin-card flex flex-wrap gap-3 p-4">
                @foreach ($report->images as $image)
                    @php $imageUrl = media_url(is_array($image) ? ($image['url'] ?? '') : $image); @endphp
                    @if ($imageUrl)
                        <a href="{{ $imageUrl }}" target="_blank" rel="noopener noreferrer" class="admin-filepond-preview admin-filepond-preview-square" style="height: 7rem; width: 7rem;">
                            <img src="{{ $imageUrl }}" alt="" class="admin-filepond-preview-img" />
                        </a>
                    @endif
                @endforeach
            </div>
        @endif

        @if ($this->hasReadings())
            <div class="overflow-x-auto rounded-lg border border-zinc-300 bg-white text-zinc-900">
                <table class="iir-sheet iir-grid">
                    <thead>
                        <tr>
                            <th rowspan="2">{{ __('S.No.') }}</th>
                            <th rowspan="2">{{ __('DESCRIPTION') }}</th>
                            <th rowspan="2">{{ __('CHARC. CLASS') }}</th>
                            <th rowspan="2">{{ __('SPEC.') }}</th>
                            <th rowspan="2">{{ __('INSP. METHOD') }}</th>
                            <th colspan="{{ $this->sampleCount() + 1 }}">{{ __('SUPPLIER OBSERVATION') }}</th>
                            <th colspan="{{ $this->sampleCount() + 1 }}">{{ __('UMAC OBSERVATION') }}</th>
                            <th rowspan="2">{{ __('REMARKS') }}</th>
                        </tr>
                        <tr>
                            @for ($sample = 1; $sample <= $this->sampleCount(); $sample++)
                                <th>{{ $sample }}</th>
                            @endfor
                            <th>{{ __('STATUS (OK/NOT OK)') }}</th>
                            @for ($sample = 1; $sample <= $this->sampleCount(); $sample++)
                                <th>{{ $sample }}</th>
                            @endfor
                            <th>{{ __('STATUS (OK/NOT OK)') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($report->results as $index => $result)
                            <tr wire:key="saved-check-{{ $result->id }}">
                                <td class="iir-center">{{ $index + 1 }}</td>
                                <td class="iir-text">{{ $result->characteristic_name }}</td>
                                <td class="iir-center">{{ $result->characteristic_class ? (\Modules\Quality\Models\InspectionCharacteristic::CLASSES[$result->characteristic_class] ?? $result->characteristic_class) : '—' }}</td>
                                <td class="iir-text">{{ $result->specification }}</td>
                                <td class="iir-text">{{ $result->method }}</td>
                                @foreach ($this->readings($result, 'supplier') as $value)
                                    <td class="iir-sample iir-{{ $this->cellState($result, $value) }}">{{ $value !== '' ? $value : '—' }}</td>
                                @endforeach
                                @php $supplierStatus = $this->sideStatus($result, 'supplier'); @endphp
                                <td class="iir-status"><span class="iir-status-field iir-{{ $supplierStatus }}">{{ $this->statusLabel($supplierStatus) }}</span></td>
                                @foreach ($this->readings($result, 'umac') as $value)
                                    <td class="iir-sample iir-{{ $this->cellState($result, $value) }}">{{ $value !== '' ? $value : '—' }}</td>
                                @endforeach
                                @php $umacStatus = $this->sideStatus($result, 'umac'); @endphp
                                <td class="iir-status"><span class="iir-status-field iir-{{ $umacStatus }}">{{ $this->statusLabel($umacStatus) }}</span></td>
                                <td class="iir-text">{{ $result->remarks ?: '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="overflow-x-auto rounded-lg border border-zinc-300 bg-white text-zinc-900">
                <table class="iir-sheet iir-footer">
                    <tbody>
                        <tr>
                            <th>{{ __('SUPPLIER STATUS') }}</th>
                            @php $supplierOverall = $this->overallStatus('supplier'); @endphp
                            <td class="iir-status"><span class="iir-status-field iir-{{ $supplierOverall }}">{{ $this->statusLabel($supplierOverall) }}</span></td>
                            <th>{{ __('UMAC STATUS') }}</th>
                            @php $umacOverall = $this->overallStatus('umac'); @endphp
                            <td class="iir-status"><span class="iir-status-field iir-{{ $umacOverall }}">{{ $this->statusLabel($umacOverall) }}</span></td>
                        </tr>
                        <tr>
                            <th>{{ __('DECISION') }}</th>
                            <td class="iir-fixed">{{ $this->decisionLabel($report->supplier_decision) }}</td>
                            <th>{{ __('DECISION') }}</th>
                            <td class="iir-fixed">{{ $this->decisionLabel($report->umac_decision) }}</td>
                        </tr>
                        <tr>
                            <th>{{ __('INSPECTED BY') }}</th>
                            <td class="iir-fixed" colspan="3">{{ $report->inspector?->full_name ?: '—' }}</td>
                        </tr>
                        <tr>
                            <th>{{ __('VERIFIED BY') }}</th>
                            <td class="iir-fixed" colspan="3">{{ $report->verifier?->full_name ?: __('Filled after the report is approved.') }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        @else
            <div class="overflow-x-auto admin-card">
                <table class="min-w-full divide-y divide-zinc-200 dark:divide-zinc-700">
                    <thead class="bg-[var(--admin-surface-muted)]">
                        <tr>
                            @foreach ([__('Characteristic'), __('Standard'), __('Measured'), __('Result')] as $heading)
                                <th class="px-4 py-3 text-left text-xs font-semibold text-zinc-500">{{ $heading }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                        @foreach ($report->results as $result)
                            <tr>
                                <td class="px-4 py-3 text-sm">
                                    {{ $result->characteristic_name }}
                                    @if ($result->is_critical)
                                        <span class="ms-1 text-xs text-red-600">{{ __('Critical') }}</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-sm text-zinc-500">{{ $result->specification }}</td>
                                <td class="px-4 py-3 text-sm">{{ $result->measured_value ?: '—' }}</td>
                                <td class="px-4 py-3"><x-quality::status-pill :status="$result->result" /></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif

        @if ($report->remarks)
            <div class="admin-card p-4 text-sm text-zinc-600 dark:text-zinc-300">{{ $report->remarks }}</div>
        @endif
    </div>

    <style>
        .iir-sheet { width: 100%; border-collapse: collapse; background: #fff; color: #111; }
        .iir-sheet th, .iir-sheet td { border: 1px solid #222; padding: 8px 10px; vertical-align: middle; font-size: 13px; font-weight: 700; line-height: 1.35; }
        .iir-sheet td { font-weight: 500; }
        .iir-header { min-width: 860px; table-layout: fixed; }
        .iir-footer { min-width: 720px; table-layout: fixed; }
        .iir-grid { min-width: max(100%, 980px); }
        .iir-title { text-align: center; font-size: 16px; background: #f4f4f5; padding: 12px 8px; }
        .iir-meta { font-size: 12px; font-weight: 700; text-align: left; background: #f4f4f5; white-space: nowrap; }
        .iir-sheet thead th { background: #f4f4f5; text-align: center; font-size: 11px; letter-spacing: 0.02em; }
        .iir-center { text-align: center; }
        .iir-fixed { text-align: center; font-weight: 700; }
        .iir-text { min-width: 8rem; text-align: left; font-weight: 600; }
        .iir-note { display: block; margin-top: 4px; font-size: 11px; font-weight: 600; color: #3f3f46; }
        .iir-sample { text-align: center; font-weight: 700; min-width: 2.6rem; }
        .iir-sheet td.iir-sample.iir-ok { background: #16a34a !important; color: #fff !important; }
        .iir-sheet td.iir-sample.iir-ng { background: #dc2626 !important; color: #fff !important; }
        .iir-sheet td.iir-status { text-align: center; font-weight: 700; white-space: nowrap; }
        .iir-status-field {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 6.75rem;
            padding: 4px 8px;
            border: 1px solid #d4d4d8;
            border-radius: 4px;
            background: #fff;
            color: #111;
            font-weight: 700;
        }
        .iir-status-field.iir-ok { background: #16a34a; color: #fff; border-color: #15803d; }
        .iir-status-field.iir-ng { background: #dc2626; color: #fff; border-color: #b91c1c; }
    </style>
</div>
