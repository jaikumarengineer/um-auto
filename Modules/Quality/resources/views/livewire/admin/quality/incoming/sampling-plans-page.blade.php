<?php

use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;
use App\Concerns\AuthorizesModule;
use App\Concerns\NotifiesWithSweetAlert;
use Modules\Quality\Models\SamplingPlan;
use Modules\Quality\Support\Ml08SamplingPlan;

new #[Layout('core::layouts.app')] #[Title('Sampling Plan Standards')] class extends Component
{
    use AuthorizesModule;
    use NotifiesWithSweetAlert;

    private const MODULE = 'quality.incoming.sampling';

    /** @var array<string, string> */
    public array $grid = [];

    public function mount(): void
    {
        Ml08SamplingPlan::ensureLoaded();
        $this->loadGrid();
    }

    public function saveGrid(): void
    {
        $this->authorizeModule(self::MODULE, 'edit');

        $parsed = [];
        $errors = [];

        foreach ($this->grid as $key => $value) {
            $raw = trim((string) $value);

            if ($raw === '*' || $raw === '0') {
                $parsed[$key] = 0;

                continue;
            }

            if (preg_match('/^\d+$/', $raw) === 1 && (int) $raw > 0) {
                $parsed[$key] = (int) $raw;

                continue;
            }

            $errors['grid.'.$key] = __('Enter a whole number or *.');
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        $ids = [];

        foreach (array_keys($parsed) as $key) {
            $ids[] = (int) substr((string) $key, 1);
        }

        $plans = SamplingPlan::query()->whereIn('id', $ids)->get()->keyBy('id');

        foreach ($parsed as $key => $sample) {
            $plan = $plans->get((int) substr((string) $key, 1));

            if (! $plan) {
                continue;
            }

            $notes = $plan->notes;

            if ($sample === 0) {
                $notes = 'Entire lot must be inspected.';
            } elseif ($notes === 'Entire lot must be inspected.') {
                $notes = null;
            }

            $plan->update([
                'sample_size' => $sample,
                'notes' => $notes,
                'accept_number' => 0,
                'reject_number' => 1,
            ]);
        }

        $this->loadGrid();
        $this->notifySuccess(__('Sampling plan saved.'));
    }

    public function with(): array
    {
        $plans = SamplingPlan::query()
            ->where('standard_ref', Ml08SamplingPlan::STANDARD)
            ->where('is_active', true)
            ->orderBy('lot_min')
            ->get();

        $bands = [];

        foreach ($plans as $plan) {
            $key = $plan->lot_min.'-'.$plan->lot_max;

            if (! isset($bands[$key])) {
                $open = (int) $plan->lot_max >= Ml08SamplingPlan::OPEN_LOT;
                $bands[$key] = [
                    'label' => $open
                        ? $plan->lot_min.' and over'
                        : $plan->lot_min.' to '.$plan->lot_max,
                    'cells' => [],
                ];
            }

            $bands[$key]['cells'][$plan->aqlLabel()] = $plan->id;
        }

        return [
            'bands' => array_values($bands),
            'aqls' => Ml08SamplingPlan::AQLS,
            'marks' => [
                '1' => __('Critical'),
                '2.5' => __('Major'),
                '6.5' => __('Others'),
            ],
            'canEdit' => module_can(self::MODULE, 'edit'),
        ];
    }

    private function loadGrid(): void
    {
        $this->grid = [];

        $plans = SamplingPlan::query()
            ->where('standard_ref', Ml08SamplingPlan::STANDARD)
            ->where('is_active', true)
            ->get(['id', 'sample_size']);

        foreach ($plans as $plan) {
            $this->grid['c'.$plan->id] = $plan->sampleLabel();
        }
    }
}; ?>

<div>
    <x-core::ui.page-header :title="__('Sampling Plan Standards')" :breadcrumbs="[
        ['label' => __('Dashboard'), 'href' => route('admin::dashboard')],
        ['label' => __('Sampling Plan Standards')],
    ]">
        <x-slot:subtitle>
            {{ __('ML-08 C=0 sheet. Each cell is the sample size for that lot range and AQL. Type * when the whole lot is inspected. Accept is always 0.') }}
        </x-slot:subtitle>
        <x-slot:actions>
            @if ($canEdit)
                <x-core::ui.confirm-button
                    method="saveGrid"
                    :title="__('Are you sure?')"
                    :text="__('Save these sample sizes to the ML-08 plan?')"
                    :confirm-text="__('Yes, save it')"
                    confirm-button-class="admin-swal-confirm"
                >
                    <flux:button variant="primary" icon="check" type="button">{{ __('Save') }}</flux:button>
                </x-core::ui.confirm-button>
            @endif
        </x-slot:actions>
    </x-core::ui.page-header>

    @error('grid')
        <p class="mb-3 text-sm text-red-600">{{ $message }}</p>
    @enderror

    <div id="sampling-matrix" class="max-h-[calc(100vh-16rem)] overflow-auto rounded-xl border border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-900">
        <table class="min-w-full border-separate border-spacing-0 text-sm">
            <thead>
                <tr>
                    <th class="sticky top-0 left-0 z-30 min-w-36 border-b border-r border-zinc-200 bg-zinc-50 px-3 py-2 text-left text-xs font-semibold uppercase tracking-wide text-zinc-500 dark:border-zinc-700 dark:bg-zinc-800">
                        {{ __('Lot size') }}
                    </th>
                    @foreach ($aqls as $aql)
                        @php
                            $heading = in_array($aql, ['1', '4', '10'], true) ? $aql.'.0' : $aql;
                            $mark = $marks[$aql] ?? '';
                            $tone = match ($aql) {
                                '1' => 'bg-red-50 text-red-700 dark:bg-red-950/40 dark:text-red-200',
                                '2.5' => 'bg-sky-50 text-sky-700 dark:bg-sky-950/40 dark:text-sky-200',
                                '6.5' => 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-200',
                                default => 'bg-zinc-50 text-zinc-600 dark:bg-zinc-800 dark:text-zinc-300',
                            };
                        @endphp
                        <th class="sticky top-0 z-20 min-w-16 border-b border-r border-zinc-200 px-1 py-2 text-center last:border-r-0 dark:border-zinc-700 {{ $tone }}">
                            <div class="h-4 text-[10px] font-semibold uppercase tracking-wide">{{ $mark }}</div>
                            <div class="font-semibold">{{ $heading }}</div>
                        </th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @foreach ($bands as $band)
                    <tr wire:key="band-{{ $band['label'] }}">
                        <th class="sticky left-0 z-10 whitespace-nowrap border-b border-r border-zinc-200 bg-white px-3 py-1.5 text-left text-sm font-medium text-zinc-800 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-100">
                            {{ $band['label'] }}
                        </th>
                        @foreach ($aqls as $aql)
                            @php
                                $id = $band['cells'][$aql] ?? null;
                                $key = $id ? 'c'.$id : null;
                                $tone = match ($aql) {
                                    '1' => 'bg-red-50/70 dark:bg-red-950/20',
                                    '2.5' => 'bg-sky-50/70 dark:bg-sky-950/20',
                                    '6.5' => 'bg-emerald-50/70 dark:bg-emerald-950/20',
                                    default => 'bg-white dark:bg-zinc-900',
                                };
                                $heading = in_array($aql, ['1', '4', '10'], true) ? $aql.'.0' : $aql;
                            @endphp
                            <td class="border-b border-r border-zinc-200 p-0 text-center last:border-r-0 dark:border-zinc-700 {{ $tone }}">
                                @if ($key && $canEdit)
                                    <input
                                        type="text"
                                        wire:model="grid.{{ $key }}"
                                        aria-label="{{ $band['label'] }} AQL {{ $heading }}"
                                        class="w-full bg-transparent px-1 py-1.5 text-center text-sm tabular-nums outline-none focus:bg-white focus:ring-2 focus:ring-inset focus:ring-zinc-400 dark:focus:bg-zinc-800 {{ $errors->has('grid.'.$key) ? 'ring-2 ring-inset ring-red-500' : '' }}"
                                    />
                                @elseif ($key)
                                    <span class="inline-block px-1 py-1.5 tabular-nums">{{ $grid[$key] ?? '' }}</span>
                                @endif
                            </td>
                        @endforeach
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <ul class="mt-3 space-y-1 text-xs text-zinc-500">
        <li>{{ __('* means the entire lot must be inspected.') }}</li>
        <li>{{ __('The acceptance number in all cases is zero. One defective unit rejects that class.') }}</li>
        <li>{{ __('Class columns: Critical AQL 1.0, Major AQL 2.5, Others AQL 6.5.') }}</li>
    </ul>
</div>
