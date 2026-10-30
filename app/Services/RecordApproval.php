<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

class RecordApproval
{
    public const PENDING = 'pending';

    public const APPROVED = 'approved';

    public const REJECTED = 'rejected';

    public function stampOnCreate(Model $record, ?User $actor = null): void
    {
        $actor ??= auth()->user();

        $record->created_by = $actor?->id;
        $record->rejection_reason = null;

        // Department users submit for approval. Admin/manager (and unauthenticated
        // system/seed paths) are auto-approved.
        if (! $actor || $actor->isUnrestrictedAdmin() || $actor->isManagerLike()) {
            $record->approval_status = self::APPROVED;
            $record->approved_by = $actor?->id;
            $record->approved_at = $actor ? now() : null;

            return;
        }

        $record->approval_status = self::PENDING;
        $record->approved_by = null;
        $record->approved_at = null;
    }

    public function approve(Model $record, ?User $actor = null, ?string $reason = null): Model
    {
        $actor = $this->assertCanApprove($actor);
        $this->assertPending($record);

        $record->forceFill([
            'approval_status' => self::APPROVED,
            'approved_by' => $actor->id,
            'approved_at' => Carbon::now(),
            'rejection_reason' => $reason,
        ])->save();

        return $record->fresh();
    }

    public function reject(Model $record, ?User $actor = null, ?string $reason = null): Model
    {
        $actor = $this->assertCanApprove($actor);
        $this->assertPending($record);

        $record->forceFill([
            'approval_status' => self::REJECTED,
            'approved_by' => $actor->id,
            'approved_at' => Carbon::now(),
            'rejection_reason' => $reason ?: __('Rejected'),
        ])->save();

        return $record->fresh();
    }

    public function isPending(Model $record): bool
    {
        return ($record->approval_status ?? self::APPROVED) === self::PENDING;
    }

    public function isApproved(Model $record): bool
    {
        return ($record->approval_status ?? self::APPROVED) === self::APPROVED;
    }

    protected function assertCanApprove(?User $actor): User
    {
        $actor ??= auth()->user();

        if (! $actor || ! $actor->canModule('quality.approvals', 'edit')) {
            throw ValidationException::withMessages([
                'approval' => __('Only an admin or manager can approve records.'),
            ]);
        }

        return $actor;
    }

    protected function assertPending(Model $record): void
    {
        if (! $this->isPending($record)) {
            throw ValidationException::withMessages([
                'approval' => __('This record is not waiting for approval.'),
            ]);
        }
    }
}
