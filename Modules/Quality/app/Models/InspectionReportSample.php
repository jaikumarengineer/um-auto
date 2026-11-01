<?php

namespace Modules\Quality\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Quality\Support\Ml08SampleQuote;
use Modules\Quality\Support\Ml08SamplingPlan;

class InspectionReportSample extends Model
{
    protected $fillable = [
        'inspection_report_id',
        'characteristic_class',
        'aql',
        'lot_min',
        'lot_max',
        'sample_size',
        'sampling_rate',
        'accept_number',
        'reject_number',
        'supplier_defectives',
        'umac_defectives',
        'supplier_nonconforming_pct',
        'umac_nonconforming_pct',
        'supplier_result',
        'umac_result',
        'sampling_plan_id',
    ];

    protected function casts(): array
    {
        return [
            'aql' => 'decimal:3',
            'sampling_rate' => 'decimal:6',
            'supplier_nonconforming_pct' => 'decimal:2',
            'umac_nonconforming_pct' => 'decimal:2',
        ];
    }

    public function report(): BelongsTo
    {
        return $this->belongsTo(InspectionReport::class, 'inspection_report_id');
    }

    public function samplingPlan(): BelongsTo
    {
        return $this->belongsTo(SamplingPlan::class);
    }

    public function classLabel(): string
    {
        return InspectionCharacteristic::CLASSES[$this->characteristic_class] ?? $this->characteristic_class;
    }

    public function aqlLabel(): string
    {
        return Ml08SamplingPlan::formatAql($this->aql);
    }

    public function rateLabel(): string
    {
        return Ml08SampleQuote::rateLabel((float) $this->sampling_rate);
    }

    public function percentLabel(string $side): string
    {
        $percent = $side === 'umac' ? $this->umac_nonconforming_pct : $this->supplier_nonconforming_pct;

        return Ml08SampleQuote::percentLabel((float) $percent);
    }

    public function verdictLabel(string $side): string
    {
        $result = $side === 'umac' ? $this->umac_result : $this->supplier_result;

        return $result === 'reject' ? __('Reject') : __('Accept');
    }
}
