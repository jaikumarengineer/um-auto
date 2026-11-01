<?php

namespace Modules\Quality\Models;

use App\Models\User;
use App\Services\RecordApproval;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class RecordActivity extends Model
{
    public const UPDATED_AT = null;

    public const EVENT_CREATED = 'created';

    public const EVENT_APPROVAL_STATUS_CHANGED = 'approval_status_changed';

    protected $table = 'quality_record_activities';

    protected $fillable = [
        'subject_type',
        'subject_id',
        'event',
        'actor_id',
        'from_status',
        'to_status',
        'reason',
        'meta',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'meta' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    public function summary(): string
    {
        $labels = RecordApproval::statusLabels();

        return match ($this->event) {
            self::EVENT_CREATED => __('created this record'),
            self::EVENT_APPROVAL_STATUS_CHANGED => __('changed accepted status from :from to :to', [
                'from' => $labels[$this->from_status] ?? ($this->from_status ? str($this->from_status)->replace('_', ' ')->title() : __('None')),
                'to' => $labels[$this->to_status] ?? ($this->to_status ? str($this->to_status)->replace('_', ' ')->title() : __('None')),
            ]),
            default => __('recorded an update'),
        };
    }
}
