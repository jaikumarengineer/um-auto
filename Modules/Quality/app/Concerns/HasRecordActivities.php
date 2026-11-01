<?php

namespace Modules\Quality\Concerns;

use Illuminate\Database\Eloquent\Relations\MorphMany;
use Modules\Quality\Models\RecordActivity;

trait HasRecordActivities
{
    public function activities(): MorphMany
    {
        return $this->morphMany(RecordActivity::class, 'subject')->latest('id');
    }
}
