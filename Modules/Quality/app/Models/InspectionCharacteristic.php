<?php

namespace Modules\Quality\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InspectionCharacteristic extends Model
{
    public const CLASSES = [
        'C' => 'Critical',
        'M' => 'Major',
        'Mi' => 'Minor',
        'O' => 'Others',
    ];

    protected $fillable = [
        'inspection_standard_id',
        'sequence',
        'name',
        'specification',
        'method',
        'instrument',
        'sample_plan',
        'inspection_stage',
        'characteristic_class',
        'remarks',
        'tolerance_min',
        'tolerance_max',
        'unit',
        'is_critical',
    ];

    protected function casts(): array
    {
        return [
            'tolerance_min' => 'decimal:4',
            'tolerance_max' => 'decimal:4',
            'is_critical' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (self $characteristic): void {
            $characteristic->characteristic_class = self::normalize($characteristic->characteristic_class);
            $characteristic->is_critical = $characteristic->characteristic_class === 'C';
        });
    }

    public static function normalize(?string $value): string
    {
        $value = trim((string) $value);

        if ($value === '' || $value === 'S') {
            return $value === 'S' ? 'O' : 'C';
        }

        return array_key_exists($value, self::CLASSES) ? $value : 'C';
    }

    public function classLabel(): string
    {
        $class = self::normalize($this->characteristic_class);

        return self::CLASSES[$class] ?? $class;
    }

    public function standard(): BelongsTo
    {
        return $this->belongsTo(InspectionStandard::class, 'inspection_standard_id');
    }

    public function judge(mixed $measured): ?string
    {
        if ($this->tolerance_min === null || $this->tolerance_max === null || ! is_numeric($measured)) {
            return null;
        }

        $value = (float) $measured;

        return $value >= (float) $this->tolerance_min && $value <= (float) $this->tolerance_max
            ? 'ok'
            : 'ng';
    }
}
