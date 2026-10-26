<?php

use App\Concerns\NotifiesWithSweetAlert;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Livewire\WithPagination;
use Modules\Quality\Models\SamplingPlan;

new #[Layout('core::layouts.app')] class extends Component {
    use NotifiesWithSweetAlert;
    use WithPagination;

    public bool $showFlyout = false;

    public ?int $planId = null;

    public string $code = '';

    public string $name = '';

    public string $standard_ref = 'ISO 2859-1';

    public string $inspection_level = 'II';

    public string $aql = '1';

    public string $lot_min = '';

    public string $lot_max = '';

    public string $sample_size = '';

    public string $accept_number = '0';

    public string $reject_number = '1';

    public string $notes = '';

    public bool $is_active = true;

    public function createPlan(): void
    {
        $this->reset(['planId', 'code', 'name', 'lot_min', 'lot_max', 'sample_size', 'notes']);
        $this->standard_ref = 'ISO 2859-1';
        $this->inspection_level = 'II';
        $this->aql = '1';
        $this->accept_number = '0';
        $this->reject_number = '1';
        $this->is_active = true;
        $this->resetValidation();
        $this->showFlyout = true;
    }

    public function editPlan(int $id): void
    {
        $plan = SamplingPlan::query()->findOrFail($id);
        $this->planId = $plan->id;
        $this->code = $plan->code;
        $this->name = $plan->name;
        $this->standard_ref = $plan->standard_ref;
        $this->inspection_level = $plan->inspection_level;
        $this->aql = (string) $plan->aql;
        $this->lot_min = (string) $plan->lot_min;
        $this->lot_max = (string) $plan->lot_max;
        $this->sample_size = (string) $plan->sample_size;
        $this->accept_number = (string) $plan->accept_number;
        $this->reject_number = (string) $plan->reject_number;
        $this->notes = (string) $plan->notes;
        $this->is_active = $plan->is_active;
        $this->showFlyout = true;
    }

    public function savePlan(): void
    {
        $data = $this->validate([
            'code' => ['required', 'string', 'max:40', Rule::unique('sampling_plans', 'code')->ignore($this->planId)],
            'name' => ['required', 'string', 'max:160'],
            'standard_ref' => ['required', 'string', 'max:80'],
            'inspection_level' => ['required', 'string', 'max:20'],
            'aql' => ['required', 'numeric', 'gt:0'],
            'lot_min' => ['required', 'integer', 'min:1'],
            'lot_max' => ['required', 'integer', 'gte:lot_min'],
            'sample_size' => ['required', 'integer', 'min:1'],
            'accept_number' => ['required', 'integer', 'min:0'],
            'reject_number' => ['required', 'integer', 'gt:accept_number'],
            'notes' => ['nullable', 'string'],
            'is_active' => ['boolean'],
        ]);

        $plan = $this->planId ? SamplingPlan::query()->findOrFail($this->planId) : new SamplingPlan();
        $plan->fill($data)->save();
        $this->showFlyout = false;
        $this->notifySuccess(__('Sampling plan saved.'));
    }

    public function deletePlan(int $id): void
    {
        SamplingPlan::query()->findOrFail($id)->delete();
        $this->notifySuccess(__('Sampling plan deleted.'));
    }

    public function with(): array
    {
        return [
            'plans' => SamplingPlan::query()->orderBy('lot_min')->paginate(15),
        ];
    }
}; ?>

<div>
    <x-core::ui.page-header
        :title="__('Sampling plan standard')"
        :subtitle="__('Lot-size bands decide how many pieces to inspect. The report picks the band that fits the batch quantity.')"
        :breadcrumbs="[
            admin_breadcrumb_home(),
            ['label' => __('Sampling plans')],
        ]"
    >
        <x-slot:actions>
            <flux:button variant="primary" icon="plus" wire:click="createPlan">{{ __('Add band') }}</flux:button>
        </x-slot:actions>
    </x-core::ui.page-header>

    <div class="overflow-x-auto admin-card">
        <table class="min-w-full divide-y divide-zinc-200 dark:divide-zinc-700">
            <thead class="bg-[var(--admin-surface-muted)]">
                <tr>
                    @foreach ([__('Code'), __('Standard'), __('Level'), __('AQL'), __('Lot size'), __('Sample'), __('Ac / Re')] as $heading)
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-zinc-500">{{ $heading }}</th>
                    @endforeach
                    <th></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                @forelse ($plans as $plan)
                    <tr wire:key="plan-{{ $plan->id }}">
                        <td class="px-4 py-3 text-sm font-medium">{{ $plan->code }}</td>
                        <td class="px-4 py-3 text-sm">{{ $plan->standard_ref }}</td>
                        <td class="px-4 py-3 text-sm">{{ $plan->inspection_level }}</td>
                        <td class="px-4 py-3 text-sm">{{ $plan->aql }}</td>
                        <td class="px-4 py-3 text-sm">{{ $plan->bandLabel() }}</td>
                        <td class="px-4 py-3 text-sm">{{ $plan->sample_size }}</td>
                        <td class="px-4 py-3 text-sm">{{ $plan->accept_number }} / {{ $plan->reject_number }}</td>
                        <td class="px-4 py-3 text-right">
                            <x-core::ui.actions-menu>
                                <flux:menu.item icon="pencil-square" icon:variant="outline" wire:click="editPlan({{ $plan->id }})">{{ __('Edit') }}</flux:menu.item>
                                <flux:menu.separator />
                                <x-core::ui.confirm-button as="menu-item" method="deletePlan" :params="[$plan->id]" />
                            </x-core::ui.actions-menu>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="px-4 py-12 text-center text-sm text-zinc-500">{{ __('No sampling bands yet.') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if ($plans->hasPages()) <div class="mt-4">{{ $plans->links() }}</div> @endif

    <flux:modal wire:model="showFlyout" variant="flyout" class="md:min-w-[28rem] !rounded-s-3xl !ps-8 !pe-8">
        <form wire:submit="savePlan" class="flex h-full flex-col gap-4">
            <flux:heading size="lg">{{ $planId ? __('Edit sampling band') : __('Add sampling band') }}</flux:heading>
            <div class="flex-1 space-y-3 overflow-y-auto pe-2">
                <flux:input wire:model="code" :label="__('Code')" required />
                <flux:input wire:model="name" :label="__('Name')" required />
                <div class="grid grid-cols-2 gap-3">
                    <flux:input wire:model="standard_ref" :label="__('Standard')" />
                    <flux:input wire:model="inspection_level" :label="__('Level')" />
                </div>
                <flux:input wire:model="aql" type="number" step="0.001" :label="__('AQL')" />
                <div class="grid grid-cols-2 gap-3">
                    <flux:input wire:model="lot_min" type="number" :label="__('Lot min')" />
                    <flux:input wire:model="lot_max" type="number" :label="__('Lot max')" />
                </div>
                <div class="grid grid-cols-3 gap-3">
                    <flux:input wire:model="sample_size" type="number" :label="__('Sample')" />
                    <flux:input wire:model="accept_number" type="number" :label="__('Accept')" />
                    <flux:input wire:model="reject_number" type="number" :label="__('Reject')" />
                </div>
                <flux:textarea wire:model="notes" :label="__('Notes')" rows="2" />
                <flux:switch wire:model="is_active" :label="__('Active')" />
            </div>
            <div class="flex justify-end gap-2 border-t border-zinc-200 pt-4 dark:border-zinc-700">
                <flux:button type="button" variant="ghost" wire:click="$set('showFlyout', false)">{{ __('Cancel') }}</flux:button>
                <flux:button type="submit" variant="primary">{{ __('Save') }}</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
