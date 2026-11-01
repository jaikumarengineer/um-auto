<?php

use App\Concerns\AuthorizesModule;
use App\Concerns\WithSorting;
use App\Helpers\FileUploadHelper;
use App\Models\MediaStorage;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Livewire\WithPagination;
use Modules\Quality\Models\ApprovedVendor;
use Modules\Quality\Models\IncomingLot;
use Modules\Quality\Models\IncomingPart;
use Modules\Quality\Models\InspectionCharacteristic;
use Modules\Quality\Models\InspectionReport;
use Modules\Quality\Models\PurchaseInvoice;
use Modules\Quality\Services\QualityWorkflow;
use Modules\Quality\Support\InspectionReadings;
use Modules\Quality\Support\Ml08SampleQuote;
use Modules\Quality\Support\Qty;

new #[Layout('core::layouts.app')] class extends Component {
    use AuthorizesModule;
    use WithPagination;
    use WithSorting;

    private const MODULE = 'quality.incoming.reports';

    public ?int $reportId = null;

    public string $reportNumber = '';

    public string $part_id = '';

    public string $part_label = '';

    public string $part_name = '';

    public string $part_code = '';

    public string $vendor_id = '';

    public string $supplier_name = '';

    public string $supplier_code = '';

    public string $invoice_id = '';

    public string $lot_id = '';

    public string $lot_number = '';

    public string $lot_qty = '';

    public string $sample_size = '';

    public string $accepted_qty = '';

    public string $rejected_qty = '0';

    public string $assembly_name = '';

    public string $process = '';

    public string $bill_no = '';

    public string $supplier_decision = 'conforming';

    public string $umac_decision = 'conforming';

    public bool $supplierDecisionLocked = false;

    public bool $umacDecisionLocked = false;

    public string $inspected_at = '';

    public string $inspector_name = '';

    public string $remarks = '';

    /** @var list<array<string, mixed>> */
    public array $checks = [];

    /** @var array<string, array<string, mixed>> */
    public array $sampleQuotes = [];

    /** @var list<array{id: int|string|null, url: string}> */
    public array $images = [];

    public ?string $planLabel = null;

    public ?string $standardLabel = null;

    public ?string $sheetError = null;

    public bool $showPartPicker = false;

    public string $partSearch = '';

    public string $sortBy = 'part_number';

    public string $sortDirection = 'asc';

    protected array $sortable = ['part_number', 'name', 'uom', 'approved_on'];

    protected string $sortPaginationPageName = 'partsPage';

    public function mount(?InspectionReport $report = null): void
    {
        $this->authorizeModule(self::MODULE, $report?->exists ? 'edit' : 'add');

        $this->inspected_at = now()->format('Y-m-d\TH:i');
        $this->inspector_name = (string) (auth()->user()?->full_name ?? '');

        if ($report?->exists) {
            $this->fillFromReport($report);

            return;
        }

        $lot = request()->integer('lot');

        if ($lot) {
            $incomingLot = IncomingLot::query()
                ->with(['part.vendors', 'part.vendor', 'invoiceLine.invoice', 'vendor'])
                ->find($lot);

            if ($incomingLot?->part) {
                $this->applyPart($incomingLot->part);
                $this->vendor_id = (string) ($incomingLot->vendor_id
                    ?? $incomingLot->invoiceLine?->invoice?->vendor_id
                    ?? $incomingLot->part->vendor_id
                    ?? '');
                $this->invoice_id = (string) ($incomingLot->invoiceLine?->purchase_invoice_id ?? '');
                $this->lot_id = (string) $incomingLot->id;
                $this->loadLot();
            }
        }
    }

    public function openPartPicker(): void
    {
        $this->partSearch = '';
        $this->resetPage('partsPage');
        $this->showPartPicker = true;
    }

    public function closePartPicker(): void
    {
        $this->showPartPicker = false;
    }

    public function updatingPartSearch(): void
    {
        $this->resetPage('partsPage');
    }

    public function selectPart(int $partId): void
    {
        $part = IncomingPart::query()
            ->with(['vendors', 'vendor'])
            ->withExists('standards')
            ->findOrFail($partId);

        if (! $part->standards_exists) {
            return;
        }

        $this->applyPart($part);
        $this->resetDownstreamSelection(keepVendor: false);
        $this->showPartPicker = false;
    }

    public function clearPart(): void
    {
        $this->part_id = '';
        $this->part_label = '';
        $this->part_name = '';
        $this->part_code = '';
        $this->vendor_id = '';
        $this->resetDownstreamSelection(keepVendor: false);
    }

    public function updatedVendorId(): void
    {
        $this->invoice_id = '';
        $this->lot_id = '';
        $this->clearSheet();
    }

    public function updatedInvoiceId(): void
    {
        $this->lot_id = '';
        $this->clearSheet();
    }

    public function updatedLotId(): void
    {
        $this->loadLot();
    }

    public function updated(string $name, mixed $value = null): void
    {
        if ($name === 'accepted_qty') {
            $this->syncRejected();
        }

        if ($name === 'supplier_decision') {
            $this->supplierDecisionLocked = true;
        }

        if ($name === 'umac_decision') {
            $this->umacDecisionLocked = true;
        }

        if (preg_match('/^checks\.\d+\.(supplier|umac)(\.\d+|_status)$/', $name)) {
            $this->suggestDecisions();
        }

        if (preg_match('/^checks\.(\d+)\.class$/', $name, $matches)) {
            $index = (int) $matches[1];

            if (! isset($this->checks[$index])) {
                return;
            }

            $class = InspectionCharacteristic::normalize($this->checks[$index]['class'] ?? 'C');
            $this->checks[$index]['class'] = $class;

            if (! isset($this->sampleQuotes[$class]) && $this->lot_qty !== '') {
                $added = Ml08SampleQuote::forClasses(Qty::num($this->lot_qty), [$class]);
                $this->sampleQuotes[$class] = $added[$class];
                $this->refreshQuoteLabels();
            }

            $this->fitCheck($index);
        }
    }

    public function removeImage(int $index): void
    {
        $image = $this->images[$index] ?? null;

        if (! is_array($image)) {
            return;
        }

        $this->deleteImage($image['id'] ?? null);
        unset($this->images[$index]);
        $this->images = array_values($this->images);
    }

    public function cellState(int $index, string $side, int $sample): string
    {
        $check = $this->checks[$index] ?? null;

        if (! is_array($check)) {
            return 'empty';
        }

        return InspectionReadings::state($check[$side][$sample] ?? '', $check['min'] ?? null, $check['max'] ?? null);
    }

    public function sideStatus(int $index, string $side): string
    {
        $check = $this->checks[$index] ?? null;

        if (! is_array($check)) {
            return 'empty';
        }

        if (empty($check['measurable'])) {
            return InspectionReadings::manualStatus($check[$side.'_status'] ?? null);
        }

        return InspectionReadings::status($check[$side] ?? [], $check['min'] ?? null, $check['max'] ?? null);
    }

    public function overallStatus(string $side): string
    {
        $statuses = [];

        foreach (array_keys($this->checks) as $index) {
            $statuses[] = $this->sideStatus((int) $index, $side);
        }

        return InspectionReadings::rollup($statuses);
    }

    public function statusLabel(string $status): string
    {
        return InspectionReadings::label($status);
    }

    public function sampleCount(): int
    {
        if ($this->sampleQuotes !== []) {
            return max(1, max(array_map(fn (array $quote): int => (int) $quote['sample_size'], $this->sampleQuotes)));
        }

        return max(1, (int) $this->sample_size);
    }

    public function rowSampleCount(int $index): int
    {
        $class = InspectionCharacteristic::normalize($this->checks[$index]['class'] ?? 'C');

        return $this->sampleSizeFor($class);
    }

    /**
     * @return array{defectives: int, percent: float, result: string}
     */
    public function classVerdict(string $class, string $side): array
    {
        $lines = [];

        foreach ($this->checks as $check) {
            if (InspectionCharacteristic::normalize($check['class'] ?? '') !== $class) {
                continue;
            }

            $lines[] = [
                'readings' => is_array($check[$side] ?? null) ? $check[$side] : [],
                'manual' => empty($check['measurable']),
                'status' => $check[$side.'_status'] ?? '',
                'min' => $check['min'] ?? null,
                'max' => $check['max'] ?? null,
            ];
        }

        $quote = $this->sampleQuotes[$class] ?? null;

        return Ml08SampleQuote::judge(
            (int) ($quote['sample_size'] ?? $this->sampleSizeFor($class)),
            (int) ($quote['accept_number'] ?? 0),
            $lines,
        );
    }

    public function save(QualityWorkflow $workflow): void
    {
        $this->syncRejected();
        $this->suggestDecisions();

        $this->validate([
            'part_id' => ['required', 'exists:incoming_parts,id'],
            'vendor_id' => ['required', 'exists:approved_vendors,id'],
            'invoice_id' => [
                'required',
                Rule::exists('purchase_invoices', 'id')->where(function ($query) {
                    $query->where('vendor_id', $this->vendor_id);
                }),
            ],
            'lot_id' => [
                'required',
                Rule::exists('incoming_lots', 'id')->where(function ($query) {
                    $query->where('part_id', $this->part_id)
                        ->whereIn('purchase_invoice_line_id', function ($sub) {
                            $sub->select('id')
                                ->from('purchase_invoice_lines')
                                ->where('purchase_invoice_id', $this->invoice_id)
                                ->where('part_id', $this->part_id);
                        });
                }),
            ],
            'sample_size' => ['required', 'integer', 'min:1'],
            'accepted_qty' => ['required', 'numeric', 'min:0'],
            'assembly_name' => ['nullable', 'string', 'max:160'],
            'process' => ['nullable', 'string', 'max:160'],
            'bill_no' => ['nullable', 'string', 'max:80'],
            'supplier_decision' => ['required', Rule::in(['conforming', 'non_conforming', 'aud'])],
            'umac_decision' => ['required', Rule::in(['conforming', 'non_conforming', 'aud'])],
            'inspected_at' => ['required', 'date'],
            'checks' => ['required', 'array', 'min:1'],
            'checks.*.class' => ['required', Rule::in(array_keys(InspectionCharacteristic::CLASSES))],
            'checks.*.remarks' => ['nullable', 'string', 'max:2000'],
            'checks.*.supplier' => ['array'],
            'checks.*.umac' => ['array'],
            'checks.*.supplier.*' => ['nullable', 'string', 'max:120'],
            'checks.*.umac.*' => ['nullable', 'string', 'max:120'],
            'checks.*.supplier_status' => ['nullable', Rule::in(['', 'ok', 'ng'])],
            'checks.*.umac_status' => ['nullable', Rule::in(['', 'ok', 'ng'])],
            'images' => ['array'],
            'images.*.url' => ['nullable', 'string', 'max:255'],
            'images.*.id' => ['nullable', 'integer'],
        ]);

        foreach ($this->checks as $index => $check) {
            if (! empty($check['measurable'])) {
                continue;
            }

            foreach (['supplier', 'umac'] as $side) {
                $filled = false;

                foreach ($check[$side] ?? [] as $value) {
                    if (trim((string) $value) !== '') {
                        $filled = true;
                        break;
                    }
                }

                if ($filled && ! in_array($check[$side.'_status'] ?? '', ['ok', 'ng'], true)) {
                    $this->addError("checks.$index.{$side}_status", __('Choose OK or Not OK.'));
                }
            }
        }

        if ($this->getErrorBag()->isNotEmpty()) {
            return;
        }

        $existing = $this->resolveExistingReport();

        if ($existing) {
            $this->authorizeModule(self::MODULE, 'edit');
        }

        $report = $workflow->saveInspectionReport([
            'lot_id' => $this->lot_id,
            'report_number' => $existing?->report_number ?: $this->reportNumber,
            'sample_size' => $this->sample_size,
            'accepted_qty' => $this->accepted_qty,
            'supplier_decision' => $this->supplier_decision,
            'umac_decision' => $this->umac_decision,
            'inspected_at' => $this->inspected_at,
            'remarks' => $this->remarks,
            'assembly_name' => $this->assembly_name,
            'process' => $this->process,
            'bill_no' => $this->bill_no,
            'images' => $this->images,
            'inspected_by' => $existing?->inspected_by ?? auth()->id(),
        ], $this->checks, $existing);

        session()->flash('success', __('Inspection report saved.'));
        $this->redirectRoute('admin::quality.incoming.reports.show', $report, navigate: true);
    }

    public function with(): array
    {
        $partVendors = collect();
        $invoices = collect();
        $lots = collect();

        if ($this->part_id !== '') {
            $part = IncomingPart::query()->with(['vendors', 'vendor'])->find($this->part_id);
            $partVendors = $part?->vendors?->isNotEmpty()
                ? $part->vendors
                : collect(array_filter([$part?->vendor]));
        }

        if ($this->part_id !== '' && $this->vendor_id !== '') {
            $invoices = PurchaseInvoice::query()
                ->with('vendor')
                ->where('vendor_id', $this->vendor_id)
                ->whereHas('lines', function ($query) {
                    $query->where('part_id', $this->part_id)->whereHas('lots');
                })
                ->orderByDesc('invoice_date')
                ->orderByDesc('id')
                ->get();
        }

        if ($this->part_id !== '' && $this->invoice_id !== '') {
            $lots = IncomingLot::query()
                ->with('part')
                ->where('part_id', $this->part_id)
                ->whereHas('invoiceLine', fn ($query) => $query->where('purchase_invoice_id', $this->invoice_id))
                ->orderByDesc('received_at')
                ->get();
        }

        return [
            'partVendors' => $partVendors,
            'invoices' => $invoices,
            'lots' => $lots,
            'decisions' => [
                'conforming' => __('CONFORMING'),
                'non_conforming' => __('NON CONFORMING'),
                'aud' => __('A.U.D.'),
            ],
            'pickerParts' => $this->showPartPicker
                ? $this->applySorting(
                    IncomingPart::query()
                        ->with(['vendors', 'vendor', 'category'])
                        ->withExists('standards')
                        ->where('is_active', true)
                        ->when($this->partSearch !== '', function ($query) {
                            $term = '%'.$this->partSearch.'%';
                            $query->where(function ($query) use ($term) {
                                $query->where('part_number', 'like', $term)
                                    ->orWhere('name', 'like', $term)
                                    ->orWhereHas('category', fn ($category) => $category->where('name', 'like', $term))
                                    ->orWhereHas('vendors', function ($vendors) use ($term) {
                                        $vendors->where('name', 'like', $term)->orWhere('code', 'like', $term);
                                    });
                            });
                        })
                )->paginate(10, pageName: 'partsPage')
                : null,
        ];
    }

    private function resolveExistingReport(): ?InspectionReport
    {
        if ($this->reportId) {
            $report = InspectionReport::query()->find($this->reportId);

            if ($report) {
                $this->reportNumber = $report->report_number;

                return $report;
            }
        }

        $number = trim($this->reportNumber);

        if ($number === '') {
            return null;
        }

        $report = InspectionReport::query()->where('report_number', $number)->first();

        if ($report) {
            $this->reportId = $report->id;
        }

        return $report;
    }

    private function applyPart(IncomingPart $part): void
    {
        $part->loadMissing(['vendors', 'vendor']);
        $this->part_id = (string) $part->id;
        $this->part_label = $part->label();
        $this->part_name = $part->name;
        $this->part_code = $part->part_number;
    }

    private function resetDownstreamSelection(bool $keepVendor = true): void
    {
        if (! $keepVendor) {
            $this->vendor_id = '';
        }

        $this->invoice_id = '';
        $this->lot_id = '';
        $this->clearSheet();
    }

    private function fillFromReport(InspectionReport $report): void
    {
        $report->load([
            'lot.part.vendors',
            'lot.part.vendor',
            'lot.invoiceLine.invoice',
            'lot.vendor',
            'results',
            'samples',
            'inspector',
        ]);

        $lot = $report->lot;
        abort_unless($lot?->part, 404);

        $this->reportId = $report->id;
        $this->reportNumber = $report->report_number;
        $this->applyPart($lot->part);
        $this->vendor_id = (string) ($lot->vendor_id
            ?? $lot->invoiceLine?->invoice?->vendor_id
            ?? $lot->part->vendor_id
            ?? '');
        $this->invoice_id = (string) ($lot->invoiceLine?->purchase_invoice_id ?? '');
        $this->lot_id = (string) $lot->id;
        $this->images = array_values(array_filter(
            is_array($report->images) ? $report->images : [],
            'is_array',
        ));
        $this->loadLot(keepImages: true);

        $this->assembly_name = (string) ($report->assembly_name ?? '');
        $this->process = (string) ($report->process ?? '');
        $this->bill_no = (string) ($report->bill_no ?? $this->bill_no);
        $this->accepted_qty = Qty::format($report->accepted_qty);
        $this->syncRejected();

        if ($report->samples->isNotEmpty()) {
            $this->applyQuotes(Ml08SampleQuote::fromSnapshot($report->samples));
        } else {
            $saved = max(1, (int) $report->sample_size);
            $this->sample_size = (string) $saved;

            foreach ($this->sampleQuotes as $class => $quote) {
                $this->sampleQuotes[$class]['sample_size'] = $saved;
            }

            $this->refreshQuoteLabels();
        }

        $this->supplier_decision = $report->supplier_decision ?: 'conforming';
        $this->umac_decision = $report->umac_decision ?: 'conforming';
        $this->supplierDecisionLocked = true;
        $this->umacDecisionLocked = true;
        $this->inspected_at = $report->inspected_at?->format('Y-m-d\TH:i') ?? $this->inspected_at;
        $this->inspector_name = (string) ($report->inspector?->full_name ?: $this->inspector_name);
        $this->remarks = (string) ($report->remarks ?? '');

        $results = $report->results->values();

        foreach ($this->checks as $index => $check) {
            $result = $results->first(
                fn ($row) => $row->characteristic_name === ($check['name'] ?? '')
            ) ?? $results->get($index);

            if ($result) {
                $this->checks[$index]['class'] = InspectionCharacteristic::normalize($result->characteristic_class ?: $check['class']);
            }

            $this->fitCheck($index);
            $samples = $this->rowSampleCount($index);
            $this->checks[$index]['supplier'] = array_fill(0, $samples, '');
            $this->checks[$index]['umac'] = array_fill(0, $samples, '');

            if (! $result) {
                continue;
            }

            $this->checks[$index]['supplier'] = InspectionReadings::pad(
                is_array($result->supplier_readings) ? $result->supplier_readings : [],
                $samples,
            );
            $this->checks[$index]['umac'] = InspectionReadings::pad(
                is_array($result->umac_readings) ? $result->umac_readings : [],
                $samples,
            );
            $this->checks[$index]['supplier_status'] = in_array($result->supplier_result, ['ok', 'ng'], true)
                ? $result->supplier_result
                : '';
            $this->checks[$index]['umac_status'] = in_array($result->umac_result, ['ok', 'ng'], true)
                ? $result->umac_result
                : '';
            $this->checks[$index]['remarks'] = (string) ($result->remarks ?? '');
        }
    }

    private function clearSheet(bool $keepImages = false): void
    {
        if (! $keepImages) {
            $this->clearImages();
        }
        $this->checks = [];
        $this->sampleQuotes = [];
        $this->planLabel = null;
        $this->standardLabel = null;
        $this->sheetError = null;
        $this->sample_size = '';
        $this->lot_qty = '';
        $this->lot_number = '';
        $this->accepted_qty = '';
        $this->rejected_qty = '0';
        $this->supplier_name = '';
        $this->supplier_code = '';
        $this->assembly_name = '';
        $this->process = '';
        $this->bill_no = '';
        $this->supplier_decision = 'conforming';
        $this->umac_decision = 'conforming';
        $this->supplierDecisionLocked = false;
        $this->umacDecisionLocked = false;
    }

    private function loadLot(bool $keepImages = false): void
    {
        $this->clearSheet($keepImages);

        $lot = IncomingLot::query()->with(['part.vendor', 'vendor', 'invoiceLine.invoice'])->find($this->lot_id);

        if (! $lot || ! $lot->part) {
            return;
        }

        if ($this->part_id !== '' && (string) $lot->part_id !== $this->part_id) {
            $this->lot_id = '';

            return;
        }

        if ($this->invoice_id !== '' && (string) ($lot->invoiceLine?->purchase_invoice_id ?? '') !== $this->invoice_id) {
            $this->lot_id = '';

            return;
        }

        if ($this->part_id === '') {
            $this->applyPart($lot->part);
        }

        if ($this->invoice_id === '' && $lot->invoiceLine) {
            $this->invoice_id = (string) $lot->invoiceLine->purchase_invoice_id;
        }

        $this->lot_number = $lot->lot_number;
        $this->lot_qty = Qty::format($lot->qty);
        $this->accepted_qty = $this->lot_qty;
        $this->bill_no = (string) ($lot->invoiceLine?->invoice?->invoice_number ?? '');
        $vendor = $this->vendor_id !== '' ? ApprovedVendor::query()->find($this->vendor_id) : $lot->vendor;
        $this->supplier_name = (string) ($vendor?->name ?? '');
        $this->supplier_code = (string) ($vendor?->code ?? '');
        $this->syncRejected();

        $standard = $lot->part->currentStandard();

        if (! $standard) {
            $this->sheetError = __('This part has no active incoming inspection standard.');

            return;
        }

        $standard->load('characteristics');

        if ($standard->characteristics->isEmpty()) {
            $this->sheetError = __('The inspection standard has no characteristics yet.');

            return;
        }

        $classes = $standard->characteristics
            ->map(fn ($characteristic) => InspectionCharacteristic::normalize($characteristic->characteristic_class))
            ->all();
        $this->applyQuotes(Ml08SampleQuote::forClasses((float) $lot->qty, $classes));
        $this->standardLabel = $standard->code.' · '.$standard->title;

        foreach ($standard->characteristics as $characteristic) {
            $limits = InspectionReadings::limits(
                $characteristic->tolerance_min,
                $characteristic->tolerance_max,
                $characteristic->specification,
            );

            $class = InspectionCharacteristic::normalize($characteristic->characteristic_class);
            $samples = $this->sampleSizeFor($class);

            $this->checks[] = [
                'name' => $characteristic->name,
                'specification' => $characteristic->specification,
                'method' => (string) $characteristic->method,
                'class' => $class,
                'min' => $limits['min'],
                'max' => $limits['max'],
                'measurable' => is_numeric($limits['min']) && is_numeric($limits['max']),
                'supplier' => array_fill(0, $samples, ''),
                'umac' => array_fill(0, $samples, ''),
                'supplier_status' => '',
                'umac_status' => '',
                'remarks' => '',
            ];
        }
    }

    private function sampleSizeFor(string $class): int
    {
        $class = InspectionCharacteristic::normalize($class);

        return max(1, (int) ($this->sampleQuotes[$class]['sample_size'] ?? $this->sample_size ?: 1));
    }

    private function fitCheck(int $index): void
    {
        if (! isset($this->checks[$index])) {
            return;
        }

        $samples = $this->rowSampleCount($index);
        $this->checks[$index]['supplier'] = InspectionReadings::pad($this->checks[$index]['supplier'] ?? [], $samples);
        $this->checks[$index]['umac'] = InspectionReadings::pad($this->checks[$index]['umac'] ?? [], $samples);
    }

    /**
     * @param  array<string, array<string, mixed>>  $quotes
     */
    private function applyQuotes(array $quotes): void
    {
        $this->sampleQuotes = $quotes;
        $this->refreshQuoteLabels();
    }

    private function refreshQuoteLabels(): void
    {
        $headline = Ml08SampleQuote::headline($this->sampleQuotes);
        $this->sample_size = (string) max(1, (int) ($headline['sample_size'] ?? $this->sample_size ?: 1));
        $this->planLabel = $this->sampleQuotes === [] ? null : Ml08SampleQuote::summary($this->sampleQuotes);
    }

    private function suggestDecisions(): void
    {
        if (! $this->supplierDecisionLocked) {
            $this->supplier_decision = $this->overallStatus('supplier') === 'ng' ? 'non_conforming' : 'conforming';
        }

        if (! $this->umacDecisionLocked) {
            $this->umac_decision = $this->overallStatus('umac') === 'ng' ? 'non_conforming' : 'conforming';
        }
    }

    private function syncRejected(): void
    {
        $lot = Qty::num($this->lot_qty);

        if ($this->accepted_qty === '') {
            $this->rejected_qty = $this->lot_qty === '' ? '0' : Qty::format($lot);

            return;
        }

        $accepted = max(0, Qty::num($this->accepted_qty));

        if ($lot > 0 && $accepted > $lot) {
            $accepted = $lot;
            $this->accepted_qty = Qty::format($accepted);
        }

        $this->rejected_qty = Qty::format(max(0, $lot - $accepted));
    }

    private function clearImages(): void
    {
        foreach ($this->images as $image) {
            if (is_array($image)) {
                $this->deleteImage($image['id'] ?? null);
            }
        }

        $this->images = [];
    }

    private function deleteImage(mixed $id): void
    {
        if ($id === null || $id === '' || ! ctype_digit((string) $id)) {
            return;
        }

        $media = MediaStorage::query()->find((int) $id);
        $user = auth()->user();

        if (! $media || ! $user) {
            return;
        }

        if ((int) $media->user_id !== (int) $user->id && ! $user->isMaster()) {
            return;
        }

        FileUploadHelper::revert_media($media->id);
    }
}; ?>

<div>
    <x-core::ui.page-header
        :title="$reportId ? $reportNumber : __('New inspection report')"
        :subtitle="$reportId ? __('Update the saved readings, quantities, and decision.') : __('Select the batch. The sampling standard, sample size, and characteristic rows are filled from the part standard.')"
        :breadcrumbs="[
            admin_breadcrumb_home(),
            ['label' => __('Incoming Quality Reports'), 'route' => 'admin::quality.incoming.reports'],
            ['label' => $reportId ? __('Edit') : __('Create')],
        ]"
    />

    <form
        class="space-y-4"
        x-data="{
            async confirmSave() {
                const save = () => $wire.save();
                const title = this.$el.dataset.confirmTitle;
                const text = this.$el.dataset.confirmText;

                if (! window.AdminNotify?.confirm) {
                    if (window.confirm(`${title}\n${text}`)) {
                        await save();
                    }
                    return;
                }

                await window.AdminNotify.confirm({
                    title,
                    text,
                    icon: 'warning',
                    confirmButtonText: this.$el.dataset.confirmYes,
                    cancelButtonText: this.$el.dataset.confirmCancel,
                    confirmButtonClass: 'admin-swal-confirm',
                    onConfirm: save,
                });
            },
        }"
        data-confirm-title="{{ __('Are you sure?') }}"
        data-confirm-text="{{ $reportId ? __('Update this inspection report?') : __('Save this inspection report?') }}"
        data-confirm-yes="{{ $reportId ? __('Yes, update it') : __('Yes, save it') }}"
        data-confirm-cancel="{{ __('Cancel') }}"
        x-on:submit.prevent="confirmSave()"
    >
        <div class="admin-card grid gap-4 p-4 md:grid-cols-2">
            <div>
                <flux:label>{{ __('Material / Part Code') }}</flux:label>
                <div class="mt-2 flex gap-2">
                    <button
                        type="button"
                        wire:click="openPartPicker"
                        class="flex min-h-10 min-w-0 flex-1 items-center justify-between gap-2 rounded-lg border border-zinc-200 bg-white px-3 py-2 text-left text-sm shadow-xs transition hover:border-zinc-300 dark:border-zinc-700 dark:bg-zinc-900 dark:hover:border-zinc-600"
                    >
                        <span @class([
                            'truncate',
                            'text-zinc-500' => $part_label === '',
                            'font-medium text-zinc-900 dark:text-zinc-100' => $part_label !== '',
                        ])>
                            {{ $part_label !== '' ? $part_label : __('Select material / part') }}
                        </span>
                        <flux:icon.magnifying-glass class="size-4 shrink-0 text-zinc-400" />
                    </button>
                    @if ($part_id !== '')
                        <flux:button type="button" variant="ghost" icon="x-mark" wire:click="clearPart" :title="__('Clear part')" />
                    @endif
                </div>
                @error('part_id') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            @if ($part_id !== '')
                <div wire:key="vendor-select-{{ $part_id }}">
                    <x-core::forms.select2
                        wire:model.live="vendor_id"
                        :label="__('Supplier')"
                        :options="$partVendors->mapWithKeys(fn ($vendor) => [$vendor->id => $vendor->optionLabel()])"
                        :placeholder="$partVendors->isEmpty() ? __('No vendors linked to this part') : __('Select supplier')"
                        :allow-clear="false"
                    />
                    @error('vendor_id') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
            @else
                <div>
                    <flux:label>{{ __('Supplier') }}</flux:label>
                    <p class="mt-2 flex min-h-10 items-center rounded-lg border border-dashed border-zinc-200 px-3 py-2 text-sm text-zinc-500 dark:border-zinc-700">
                        {{ __('Select a part first.') }}
                    </p>
                </div>
            @endif

            @if ($part_id !== '' && $vendor_id !== '')
                <div wire:key="invoice-select-{{ $part_id }}-{{ $vendor_id }}">
                    <x-core::forms.select2
                        wire:model.live="invoice_id"
                        :label="__('Purchase invoice (PI)')"
                        :options="$invoices->mapWithKeys(fn ($invoice) => [
                            $invoice->id => $invoice->invoice_number.' · '.($invoice->invoice_date?->format('d M Y') ?? '—'),
                        ])"
                        :placeholder="$invoices->isEmpty() ? __('No purchase invoices for this vendor / part') : __('Select PI')"
                        :allow-clear="false"
                    />
                    @error('invoice_id') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
            @else
                <div>
                    <flux:label>{{ __('Purchase invoice (PI)') }}</flux:label>
                    <p class="mt-2 flex min-h-10 items-center rounded-lg border border-dashed border-zinc-200 px-3 py-2 text-sm text-zinc-500 dark:border-zinc-700">
                        {{ $part_id === '' ? __('Select a part first.') : __('Select a supplier first.') }}
                    </p>
                </div>
            @endif

            @if ($part_id !== '' && $vendor_id !== '' && $invoice_id !== '')
                <div wire:key="lot-select-{{ $part_id }}-{{ $vendor_id }}-{{ $invoice_id }}">
                    <x-core::forms.select2
                        wire:model.live="lot_id"
                        :label="__('Batch number')"
                        :options="$lots->mapWithKeys(fn ($lot) => [
                            $lot->id => $lot->lot_number.' · '.Qty::format($lot->qty).' '.$lot->part?->uom,
                        ])"
                        :placeholder="$lots->isEmpty() ? __('No batches on this PI') : __('Select batch')"
                        :allow-clear="false"
                    />
                    @error('lot_id') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
            @else
                <div>
                    <flux:label>{{ __('Batch number') }}</flux:label>
                    <p class="mt-2 flex min-h-10 items-center rounded-lg border border-dashed border-zinc-200 px-3 py-2 text-sm text-zinc-500 dark:border-zinc-700">
                        @if ($part_id === '')
                            {{ __('Select a part first.') }}
                        @elseif ($vendor_id === '')
                            {{ __('Select a supplier first.') }}
                        @else
                            {{ __('Select a purchase invoice first.') }}
                        @endif
                    </p>
                </div>
            @endif

            @if ($checks === [])
                <p class="md:col-span-2 text-sm text-zinc-500">
                    {{ __('Sample size is taken from ML-08 for each characteristic class. It cannot be edited.') }}
                </p>
            @endif

            @if ($sheetError)
                <p class="md:col-span-2 text-sm text-red-600">{{ $sheetError }}</p>
            @endif
        </div>

        @if ($checks !== [])
            <div wire:key="sheet-{{ $lot_id }}-{{ $sample_size }}" class="space-y-4">
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
                                <td class="iir-fixed" colspan="2">{{ $part_name }}</td>
                                <th>{{ __('BILL No.') }}</th>
                                <td colspan="3"><input id="bill-no" value="{{ $bill_no }}" disabled readonly /></td>
                            </tr>
                            <tr>
                                <th>{{ __('Material / Part Code') }}</th>
                                <td class="iir-fixed" colspan="2">{{ $part_code }}</td>
                                <th>{{ __('DATE') }}</th>
                                <td colspan="3"><input wire:model="inspected_at" type="datetime-local" /></td>
                            </tr>
                            <tr>
                                <th>{{ __('Batch') }}</th>
                                <td class="iir-fixed" colspan="2">{{ $lot_number }}</td>
                                <th>{{ __('SUPPLIER') }}</th>
                                <td class="iir-fixed" colspan="3">{{ $supplier_name }}</td>
                            </tr>
                            <tr>
                                <th>{{ __('ASSY. NAME') }}</th>
                                <td colspan="2"><textarea wire:model="assembly_name" class="iir-grow" rows="2"></textarea></td>
                                <th>{{ __('SUPPLIER Code') }}</th>
                                <td class="iir-fixed" colspan="3">{{ $supplier_code }}</td>
                            </tr>
                            <tr>
                                <th>{{ __('PROCESS') }}</th>
                                <td colspan="2"><textarea wire:model="process" class="iir-grow" rows="2"></textarea></td>
                                <th>{{ __('SAMPLE SIZE') }}</th>
                                <td colspan="3">
                                    <input id="sample-size" value="{{ $sample_size }}" disabled readonly />
                                    @if ($planLabel)
                                        <span class="iir-note">{{ $planLabel }}</span>
                                    @endif
                                </td>
                            </tr>
                            <tr>
                                <th>{{ __('LOT SIZE') }}</th>
                                <td class="iir-fixed" colspan="2">{{ $lot_qty }}</td>
                                <th>{{ __('QTY. REJECTED') }}</th>
                                <td class="iir-fixed" colspan="3">{{ $rejected_qty }}</td>
                            </tr>
                            <tr>
                                <th>{{ __('QTY. ACCEPTED') }}</th>
                                <td colspan="2"><input wire:model.live.debounce.300ms="accepted_qty" inputmode="decimal" /></td>
                                <th>{{ __('Standard') }}</th>
                                <td class="iir-fixed" colspan="3">{{ $standardLabel }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                @error('accepted_qty') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
                @error('sample_size') <p class="text-sm text-red-600">{{ $message }}</p> @enderror

                @if ($sampleQuotes !== [])
                    <div class="overflow-x-auto rounded-lg border border-zinc-300 bg-white text-zinc-900">
                        <table class="iir-sheet" id="sample-classes">
                            <thead>
                                <tr>
                                    <th>{{ __('Class') }}</th>
                                    <th>{{ __('AQL') }}</th>
                                    <th>{{ __('Sample') }}</th>
                                    <th>{{ __('Rate') }}</th>
                                    <th>{{ __('Accept') }}</th>
                                    <th>{{ __('Supplier defectives') }}</th>
                                    <th>{{ __('Supplier %') }}</th>
                                    <th>{{ __('Supplier result') }}</th>
                                    <th>{{ __('UMAC defectives') }}</th>
                                    <th>{{ __('UMAC %') }}</th>
                                    <th>{{ __('UMAC result') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach (Modules\Quality\Support\Ml08SampleQuote::CLASS_ORDER as $class)
                                    @continue(! isset($sampleQuotes[$class]))
                                    @php
                                        $quote = $sampleQuotes[$class];
                                        $supplierVerdict = $this->classVerdict($class, 'supplier');
                                        $umacVerdict = $this->classVerdict($class, 'umac');
                                    @endphp
                                    <tr>
                                        <td class="iir-center">{{ \Modules\Quality\Models\InspectionCharacteristic::CLASSES[$class] }}</td>
                                        <td class="iir-center">{{ $quote['aql'] }}</td>
                                        <td class="iir-center">{{ $quote['sample_size'] }}</td>
                                        <td class="iir-center">{{ \Modules\Quality\Support\Ml08SampleQuote::rateLabel((float) $quote['sampling_rate']) }}</td>
                                        <td class="iir-center">{{ $quote['accept_number'] }}</td>
                                        <td class="iir-center">{{ $supplierVerdict['defectives'] }}</td>
                                        <td class="iir-center">{{ \Modules\Quality\Support\Ml08SampleQuote::percentLabel($supplierVerdict['percent']) }}</td>
                                        <td class="iir-center">{{ $supplierVerdict['result'] === 'reject' ? __('Reject') : __('Accept') }}</td>
                                        <td class="iir-center">{{ $umacVerdict['defectives'] }}</td>
                                        <td class="iir-center">{{ \Modules\Quality\Support\Ml08SampleQuote::percentLabel($umacVerdict['percent']) }}</td>
                                        <td class="iir-center">{{ $umacVerdict['result'] === 'reject' ? __('Reject') : __('Accept') }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif

                <div class="admin-card p-4">
                    <x-core::forms.filepond
                        wire:model="images"
                        model="images"
                        folder="images"
                        allow-multiple
                        :label="__('Part images')"
                        :description="__('Upload one or more photos of the part. Each image can be removed from the corner of its preview.')"
                        :accepted-file-types="['image/png', 'image/jpeg', 'image/webp', 'image/gif']"
                        max-file-size="5MB"
                    />

                    @if ($images !== [])
                        <div class="mt-4 flex flex-wrap gap-3">
                            @foreach ($images as $index => $image)
                                @php $imageUrl = media_url(is_array($image) ? ($image['url'] ?? '') : $image); @endphp
                                @if ($imageUrl)
                                    <div wire:key="report-image-{{ $image['id'] ?? $index }}" class="admin-filepond-preview admin-filepond-preview-square" style="height: 7rem; width: 7rem;">
                                        <img src="{{ $imageUrl }}" alt="" class="admin-filepond-preview-img" />
                                        <button
                                            type="button"
                                            wire:click="removeImage({{ $index }})"
                                            class="admin-filepond-remove"
                                            aria-label="{{ __('Remove image') }}"
                                        >
                                            <flux:icon.x-mark variant="outline" class="size-3.5" />
                                        </button>
                                    </div>
                                @endif
                            @endforeach
                        </div>
                    @endif
                </div>

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
                            @foreach ($checks as $index => $check)
                                <tr wire:key="check-{{ $lot_id }}-{{ $index }}">
                                    <td class="iir-center">{{ $index + 1 }}</td>
                                    <td class="iir-text">{{ $check['name'] }}</td>
                                    <td>
                                        <select wire:model.live="checks.{{ $index }}.class">
                                            @foreach (InspectionCharacteristic::CLASSES as $value => $label)
                                                <option value="{{ $value }}">{{ __($label) }}</option>
                                            @endforeach
                                        </select>
                                    </td>
                                    <td class="iir-text">{{ $check['specification'] }}</td>
                                    <td class="iir-text">{{ $check['method'] }}</td>
                                    @php $rowSamples = $this->rowSampleCount($index); @endphp
                                    @for ($sample = 0; $sample < $rowSamples; $sample++)
                                        @php
                                            $supplierTone = $this->cellState($index, 'supplier', $sample);
                                            $hasLimits = is_numeric($check['min'] ?? null) && is_numeric($check['max'] ?? null);
                                        @endphp
                                        <td wire:key="supplier-{{ $lot_id }}-{{ $index }}-{{ $sample }}" class="iir-sample">
                                            <input
                                                type="{{ ! empty($check['measurable']) && $hasLimits ? 'number' : 'text' }}"
                                                @if (! empty($check['measurable']) && $hasLimits) step="any" inputmode="decimal" @endif
                                                autocomplete="off"
                                                data-iir-min="{{ $hasLimits ? $check['min'] : '' }}"
                                                data-iir-max="{{ $hasLimits ? $check['max'] : '' }}"
                                                wire:model.live.debounce.200ms="checks.{{ $index }}.supplier.{{ $sample }}"
                                                x-data
                                                x-init="
                                                    const paint = () => window.iirPaintReading($el);
                                                    paint();
                                                    $el.addEventListener('input', paint);
                                                    $el.addEventListener('change', paint);
                                                    $el.addEventListener('blur', paint);
                                                "
                                                class="iir-reading @if ($supplierTone === 'ok') iir-ok @elseif ($supplierTone === 'ng') iir-ng @endif"
                                                @if ($supplierTone === 'ok') style="background:#16a34a!important;color:#fff!important;border-color:#15803d!important;"
                                                @elseif ($supplierTone === 'ng') style="background:#dc2626!important;color:#fff!important;border-color:#b91c1c!important;"
                                                @endif
                                            />
                                        </td>
                                    @endfor
                                    @for ($sample = $rowSamples; $sample < $this->sampleCount(); $sample++)
                                        <td class="iir-sample iir-na">—</td>
                                    @endfor
                                    @php
                                        $supplierStatus = $this->sideStatus($index, 'supplier');
                                        $supplierManual = empty($check['measurable']);
                                    @endphp
                                    <td class="iir-status">
                                        @if ($supplierManual)
                                            <select
                                                wire:model.live="checks.{{ $index }}.supplier_status"
                                                class="iir-status-select @if ($supplierStatus === 'ok') iir-ok @elseif ($supplierStatus === 'ng') iir-ng @endif"
                                            >
                                                <option value="">{{ __('—') }}</option>
                                                <option value="ok">{{ __('OK') }}</option>
                                                <option value="ng">{{ __('Not OK') }}</option>
                                            </select>
                                            @error("checks.$index.supplier_status")
                                                <span class="iir-note">{{ $message }}</span>
                                            @enderror
                                        @else
                                            <input
                                                class="iir-status-field @if ($supplierStatus === 'ok') iir-ok @elseif ($supplierStatus === 'ng') iir-ng @endif"
                                                value="{{ $this->statusLabel($supplierStatus) }}"
                                                disabled
                                                readonly
                                            />
                                        @endif
                                    </td>
                                    @for ($sample = 0; $sample < $rowSamples; $sample++)
                                        @php
                                            $umacTone = $this->cellState($index, 'umac', $sample);
                                            $hasLimits = is_numeric($check['min'] ?? null) && is_numeric($check['max'] ?? null);
                                        @endphp
                                        <td wire:key="umac-{{ $lot_id }}-{{ $index }}-{{ $sample }}" class="iir-sample">
                                            <input
                                                type="{{ ! empty($check['measurable']) && $hasLimits ? 'number' : 'text' }}"
                                                @if (! empty($check['measurable']) && $hasLimits) step="any" inputmode="decimal" @endif
                                                autocomplete="off"
                                                data-iir-min="{{ $hasLimits ? $check['min'] : '' }}"
                                                data-iir-max="{{ $hasLimits ? $check['max'] : '' }}"
                                                wire:model.live.debounce.200ms="checks.{{ $index }}.umac.{{ $sample }}"
                                                x-data
                                                x-init="
                                                    const paint = () => window.iirPaintReading($el);
                                                    paint();
                                                    $el.addEventListener('input', paint);
                                                    $el.addEventListener('change', paint);
                                                    $el.addEventListener('blur', paint);
                                                "
                                                class="iir-reading @if ($umacTone === 'ok') iir-ok @elseif ($umacTone === 'ng') iir-ng @endif"
                                                @if ($umacTone === 'ok') style="background:#16a34a!important;color:#fff!important;border-color:#15803d!important;"
                                                @elseif ($umacTone === 'ng') style="background:#dc2626!important;color:#fff!important;border-color:#b91c1c!important;"
                                                @endif
                                            />
                                        </td>
                                    @endfor
                                    @for ($sample = $rowSamples; $sample < $this->sampleCount(); $sample++)
                                        <td class="iir-sample iir-na">—</td>
                                    @endfor
                                    @php
                                        $umacStatus = $this->sideStatus($index, 'umac');
                                        $umacManual = empty($check['measurable']);
                                    @endphp
                                    <td class="iir-status">
                                        @if ($umacManual)
                                            <select
                                                wire:model.live="checks.{{ $index }}.umac_status"
                                                class="iir-status-select @if ($umacStatus === 'ok') iir-ok @elseif ($umacStatus === 'ng') iir-ng @endif"
                                            >
                                                <option value="">{{ __('—') }}</option>
                                                <option value="ok">{{ __('OK') }}</option>
                                                <option value="ng">{{ __('Not OK') }}</option>
                                            </select>
                                            @error("checks.$index.umac_status")
                                                <span class="iir-note">{{ $message }}</span>
                                            @enderror
                                        @else
                                            <input
                                                class="iir-status-field @if ($umacStatus === 'ok') iir-ok @elseif ($umacStatus === 'ng') iir-ng @endif"
                                                value="{{ $this->statusLabel($umacStatus) }}"
                                                disabled
                                                readonly
                                            />
                                        @endif
                                    </td>
                                    <td><textarea wire:model="checks.{{ $index }}.remarks" rows="2"></textarea></td>
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
                                <td class="iir-status">
                                    <input
                                        class="iir-status-field @if ($supplierOverall === 'ok') iir-ok @elseif ($supplierOverall === 'ng') iir-ng @endif"
                                        value="{{ $this->statusLabel($supplierOverall) }}"
                                        disabled
                                        readonly
                                    />
                                </td>
                                <th>{{ __('UMAC STATUS') }}</th>
                                @php $umacOverall = $this->overallStatus('umac'); @endphp
                                <td class="iir-status">
                                    <input
                                        class="iir-status-field @if ($umacOverall === 'ok') iir-ok @elseif ($umacOverall === 'ng') iir-ng @endif"
                                        value="{{ $this->statusLabel($umacOverall) }}"
                                        disabled
                                        readonly
                                    />
                                </td>
                            </tr>
                            <tr>
                                <th>{{ __('DECISION') }}</th>
                                <td>
                                    <select wire:model="supplier_decision">
                                        @foreach ($decisions as $value => $label)
                                            <option value="{{ $value }}">{{ $label }}</option>
                                        @endforeach
                                    </select>
                                </td>
                                <th>{{ __('DECISION') }}</th>
                                <td>
                                    <select wire:model="umac_decision">
                                        @foreach ($decisions as $value => $label)
                                            <option value="{{ $value }}">{{ $label }}</option>
                                        @endforeach
                                    </select>
                                </td>
                            </tr>
                            <tr>
                                <th>{{ __('INSPECTED BY') }}</th>
                                <td class="iir-fixed" colspan="3">{{ $inspector_name !== '' ? $inspector_name : '—' }}</td>
                            </tr>
                            <tr>
                                <th>{{ __('VERIFIED BY') }}</th>
                                <td class="iir-fixed" colspan="3">{{ __('Filled after the report is approved.') }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="admin-card p-4">
                    <flux:textarea wire:model="remarks" :label="__('Remarks')" rows="2" />
                </div>
            </div>
        @endif

        <div class="flex justify-end gap-2">
            <flux:button variant="ghost" :href="route('admin::quality.incoming.reports')" wire:navigate>{{ __('Cancel') }}</flux:button>
            <flux:button type="submit" variant="primary" :disabled="$checks === []">{{ $reportId ? __('Update report') : __('Save report') }}</flux:button>
        </div>
    </form>

    <flux:modal wire:model="showPartPicker" class="w-full !max-w-5xl max-h-[90vh] overflow-y-auto">
        <div class="space-y-4">
            <div>
                <flux:heading size="lg">{{ __('Select material / part') }}</flux:heading>
                <flux:text class="mt-1">
                    {{ __('Choose a part with an uploaded incoming standard. Parts without a standard can open the upload page in a new tab.') }}
                </flux:text>
            </div>

            <flux:input
                wire:model.live.debounce.300ms="partSearch"
                icon="magnifying-glass"
                :placeholder="__('Search part code or description')"
            />

            @if ($pickerParts)
                <div class="overflow-x-auto rounded-xl border border-zinc-200 dark:border-zinc-700">
                    <table class="min-w-full divide-y divide-zinc-200 dark:divide-zinc-700">
                        <thead class="bg-[var(--admin-surface-muted)]">
                            <tr>
                                <x-core::ui.sortable-th column="part_number" :label="__('Material / Part Code')" :sort-by="$sortBy" :sort-direction="$sortDirection" />
                                <x-core::ui.sortable-th column="name" :label="__('Material / Part Description')" :sort-by="$sortBy" :sort-direction="$sortDirection" />
                                <th class="px-4 py-3 text-left text-xs font-semibold text-zinc-500">{{ __('Vendor Details') }}</th>
                                <x-core::ui.sortable-th column="uom" :label="__('UOM')" :sort-by="$sortBy" :sort-direction="$sortDirection" />
                                <x-core::ui.sortable-th column="approved_on" :label="__('Material/Part approved Date')" :sort-by="$sortBy" :sort-direction="$sortDirection" />
                                <th class="px-4 py-3 text-left text-xs font-semibold text-zinc-500">{{ __('Incoming standard') }}</th>
                                <th class="px-4 py-3 text-right text-xs font-semibold text-zinc-500">{{ __('Action') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                            @forelse ($pickerParts as $part)
                                <tr wire:key="picker-part-{{ $part->id }}" @class(['bg-teal-50/60 dark:bg-teal-950/20' => (string) $part->id === $part_id])>
                                    <td class="px-4 py-3 text-sm font-medium">{{ $part->part_number }}</td>
                                    <td class="px-4 py-3 text-sm">
                                        {{ $part->name }}
                                        @if ($part->category)
                                            <br><span class="text-xs text-zinc-500">{{ $part->category->name }}</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 text-sm">{{ $part->vendorsLabel() }}</td>
                                    <td class="px-4 py-3 text-sm">{{ $part->uom }}</td>
                                    <td class="px-4 py-3 text-sm">{{ $part->approved_on?->format('d M Y') ?? '—' }}</td>
                                    <td class="px-4 py-3 text-sm">
                                        <a
                                            href="{{ route('admin::quality.incoming.parts.standard', $part) }}"
                                            target="_blank"
                                            rel="noopener noreferrer"
                                            class="inline-flex rounded-lg p-1.5 text-zinc-600 hover:bg-zinc-100 dark:text-zinc-300 dark:hover:bg-zinc-800"
                                            title="{{ $part->standards_exists ? __('Open incoming standard in new tab') : __('Upload incoming standard in new tab') }}"
                                        >
                                            @if ($part->standards_exists)
                                                <flux:icon.document-text class="size-5" />
                                            @else
                                                <flux:icon.arrow-top-right-on-square class="size-5" />
                                            @endif
                                        </a>
                                    </td>
                                    <td class="px-4 py-3 text-right">
                                        @if ($part->standards_exists)
                                            <flux:button
                                                type="button"
                                                size="sm"
                                                :variant="(string) $part->id === $part_id ? 'primary' : 'ghost'"
                                                wire:click="selectPart({{ $part->id }})"
                                            >
                                                {{ (string) $part->id === $part_id ? __('Selected') : __('Select') }}
                                            </flux:button>
                                        @else
                                            <span class="text-xs text-zinc-500">{{ __('Upload standard first') }}</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="px-4 py-12 text-center text-sm text-zinc-500">{{ __('No parts found.') }}</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <x-core::ui.pagination :paginator="$pickerParts" />
            @endif

            <div class="flex justify-end border-t border-zinc-200 pt-4 dark:border-zinc-700">
                <flux:button type="button" variant="ghost" wire:click="closePartPicker">{{ __('Close') }}</flux:button>
            </div>
        </div>
    </flux:modal>

    <script>
        window.iirPaintReading = function (el) {
            if (! el) {
                return;
            }

            const minRaw = el.dataset.iirMin;
            const maxRaw = el.dataset.iirMax;
            const min = minRaw === '' || minRaw === undefined ? NaN : Number(minRaw);
            const max = maxRaw === '' || maxRaw === undefined ? NaN : Number(maxRaw);
            const text = String(el.value ?? '').trim();
            let tone = 'empty';

            if (text !== '' && Number.isFinite(min) && Number.isFinite(max)) {
                const number = Number(text);
                // Inclusive: 55 ± 0.4 => 54.6 .. 55.4
                tone = Number.isFinite(number) && number >= min && number <= max ? 'ok' : 'ng';
            }

            el.dataset.iirTone = tone;
            el.classList.toggle('iir-ok', tone === 'ok');
            el.classList.toggle('iir-ng', tone === 'ng');

            if (tone === 'ok') {
                el.style.setProperty('background', '#16a34a', 'important');
                el.style.setProperty('color', '#fff', 'important');
                el.style.setProperty('border-color', '#15803d', 'important');
                return;
            }

            if (tone === 'ng') {
                el.style.setProperty('background', '#dc2626', 'important');
                el.style.setProperty('color', '#fff', 'important');
                el.style.setProperty('border-color', '#b91c1c', 'important');
                return;
            }

            el.style.setProperty('background', '#fff', 'important');
            el.style.setProperty('color', '#111', 'important');
            el.style.setProperty('border-color', '#d4d4d8', 'important');
        };
    </script>

    <style>
        .iir-sheet { width: 100%; border-collapse: collapse; background: #fff; color: #111; }
        .iir-sheet th, .iir-sheet td { border: 1px solid #222; padding: 8px 10px; vertical-align: middle; font-size: 13px; font-weight: 700; line-height: 1.35; }
        .iir-sheet td { font-weight: 500; background: #eef0f3; }
        .iir-sheet th { background: #e4e4e7; }
        .iir-header { min-width: 860px; table-layout: fixed; }
        .iir-footer { min-width: 720px; table-layout: fixed; }
        .iir-grid { min-width: max(100%, 980px); }
        .iir-title { text-align: center; font-size: 16px; background: #f4f4f5; padding: 12px 8px; }
        .iir-meta { font-size: 12px; font-weight: 700; text-align: left; background: #f4f4f5; white-space: nowrap; }
        .iir-sheet thead th { background: #f4f4f5; text-align: center; font-size: 11px; letter-spacing: 0.02em; }
        .iir-center { text-align: center; }
        .iir-fixed { text-align: center; font-weight: 700; }
        .iir-text { min-width: 8rem; text-align: left; font-weight: 600; }
        .iir-note { display: block; margin-top: 4px; font-size: 11px; font-weight: 600; color: #3f3f46; text-align: center; }
        .iir-sample { padding: 2px !important; width: 3.4rem; }
        .iir-na { background: #f4f4f5; color: #a1a1aa; text-align: center; }
        .iir-sheet input, .iir-sheet textarea, .iir-sheet select {
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
        .iir-sheet input:not(:disabled):not([readonly]),
        .iir-sheet textarea,
        .iir-sheet select {
            background: #fff;
            border: 1px solid #d4d4d8;
            border-radius: 4px;
        }
        .iir-sheet textarea { text-align: left; resize: both; min-width: 8rem; min-height: 2.75rem; }
        .iir-sheet textarea.iir-grow { min-height: 3.25rem; }
        .iir-sheet select {
            cursor: pointer;
            appearance: none;
            background-image: none;
            font-weight: 600;
        }
        .iir-sheet input:focus, .iir-sheet textarea:focus, .iir-sheet select:focus { outline: 1px solid #0f766e; }
        .iir-sheet input:not(.iir-ok):not(.iir-ng):focus { background: #fff; }
        .iir-sheet input:disabled { color: #111; opacity: 1; font-weight: 700; cursor: not-allowed; }
        .iir-sheet input.iir-status-field {
            width: 6.75rem;
            min-width: 6.75rem;
            font-weight: 700;
            border: 1px solid #d4d4d8;
            border-radius: 4px;
            background: #fff;
            opacity: 1;
        }
        .iir-sheet input.iir-reading { width: 4.25rem; min-width: 4.25rem; font-weight: 700; }
        .iir-sheet input[type="number"] { -moz-appearance: textfield; appearance: textfield; }
        .iir-sheet input[type="number"]::-webkit-outer-spin-button,
        .iir-sheet input[type="number"]::-webkit-inner-spin-button { -webkit-appearance: none; margin: 0; }
        .iir-sheet input.iir-ok,
        .iir-sheet input.iir-ok:focus,
        .iir-sheet input.iir-status-field.iir-ok {
            background: #16a34a !important;
            color: #fff !important;
            border-color: #15803d !important;
        }
        .iir-sheet input.iir-ng,
        .iir-sheet input.iir-ng:focus,
        .iir-sheet input.iir-status-field.iir-ng {
            background: #dc2626 !important;
            color: #fff !important;
            border-color: #b91c1c !important;
        }
        .iir-sheet td.iir-status { text-align: center; font-weight: 700; white-space: nowrap; }
        .iir-sheet select.iir-status-select {
            min-width: 6.75rem;
            font-weight: 700;
            appearance: auto;
            cursor: pointer;
        }
        .iir-sheet select.iir-status-select.iir-ok,
        .iir-sheet select.iir-status-select.iir-ok:focus {
            background: #16a34a !important;
            color: #fff !important;
            border-color: #15803d !important;
        }
        .iir-sheet select.iir-status-select.iir-ng,
        .iir-sheet select.iir-status-select.iir-ng:focus {
            background: #dc2626 !important;
            color: #fff !important;
            border-color: #b91c1c !important;
        }
        .iir-sheet select.iir-status-select option {
            background: #fff;
            color: #111;
        }
    </style>
</div>
