<?php

use App\Concerns\NotifiesWithSweetAlert;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Modules\Quality\Models\IncomingPart;
use Modules\Quality\Models\InspectionStandard;
use Modules\Quality\Models\SamplingPlan;

new #[Layout('core::layouts.app')] class extends Component {
    use NotifiesWithSweetAlert;

    public ?int $standardId = null;

    public string $code = '';

    public string $part_id = '';

    public string $sampling_plan_id = '';

    public string $revision = 'A';

    public string $title = '';

    public string $effective_from = '';

    public string $status = 'active';

    public string $notes = '';

    public array $checks = [];

    public function mount(?int $standard = null): void
    {
        $this->effective_from = now()->toDateString();
        $this->addCheck();

        if (! $standard) {
            return;
        }

        $model = InspectionStandard::query()->with('characteristics')->findOrFail($standard);
        $this->standardId = $model->id;
        $this->code = $model->code;
        $this->part_id = (string) $model->part_id;
        $this->sampling_plan_id = (string) ($model->sampling_plan_id ?? '');
        $this->revision = $model->revision;
        $this->title = $model->title;
        $this->effective_from = $model->effective_from?->toDateString() ?? '';
        $this->status = $model->status;
        $this->notes = (string) $model->notes;
        $this->checks = $model->characteristics->map(fn ($check) => [
            'key' => (string) Str::uuid(),
            'id' => $check->id,
            'name' => $check->name,
            'specification' => $check->specification,
            'method' => (string) $check->method,
            'instrument' => (string) $check->instrument,
            'tolerance_min' => $check->tolerance_min,
            'tolerance_max' => $check->tolerance_max,
            'unit' => (string) $check->unit,
            'is_critical' => $check->is_critical,
        ])->all();
    }

    public function addCheck(): void
    {
        $this->checks[] = [
            'key' => (string) Str::uuid(),
            'id' => null,
            'name' => '',
            'specification' => '',
            'method' => '',
            'instrument' => '',
            'tolerance_min' => '',
            'tolerance_max' => '',
            'unit' => '',
            'is_critical' => false,
        ];
    }

    public function removeCheck(int $index): void
    {
        unset($this->checks[$index]);
        $this->checks = array_values($this->checks);
    }

    public function save(): void
    {
        $this->validate([
            'code' => ['required', 'string', 'max:60', Rule::unique('inspection_standards', 'code')->ignore($this->standardId)],
            'part_id' => ['required', 'exists:incoming_parts,id'],
            'sampling_plan_id' => ['nullable', 'exists:sampling_plans,id'],
            'revision' => ['required', 'string', 'max:20'],
            'title' => ['required', 'string', 'max:160'],
            'effective_from' => ['required', 'date'],
            'status' => ['required', 'in:draft,active,obsolete'],
            'checks' => ['required', 'array', 'min:1'],
            'checks.*.name' => ['required', 'string', 'max:160'],
            'checks.*.specification' => ['required', 'string', 'max:255'],
        ]);

        $standard = $this->standardId
            ? InspectionStandard::query()->findOrFail($this->standardId)
            : new InspectionStandard();

        $standard->fill([
            'code' => $this->code,
            'part_id' => $this->part_id,
            'sampling_plan_id' => $this->sampling_plan_id ?: null,
            'revision' => $this->revision,
            'title' => $this->title,
            'effective_from' => $this->effective_from,
            'status' => $this->status,
            'notes' => $this->notes ?: null,
        ])->save();

        $kept = [];

        foreach (array_values($this->checks) as $index => $check) {
            $model = ! empty($check['id'])
                ? $standard->characteristics()->whereKey($check['id'])->first()
                : $standard->characteristics()->make();

            $model->fill([
                'sequence' => $index + 1,
                'name' => $check['name'],
                'specification' => $check['specification'],
                'method' => $check['method'] ?: null,
                'instrument' => $check['instrument'] ?: null,
                'tolerance_min' => $check['tolerance_min'] === '' ? null : $check['tolerance_min'],
                'tolerance_max' => $check['tolerance_max'] === '' ? null : $check['tolerance_max'],
                'unit' => $check['unit'] ?: null,
                'is_critical' => (bool) ($check['is_critical'] ?? false),
            ])->save();

            $kept[] = $model->id;
        }

        $standard->characteristics()->whereNotIn('id', $kept)->delete();

        session()->flash('success', __('Inspection standard saved.'));
        $this->redirectRoute('admin::quality.incoming.standards', navigate: true);
    }

    public function with(): array
    {
        return [
            'parts' => IncomingPart::query()->where('is_active', true)->orderBy('part_number')->get(),
            'plans' => SamplingPlan::query()->where('is_active', true)->orderBy('lot_min')->get(),
        ];
    }
}; ?>

<div>
    <x-core::ui.page-header
        :title="$standardId ? $code : __('New inspection standard')"
        :subtitle="__('Numeric limits auto-judge the inspection report. Leave them blank for visual checks.')"
        :breadcrumbs="[
            admin_breadcrumb_home(),
            ['label' => __('Inspection standards'), 'route' => 'admin::quality.incoming.standards'],
            ['label' => $standardId ? $code : __('Create')],
        ]"
    />

    <form wire:submit="save" class="space-y-4">
        <div class="admin-card grid gap-4 p-4 md:grid-cols-2">
            <flux:input wire:model="code" :label="__('Code')" required />
            <flux:input wire:model="title" :label="__('Title')" required />
            <x-core::forms.select2
                wire:model="part_id"
                :label="__('Part')"
                :options="$parts->mapWithKeys(fn ($part) => [$part->id => $part->label()])"
                :placeholder="__('Select')"
                :allow-clear="false"
            />
            <x-core::forms.select2
                wire:model="sampling_plan_id"
                :label="__('Default sampling plan')"
                :options="$plans->mapWithKeys(fn ($plan) => [$plan->id => $plan->code.' · '.$plan->bandLabel()])"
                :placeholder="__('Pick from the lot size later')"
            />
            <flux:input wire:model="revision" :label="__('Revision')" />
            <flux:input wire:model="effective_from" type="date" :label="__('Effective from')" />
            <x-core::forms.select2
                wire:model="status"
                :label="__('Status')"
                :options="collect(['draft', 'active', 'obsolete'])->mapWithKeys(fn ($option) => [$option => (string) str($option)->title()])"
                :allow-clear="false"
            />
            <div class="md:col-span-2">
                <flux:textarea wire:model="notes" :label="__('Notes')" rows="2" />
            </div>
        </div>

        <div class="admin-card p-4">
            <div class="mb-3 flex items-center justify-between">
                <flux:heading size="lg">{{ __('Characteristics') }}</flux:heading>
                <flux:button type="button" variant="ghost" icon="plus" wire:click="addCheck">{{ __('Add check') }}</flux:button>
            </div>
            <div class="space-y-3">
                @foreach ($checks as $index => $check)
                    <div class="grid gap-3 rounded-2xl border border-zinc-200 p-3 dark:border-zinc-700 md:grid-cols-12" wire:key="check-{{ $check['key'] }}">
                        <div class="md:col-span-3"><flux:input wire:model="checks.{{ $index }}.name" :label="__('Characteristic')" /></div>
                        <div class="md:col-span-3"><flux:input wire:model="checks.{{ $index }}.specification" :label="__('Specification')" /></div>
                        <div class="md:col-span-2"><flux:input wire:model="checks.{{ $index }}.method" :label="__('Method')" /></div>
                        <div class="md:col-span-2"><flux:input wire:model="checks.{{ $index }}.instrument" :label="__('Instrument')" /></div>
                        <div class="md:col-span-2 flex items-end"><flux:button type="button" variant="ghost" icon="trash" wire:click="removeCheck({{ $index }})">{{ __('Remove') }}</flux:button></div>
                        <div class="md:col-span-2"><flux:input wire:model="checks.{{ $index }}.tolerance_min" type="number" step="0.0001" :label="__('Min')" /></div>
                        <div class="md:col-span-2"><flux:input wire:model="checks.{{ $index }}.tolerance_max" type="number" step="0.0001" :label="__('Max')" /></div>
                        <div class="md:col-span-2"><flux:input wire:model="checks.{{ $index }}.unit" :label="__('Unit')" /></div>
                        <div class="flex items-end md:col-span-3"><flux:switch wire:model="checks.{{ $index }}.is_critical" :label="__('Critical')" /></div>
                    </div>
                @endforeach
            </div>
        </div>

        <div class="flex justify-end gap-2">
            <flux:button variant="ghost" :href="route('admin::quality.incoming.standards')" wire:navigate>{{ __('Cancel') }}</flux:button>
            <flux:button type="submit" variant="primary">{{ __('Save standard') }}</flux:button>
        </div>
    </form>
</div>
