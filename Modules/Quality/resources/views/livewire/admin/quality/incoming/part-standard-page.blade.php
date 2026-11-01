<?php

use App\Concerns\NotifiesWithSweetAlert;
use App\Models\MediaStorage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Modules\Quality\Models\IncomingPart;
use Modules\Quality\Models\InspectionCharacteristic;
use Modules\Quality\Models\InspectionStandard;
use Modules\Quality\Support\InspectionStandardSheet;

new #[Layout('core::layouts.app')] class extends Component {
    use NotifiesWithSweetAlert;

    public int $partId = 0;

    public string $partNumber = '';

    public string $partName = '';

    public ?int $standardId = null;

    public string $code = '';

    public string $supplier_name = '';

    public string $supplier_code = '';

    public string $doc_no = '';

    public string $page_label = '1 of 1';

    public string $revision = '1';

    public string $effective_from = '';

    public string $model = '';

    public string $drawing_no = '';

    public string $drawing_rev = '';

    public string $approved_by = '';

    public string $checked_by = '';

    public string $prepared_by = '';

    public array $lines = [];

    public string $document = '';

    public string $documentName = '';

    public bool $removeDocument = false;

    public function mount(IncomingPart $part): void
    {
        $part->load('vendor');
        $this->partId = $part->id;
        $this->partNumber = $part->part_number;
        $this->partName = $part->name;
        $this->effective_from = now()->toDateString();
        $this->supplier_name = (string) ($part->vendor?->name ?? '');
        $this->supplier_code = (string) ($part->vendor?->code ?? '');
        $this->lines = [$this->blankLine()];

        $standard = $part->editableStandard()?->load('characteristics');

        if ($standard) {
            $this->fillStandard($standard);
        }

        if ($this->supplier_name === '') {
            $this->supplier_name = (string) ($part->vendor?->name ?? '');
        }

        if ($this->supplier_code === '') {
            $this->supplier_code = (string) ($part->vendor?->code ?? '');
        }
    }

    public function updatedDocument(?string $value): void
    {
        if ($value === null || $value === '') {
            return;
        }

        $this->removeDocument = false;
        $media = MediaStorage::query()->where('url', $value)->first();
        $this->documentName = $media
            ? $media->name.($media->extension ? '.'.$media->extension : '')
            : basename((string) (parse_url($value, PHP_URL_PATH) ?: $value));

        $extension = strtolower(pathinfo($this->documentName, PATHINFO_EXTENSION));

        if ($extension === 'xls') {
            $this->notifyWarning(__('Save the standard as .xlsx to fill the form from it.'));

            return;
        }

        if ($extension !== 'xlsx' || ! $media) {
            return;
        }

        $disk = Storage::disk($media->disk ?: 'public_uploads');

        if (! $disk->exists($media->path)) {
            return;
        }

        try {
            $this->applyImportedSheet(InspectionStandardSheet::read($disk->path($media->path)));
        } catch (\Throwable) {
            $this->notifyWarning(__('The Excel file was saved, but its layout was not recognized, so the fields were left as they are.'));
        }
    }

    /**
     * @param  array<string, mixed>  $sheet
     */
    private function applyImportedSheet(array $sheet): void
    {
        foreach (['code', 'doc_no', 'page_label', 'revision', 'model', 'drawing_no', 'drawing_rev', 'approved_by', 'checked_by', 'prepared_by', 'supplier_name', 'supplier_code'] as $field) {
            if (filled($sheet[$field] ?? null)) {
                $this->{$field} = (string) $sheet[$field];
            }
        }

        if (! empty($sheet['effective_from']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $sheet['effective_from'])) {
            $this->effective_from = (string) $sheet['effective_from'];
        }

        if (($sheet['lines'] ?? []) !== []) {
            $this->lines = array_map(fn (array $line): array => [
                'key' => (string) Str::uuid(),
                'id' => null,
                'name' => $line['name'],
                'specification' => $line['specification'],
                'method' => $line['method'],
                'instrument' => $line['instrument'],
                'sample_plan' => $line['sample_plan'],
                'inspection_stage' => $line['inspection_stage'],
                'characteristic_class' => InspectionCharacteristic::normalize($line['characteristic_class'] ?? null),
                'remarks' => $line['remarks'],
            ], $sheet['lines']);
        }

        $this->notifySuccess(__('Fields filled from the uploaded Excel. Review them, then save.'));
    }

    public function addLine(): void
    {
        $this->lines[] = $this->blankLine();
    }

    public function removeLine(int $index): void
    {
        if (count($this->lines) <= 1) {
            return;
        }

        unset($this->lines[$index]);
        $this->lines = array_values($this->lines);
    }

    public function clearDocument(): void
    {
        if ($this->document !== '') {
            $this->deleteMediaUrl($this->document);
            $this->document = '';
        }

        $this->documentName = '';
        $this->removeDocument = true;
    }

    public function save(): void
    {
        $part = IncomingPart::query()->findOrFail($this->partId);
        $existing = $part->editableStandard();
        $this->standardId = $existing?->id;

        $data = $this->validate([
            'code' => ['required', 'string', 'max:60', Rule::unique('inspection_standards', 'code')->ignore($this->standardId)],
            'supplier_name' => ['nullable', 'string', 'max:160'],
            'supplier_code' => ['nullable', 'string', 'max:40'],
            'doc_no' => ['nullable', 'string', 'max:40'],
            'page_label' => ['nullable', 'string', 'max:20'],
            'revision' => ['required', 'string', 'max:20'],
            'effective_from' => ['required', 'date'],
            'model' => ['nullable', 'string', 'max:160'],
            'drawing_no' => ['nullable', 'string', 'max:60'],
            'drawing_rev' => ['nullable', 'string', 'max:20'],
            'approved_by' => ['nullable', 'string', 'max:120'],
            'checked_by' => ['nullable', 'string', 'max:120'],
            'prepared_by' => ['nullable', 'string', 'max:120'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.id' => ['nullable', 'integer'],
            'lines.*.name' => ['required', 'string', 'max:160'],
            'lines.*.specification' => ['required', 'string', 'max:2000'],
            'lines.*.method' => ['nullable', 'string', 'max:160'],
            'lines.*.instrument' => ['nullable', 'string', 'max:160'],
            'lines.*.sample_plan' => ['nullable', 'string', 'max:255'],
            'lines.*.inspection_stage' => ['nullable', 'string', 'max:80'],
            'lines.*.characteristic_class' => ['required', 'string', Rule::in(array_keys(InspectionCharacteristic::CLASSES))],
            'lines.*.remarks' => ['nullable', 'string', 'max:2000'],
            'document' => ['nullable', 'string', 'max:255', function (string $attribute, mixed $value, \Closure $fail): void {
                if (! is_string($value) || $value === '') {
                    return;
                }

                $path = (string) (parse_url($value, PHP_URL_PATH) ?: $value);
                $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

                if (! in_array($extension, ['pdf', 'xls', 'xlsx'], true)) {
                    $fail(__('The document must be an Excel or PDF file.'));
                }
            }],
        ]);

        $standard = DB::transaction(function () use ($data, $existing) {
            $standard = $existing ?? new InspectionStandard([
                'part_id' => $this->partId,
                'status' => 'active',
            ]);

            $standard->fill([
                'code' => $data['code'],
                'part_id' => $this->partId,
                'revision' => $data['revision'],
                'title' => $data['model'] !== '' ? $data['model'] : $this->partName,
                'effective_from' => $data['effective_from'],
                'supplier_name' => $data['supplier_name'] ?: null,
                'supplier_code' => $data['supplier_code'] ?: null,
                'doc_no' => $data['doc_no'] ?: null,
                'page_label' => $data['page_label'] ?: null,
                'drawing_no' => $data['drawing_no'] ?: null,
                'drawing_rev' => $data['drawing_rev'] ?: null,
                'approved_by' => $data['approved_by'] ?: null,
                'checked_by' => $data['checked_by'] ?: null,
                'prepared_by' => $data['prepared_by'] ?: null,
            ])->save();

            $kept = [];

            foreach (array_values($data['lines']) as $index => $line) {
                $model = ! empty($line['id'])
                    ? $standard->characteristics()->whereKey($line['id'])->first()
                    : null;
                $model ??= $standard->characteristics()->make();
                $model->fill([
                    'sequence' => $index + 1,
                    'name' => $line['name'],
                    'specification' => $line['specification'],
                    'method' => $line['method'] ?: null,
                    'instrument' => $line['instrument'] ?: null,
                    'sample_plan' => $line['sample_plan'] ?: null,
                    'inspection_stage' => $line['inspection_stage'] ?: null,
                    'characteristic_class' => InspectionCharacteristic::normalize($line['characteristic_class'] ?? null),
                    'remarks' => $line['remarks'] ?: null,
                ])->save();
                $kept[] = $model->id;
            }

            $standard->characteristics()->whereNotIn('id', $kept)->delete();
            $this->storeDocument($standard);

            return $standard;
        });

        $this->document = '';
        $this->removeDocument = false;
        $this->fillStandard($standard->fresh('characteristics'));
        $this->notifySuccess(__('Standard saved.'));
    }

    private function fillStandard(InspectionStandard $standard): void
    {
        $this->standardId = $standard->id;
        $this->code = $standard->code;
        $this->supplier_name = (string) $standard->supplier_name;
        $this->supplier_code = (string) $standard->supplier_code;
        $this->doc_no = (string) $standard->doc_no;
        $this->page_label = (string) ($standard->page_label ?: '1 of 1');
        $this->revision = $standard->revision;
        $this->effective_from = $standard->effective_from?->toDateString() ?? '';
        $this->model = (string) $standard->title;
        $this->drawing_no = (string) $standard->drawing_no;
        $this->drawing_rev = (string) $standard->drawing_rev;
        $this->approved_by = (string) $standard->approved_by;
        $this->checked_by = (string) $standard->checked_by;
        $this->prepared_by = (string) $standard->prepared_by;
        $this->document = '';
        $this->documentName = (string) $standard->document_name;
        $this->lines = $standard->characteristics->map(fn ($line) => [
            'key' => (string) Str::uuid(),
            'id' => $line->id,
            'name' => $line->name,
            'specification' => $line->specification,
            'method' => (string) $line->method,
            'instrument' => (string) $line->instrument,
            'sample_plan' => (string) $line->sample_plan,
            'inspection_stage' => (string) $line->inspection_stage,
            'characteristic_class' => InspectionCharacteristic::normalize($line->characteristic_class),
            'remarks' => (string) $line->remarks,
        ])->all();

        if ($this->lines === []) {
            $this->lines = [$this->blankLine()];
        }
    }

    private function storeDocument(InspectionStandard $standard): void
    {
        if ($this->document !== '') {
            $media = MediaStorage::query()->where(function ($query): void {
                $query->where('url', $this->document)->orWhere('path', $this->document);
            })->first();
            $newPath = $media?->path ?: ltrim((string) (parse_url($this->document, PHP_URL_PATH) ?: $this->document), '/');
            $newPath = str_starts_with($newPath, 'uploads/') ? substr($newPath, strlen('uploads/')) : $newPath;
            $newName = $media
                ? $media->name.($media->extension ? '.'.$media->extension : '')
                : basename($newPath);

            if ($standard->document_path && $standard->document_path !== $newPath) {
                $this->deleteStoredFile($standard->document_path);
            }

            $standard->forceFill([
                'document_path' => $newPath,
                'document_name' => $newName,
            ])->save();

            return;
        }

        if ($this->removeDocument && $standard->document_path) {
            $this->deleteStoredFile($standard->document_path);
            $standard->forceFill([
                'document_path' => null,
                'document_name' => null,
            ])->save();
        }
    }

    private function deleteMediaUrl(string $url): void
    {
        $media = MediaStorage::query()->where('url', $url)->first();

        if (! $media) {
            return;
        }

        $this->deleteStoredFile($media->path);
        $media->delete();
    }

    private function deleteStoredFile(string $path): void
    {
        foreach (['public_uploads', 'local'] as $disk) {
            if (Storage::disk($disk)->exists($path)) {
                Storage::disk($disk)->delete($path);
            }
        }

        MediaStorage::query()->where(function ($query) use ($path): void {
            $query->where('path', $path)->orWhere('url', $path)->orWhere('url', 'uploads/'.$path);
        })->delete();
    }

    private function blankLine(): array
    {
        return [
            'key' => (string) Str::uuid(),
            'id' => null,
            'name' => '',
            'specification' => '',
            'method' => '',
            'instrument' => '',
            'sample_plan' => '',
            'inspection_stage' => '',
            'characteristic_class' => 'C',
            'remarks' => '',
        ];
    }

}; ?>

<div>
    <x-core::ui.page-header
        :title="$partNumber.' · '.$partName.' — '.__('Incoming standard')"
        :subtitle="__('One inspection standard for this part.')"
        :breadcrumbs="[
            admin_breadcrumb_home(),
            ['label' => __('Incoming Quality parts'), 'route' => 'admin::quality.incoming.parts'],
            ['label' => $partNumber],
        ]"
    >
        <x-slot:actions>
            @if ($standardId)
                <flux:button size="sm" icon="arrow-down-tray" :href="route('admin::quality.incoming.parts.standard.pdf', $partId)">{{ __('Generate PDF') }}</flux:button>
                <flux:button size="sm" icon="arrow-down-tray" :href="route('admin::quality.incoming.parts.standard.excel', $partId)">{{ __('Generate Excel') }}</flux:button>
            @endif
        </x-slot:actions>
    </x-core::ui.page-header>

    <form wire:submit="save" class="space-y-4">
        <div class="admin-card p-4">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                <div class="min-w-0 flex-1 lg:max-w-xl">
                    <x-core::forms.filepond
                        wire:model="document"
                        model="document"
                        folder="documents"
                        :label="__('Document')"
                        :description="__('One Excel or PDF file. An .xlsx inspection standard fills the form. Uploading again replaces it.')"
                        :accepted-file-types="['application/pdf', 'application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']"
                        max-file-size="10MB"
                    />
                </div>
                @if ($documentName !== '' && ! $removeDocument)
                    <div class="flex flex-wrap items-center gap-2 lg:pt-7">
                        <span class="text-sm font-medium">{{ $documentName }}</span>
                        @if ($standardId && $document === '')
                            <flux:button size="sm" icon="arrow-down-tray" :href="route('admin::quality.incoming.parts.standard.document', $partId)">{{ __('Download') }}</flux:button>
                        @elseif ($document !== '')
                            <flux:button size="sm" icon="arrow-down-tray" :href="asset(ltrim($document, '/'))">{{ __('Download') }}</flux:button>
                        @endif
                        <flux:button type="button" size="sm" variant="ghost" wire:click="clearDocument">{{ __('Remove') }}</flux:button>
                    </div>
                @endif
            </div>
            @error('document') <p class="mt-2 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>

        @if ($errors->any())
            <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                <ul class="list-disc pl-5">
                    @foreach ($errors->all() as $message)
                        <li>{{ $message }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="overflow-x-auto rounded-lg border border-zinc-300 bg-white text-zinc-900">
            <table class="iis-sheet">
                <colgroup>
                    <col style="width: 7.5%">
                    <col style="width: 10.5%">
                    <col style="width: 7.5%">
                    <col style="width: 6%">
                    <col style="width: 10.5%">
                    <col style="width: 11.5%">
                    <col style="width: 9.5%">
                    <col style="width: 9.5%">
                    <col style="width: 8.5%">
                    <col style="width: 10%">
                    <col style="width: 9%">
                </colgroup>
                <tbody>
                    <tr>
                        <th class="iis-head iis-title" colspan="11">{{ __('Inspection Standard - Parts') }}</th>
                    </tr>
                    <tr>
                        <th class="iis-head">UMAC-IIS</th>
                        <th class="iis-head" colspan="3">{{ __("Supplier's Name") }}</th>
                        <th class="iis-head" colspan="3">{{ __("Supplier's Code") }}</th>
                        <th class="iis-head">{{ __('Doc.No.') }}</th>
                        <td class="iis-head"><input wire:model="doc_no" /></td>
                        <th class="iis-head">{{ __('Page') }}</th>
                        <td class="iis-head"><input wire:model="page_label" /></td>
                    </tr>
                    <tr>
                        <td class="iis-head">
                            <span class="iis-label">{{ __('No.') }}</span>
                            <input wire:model="code" />
                        </td>
                        <td class="iis-head" colspan="3"></td>
                        <td class="iis-head" colspan="3"></td>
                        <th class="iis-head">{{ __('Rev.No.') }}</th>
                        <td class="iis-head"><input wire:model="revision" /></td>
                        <td class="iis-head" colspan="2"></td>
                    </tr>
                    <tr>
                        <td class="iis-head"></td>
                        <td class="iis-head" colspan="3"><input wire:model="supplier_name" /></td>
                        <td class="iis-head" colspan="3"><input wire:model="supplier_code" /></td>
                        <th class="iis-head">{{ __('Rev.Date') }}</th>
                        <td class="iis-head"><input wire:model="effective_from" type="date" /></td>
                        <th class="iis-head">{{ __('Model') }}</th>
                        <td class="iis-head"><input wire:model="model" /></td>
                    </tr>
                    <tr>
                        <td class="iis-head"></td>
                        <th class="iis-head" colspan="3">{{ __('Part Name') }}</th>
                        <th class="iis-head" colspan="3">{{ __('Part No.') }}</th>
                        <th class="iis-head iis-left">{{ __('Drawing No.') }}</th>
                        <td class="iis-head" colspan="3"><input wire:model="drawing_no" /></td>
                    </tr>
                    <tr>
                        <td class="iis-head"></td>
                        <td class="iis-head iis-fixed" colspan="3">{{ $partName }}</td>
                        <td class="iis-head iis-fixed" colspan="3">{{ $partNumber }}</td>
                        <th class="iis-head iis-left">{{ __('Drawing Rev.No.') }}</th>
                        <td class="iis-head" colspan="3"><input wire:model="drawing_rev" /></td>
                    </tr>
                    <tr>
                        <th class="iis-head" rowspan="2">{{ __('S.No.') }}</th>
                        <th class="iis-head" colspan="2" rowspan="2">{{ __('Inspection Item') }}</th>
                        <th class="iis-head" colspan="2" rowspan="2">{{ __('Criteria') }}</th>
                        <th class="iis-head">{{ __('Inspection') }}</th>
                        <th class="iis-head">{{ __('Inspection') }}</th>
                        <th class="iis-head">{{ __('Sample') }}</th>
                        <th class="iis-head">{{ __('Inspection') }}</th>
                        <th class="iis-head" rowspan="2">{{ __('CHARC. CLASS') }}</th>
                        <th class="iis-head" rowspan="2">{{ __('Remarks') }}</th>
                    </tr>
                    <tr>
                        <th class="iis-head">{{ __('Method') }}</th>
                        <th class="iis-head">{{ __('Tool') }}</th>
                        <th class="iis-head">{{ __('Plan') }}</th>
                        <th class="iis-head">{{ __('Stage') }}</th>
                    </tr>
                    @foreach ($lines as $index => $line)
                        <tr class="iis-data" wire:key="line-{{ $line['key'] }}">
                            <td class="iis-center">
                                {{ $index + 1 }}
                                @if (count($lines) > 1)
                                    <button type="button" class="mt-1 block w-full text-[10px] underline" wire:click="removeLine({{ $index }})">{{ __('Remove') }}</button>
                                @endif
                            </td>
                            <td colspan="2"><input wire:model="lines.{{ $index }}.name" /></td>
                            <td colspan="2"><textarea wire:model="lines.{{ $index }}.specification" rows="2"></textarea></td>
                            <td><input wire:model="lines.{{ $index }}.method" /></td>
                            <td><input wire:model="lines.{{ $index }}.instrument" /></td>
                            <td><input wire:model="lines.{{ $index }}.sample_plan" /></td>
                            <td><input wire:model="lines.{{ $index }}.inspection_stage" /></td>
                            <td class="iis-center iis-class">
                                <select wire:model="lines.{{ $index }}.characteristic_class">
                                    @foreach (InspectionCharacteristic::CLASSES as $value => $label)
                                        <option value="{{ $value }}">{{ __($label) }}</option>
                                    @endforeach
                                </select>
                            </td>
                            <td><textarea wire:model="lines.{{ $index }}.remarks" rows="2"></textarea></td>
                        </tr>
                    @endforeach
                    <tr>
                        <td colspan="11" class="iis-pad">
                            <button type="button" class="text-sm font-medium underline" wire:click="addLine">{{ __('Add inspection line') }}</button>
                        </td>
                    </tr>
                    <tr>
                        <th class="iis-head" colspan="11">{{ __('General Tolerance') }}</th>
                    </tr>
                    <tr>
                        <th class="iis-head">{{ __('Above') }}</th>
                        @foreach (['0.5', '3', '6', '30', '120', '315', '1000'] as $band)
                            <td class="iis-head iis-center">{{ $band }}</td>
                        @endforeach
                        <th class="iis-head">{{ __('Approved By') }}</th>
                        <td class="iis-head" colspan="2"><input wire:model="approved_by" /></td>
                    </tr>
                    <tr>
                        <th class="iis-head">{{ __('Up to and including') }}</th>
                        @foreach (['3', '6', '30', '120', '315', '1000', '2000'] as $band)
                            <td class="iis-head iis-center">{{ $band }}</td>
                        @endforeach
                        <th class="iis-head">{{ __('Checked By') }}</th>
                        <td class="iis-head" colspan="2"><input wire:model="checked_by" /></td>
                    </tr>
                    <tr>
                        <th class="iis-head">{{ __('Tolerance') }}</th>
                        @foreach (['± 0.1', '± 0.1', '± 0.2', '± 0.3', '± 0.5', '± 0.8', '± 1.2'] as $band)
                            <td class="iis-head iis-center">{{ $band }}</td>
                        @endforeach
                        <th class="iis-head">{{ __('Prepared By') }}</th>
                        <td class="iis-head" colspan="2"><input wire:model="prepared_by" /></td>
                    </tr>
                    <tr>
                        <td class="iis-head iis-legend" colspan="11">V.C = Vernier Caliper &nbsp;&nbsp; MIC Met = Micro Meter &nbsp;&nbsp; TPG = Thread Plug Gauge &nbsp;&nbsp; V.H.G = Vernier Height Gauge &nbsp;&nbsp; TRG = Thread Ring Gauge</td>
                    </tr>
                    <tr>
                        <td class="iis-head iis-legend" colspan="11">H.T = Hardness Tester &nbsp;&nbsp; D.T.G = Dial Thickness Gauge</td>
                    </tr>
                    <tr>
                        <td class="iis-head iis-legend" colspan="11">{{ __('Category:') }} &nbsp;&nbsp; {{ __('Critical') }} &nbsp;&nbsp; {{ __('Major') }} &nbsp;&nbsp; {{ __('Minor') }} &nbsp;&nbsp; {{ __('Others') }}</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="flex flex-wrap justify-end gap-2">
            @if ($standardId)
                <flux:button icon="arrow-down-tray" :href="route('admin::quality.incoming.parts.standard.pdf', $partId)">{{ __('Generate PDF') }}</flux:button>
                <flux:button icon="arrow-down-tray" :href="route('admin::quality.incoming.parts.standard.excel', $partId)">{{ __('Generate Excel') }}</flux:button>
            @endif
            <flux:button variant="ghost" :href="route('admin::quality.incoming.parts')" wire:navigate>{{ __('Cancel') }}</flux:button>
            <flux:button type="submit" variant="primary" wire:loading.attr="disabled" wire:target="save">{{ __('Save standard') }}</flux:button>
        </div>
    </form>

    <style>
        .iis-sheet { width: 100%; min-width: 1180px; border-collapse: collapse; table-layout: fixed; background: #fff; color: #111; }
        .iis-sheet th, .iis-sheet td { border: 1px solid #222; padding: 8px 10px; vertical-align: middle; font-size: 13px; font-weight: 700; line-height: 1.4; }
        .iis-sheet td { font-weight: 400; }
        .iis-head { background: #f4f4f5; }
        .iis-title { font-size: 18px; text-align: center; padding: 14px 10px; }
        .iis-data td { height: 48px; }
        .iis-center { text-align: center; }
        .iis-fixed { text-align: center; font-weight: 700; }
        .iis-left { text-align: left; }
        .iis-label { display: block; font-size: 12px; font-weight: 700; }
        .iis-legend { text-align: center; font-weight: 700; font-size: 12px; padding: 10px; }
        .iis-pad { padding: 12px; background: #fff; }
        .iis-sheet input, .iis-sheet textarea, .iis-sheet select {
            width: 100%;
            background: transparent;
            border: 0;
            outline: 0;
            box-shadow: none;
            padding: 4px 2px;
            font-size: 13px;
            text-align: center;
            color: #111;
        }
        .iis-sheet textarea { text-align: left; resize: vertical; }
        .iis-sheet select,
        .iis-class select {
            cursor: pointer;
            appearance: none;
            -webkit-appearance: none;
            -moz-appearance: none;
            background-image: none;
            border: 0 !important;
            outline: 0 !important;
            box-shadow: none !important;
            min-width: 100%;
            white-space: nowrap;
        }
        .iis-sheet input:focus, .iis-sheet textarea:focus, .iis-sheet select:focus {
            outline: 1px solid #0f766e;
            background: #fff;
            border: 0;
            box-shadow: none;
        }
    </style>
</div>
