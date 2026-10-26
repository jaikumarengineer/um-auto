<?php

use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Modules\Quality\Models\IncomingLot;
use Modules\Quality\Models\InspectionReport;
use Modules\Quality\Models\SamplingPlan;
use Modules\Quality\Services\QualityWorkflow;
use Modules\Quality\Support\Qty;

new #[Layout('core::layouts.app')] class extends Component {
    public string $lot_id = '';

    public string $report_number = '';

    public string $sample_size = '';

    public string $result = 'pass';

    public string $rejected_qty = '0';

    public string $inspected_at = '';

    public string $remarks = '';

    public bool $resultLocked = false;

    public array $checks = [];

    public ?string $planLabel = null;

    public ?string $standardLabel = null;

    public function mount(): void
    {
        $this->inspected_at = now()->format('Y-m-d\TH:i');
        $this->report_number = 'IR-'.now()->format('Y').'-'.str_pad((string) (InspectionReport::query()->count() + 1), 4, '0', STR_PAD_LEFT);

        $lot = request()->integer('lot');

        if ($lot) {
            $this->lot_id = (string) $lot;
            $this->loadLot();
        }
    }

    public function updatedLotId(): void
    {
        $this->resultLocked = false;
        $this->loadLot();
    }

    public function updated(string $name, mixed $value = null): void
    {
        if ($name === 'result') {
            $this->resultLocked = true;
        }

        if (preg_match('/^checks\.(\d+)\.measured$/', $name, $matches)) {
            $this->judge((int) $matches[1]);
            $this->suggestResult();
        }
    }

    public function save(QualityWorkflow $workflow): void
    {
        $this->validate([
            'lot_id' => ['required', 'exists:incoming_lots,id'],
            'report_number' => ['required', 'string', 'max:40', Rule::unique('inspection_reports', 'report_number')],
            'sample_size' => ['required', 'integer', 'min:1'],
            'result' => ['required', 'in:pass,conditional,fail'],
            'rejected_qty' => ['nullable', 'numeric', 'min:0'],
            'inspected_at' => ['required', 'date'],
            'checks' => ['required', 'array', 'min:1'],
        ]);

        $report = $workflow->saveInspectionReport([
            'lot_id' => $this->lot_id,
            'report_number' => $this->report_number,
            'sample_size' => $this->sample_size,
            'result' => $this->result,
            'rejected_qty' => $this->rejected_qty,
            'inspected_at' => $this->inspected_at,
            'remarks' => $this->remarks,
        ], $this->checks);

        session()->flash('success', __('Inspection report saved.'));
        $this->redirectRoute('admin::quality.incoming.reports.show', $report, navigate: true);
    }

    public function with(): array
    {
        return [
            'lots' => IncomingLot::query()->with('part')->orderByDesc('received_at')->get(),
        ];
    }

    private function loadLot(): void
    {
        $this->checks = [];
        $this->planLabel = null;
        $this->standardLabel = null;

        $lot = IncomingLot::query()->with('part')->find($this->lot_id);

        if (! $lot || ! $lot->part) {
            return;
        }

        $standard = $lot->part->currentStandard();

        if (! $standard) {
            return;
        }

        $standard->load('characteristics');
        $plan = SamplingPlan::forQuantity((float) $lot->qty) ?? $standard->samplingPlan;
        $this->standardLabel = $standard->code.' · '.$standard->title;
        $this->planLabel = $plan ? $plan->code.' · sample '.$plan->sample_size.' for lot '.Qty::format($lot->qty) : null;
        $this->sample_size = (string) ($plan?->sample_size ?? $standard->samplingPlan?->sample_size ?? 1);

        foreach ($standard->characteristics as $characteristic) {
            $this->checks[] = [
                'name' => $characteristic->name,
                'specification' => $characteristic->specification,
                'min' => $characteristic->tolerance_min,
                'max' => $characteristic->tolerance_max,
                'unit' => $characteristic->unit,
                'is_critical' => $characteristic->is_critical,
                'measured' => '',
                'result' => 'ok',
                'remarks' => '',
            ];
        }
    }

    private function judge(int $index): void
    {
        $check = $this->checks[$index] ?? null;

        if (! $check || $check['min'] === null || $check['max'] === null || ! is_numeric($check['measured'])) {
            return;
        }

        $value = (float) $check['measured'];
        $this->checks[$index]['result'] = $value >= (float) $check['min'] && $value <= (float) $check['max'] ? 'ok' : 'ng';
    }

    private function suggestResult(): void
    {
        if ($this->resultLocked) {
            return;
        }

        $criticalNg = collect($this->checks)->contains(fn ($check) => $check['is_critical'] && $check['result'] === 'ng');
        $anyNg = collect($this->checks)->contains(fn ($check) => $check['result'] === 'ng');

        $this->result = match (true) {
            $criticalNg => 'fail',
            $anyNg => 'conditional',
            default => 'pass',
        };
    }
}; ?>

<div>
    <x-core::ui.page-header
        :title="__('New inspection report')"
        :subtitle="__('The active standard for the part is compared with what was measured on this batch.')"
        :breadcrumbs="[
            admin_breadcrumb_home(),
            ['label' => __('Inspection reports'), 'route' => 'admin::quality.incoming.reports'],
            ['label' => __('Create')],
        ]"
    />

    <form wire:submit="save" class="space-y-4">
        <div class="admin-card grid gap-4 p-4 md:grid-cols-2">
            <x-core::forms.select2
                wire:model.live="lot_id"
                :label="__('Batch / lot')"
                :options="$lots->mapWithKeys(fn ($lot) => [$lot->id => $lot->lot_number.' · '.$lot->part?->part_number.' · '.Qty::format($lot->qty)])"
                :placeholder="__('Select')"
                :allow-clear="false"
            />
            <flux:input wire:model="report_number" :label="__('Report number')" required />
            <flux:input wire:model="sample_size" type="number" min="1" :label="__('Sample size')" />
            <flux:input wire:model="inspected_at" type="datetime-local" :label="__('Inspected at')" />
            <x-core::forms.select2
                wire:model="result"
                :label="__('Lot result')"
                :options="collect(['pass', 'conditional', 'fail'])->mapWithKeys(fn ($option) => [$option => (string) str($option)->title()])"
                :allow-clear="false"
            />
            @if ($result === 'conditional')
                <flux:input wire:model="rejected_qty" type="number" step="0.001" min="0" :label="__('Rejected qty')" />
            @endif
            <div class="md:col-span-2 text-sm text-zinc-500">
                @if ($standardLabel) <p>{{ $standardLabel }}</p> @endif
                @if ($planLabel) <p>{{ $planLabel }}</p> @endif
                @error('lot_id') <p class="text-red-600">{{ $message }}</p> @enderror
            </div>
            <div class="md:col-span-2">
                <flux:textarea wire:model="remarks" :label="__('Remarks')" rows="2" />
            </div>
        </div>

        @if ($checks !== [])
            <div class="overflow-x-auto admin-card">
                <table class="min-w-full divide-y divide-zinc-200 dark:divide-zinc-700">
                    <thead class="bg-[var(--admin-surface-muted)]">
                        <tr>
                            @foreach ([__('Characteristic'), __('Specification'), __('Measured'), __('Result')] as $heading)
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-zinc-500">{{ $heading }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                        @foreach ($checks as $index => $check)
                            <tr wire:key="measure-{{ $index }}">
                                <td class="px-4 py-3 text-sm">
                                    {{ $check['name'] }}
                                    @if ($check['is_critical'])
                                        <span class="ms-1 text-xs text-red-600">{{ __('Critical') }}</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-sm text-zinc-500">{{ $check['specification'] }}</td>
                                <td class="px-4 py-3">
                                    <flux:input wire:model.live="checks.{{ $index }}.measured" :placeholder="$check['unit'] ?: __('Value or note')" />
                                </td>
                                <td class="px-4 py-3">
                                    <flux:select wire:model="checks.{{ $index }}.result">
                                        <flux:select.option value="ok">{{ __('OK') }}</flux:select.option>
                                        <flux:select.option value="ng">{{ __('NG') }}</flux:select.option>
                                    </flux:select>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif

        <div class="flex justify-end gap-2">
            <flux:button variant="ghost" :href="route('admin::quality.incoming.reports')" wire:navigate>{{ __('Cancel') }}</flux:button>
            <flux:button type="submit" variant="primary" :disabled="$checks === []">{{ __('Save report') }}</flux:button>
        </div>
    </form>
</div>
