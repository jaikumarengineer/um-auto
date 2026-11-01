<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Modules\Quality\Models\RecordActivity;

class RecordApproval
{
    public const PENDING = 'pending';

    public const UNDER_REVIEW = 'under_review';

    public const APPROVED = 'approved';

    public const SENT_BACK = 'sent_back';

    public const REJECTED = 'rejected';

    /**
     * @return list<string>
     */
    public static function statuses(): array
    {
        return [
            self::PENDING,
            self::UNDER_REVIEW,
            self::APPROVED,
            self::SENT_BACK,
            self::REJECTED,
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function statusLabels(): array
    {
        return [
            self::PENDING => __('Pending'),
            self::UNDER_REVIEW => __('Under Review'),
            self::APPROVED => __('Approved'),
            self::SENT_BACK => __('Sent Back'),
            self::REJECTED => __('Rejected'),
        ];
    }

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

    public function recordCreated(Model $record, ?User $actor = null): void
    {
        if (! $record->exists) {
            return;
        }

        $actor ??= auth()->user();

        $this->writeActivity(
            $record,
            RecordActivity::EVENT_CREATED,
            $actor,
            fromStatus: null,
            toStatus: null,
            reason: null,
            meta: null,
            at: $record->created_at,
        );

        if (($record->approval_status ?? null) === self::APPROVED) {
            $this->writeActivity(
                $record,
                RecordActivity::EVENT_APPROVAL_STATUS_CHANGED,
                $actor ?? ($record->approved_by ? User::query()->find($record->approved_by) : null),
                fromStatus: null,
                toStatus: self::APPROVED,
                reason: null,
                meta: ['auto' => true],
                at: $record->approved_at ?? $record->created_at,
            );
        }
    }

    public function updateStatus(Model $record, string $status, ?User $actor = null, ?string $reason = null): Model
    {
        $actor = $this->assertCanApprove($actor);

        if (! in_array($status, self::statuses(), true)) {
            throw ValidationException::withMessages([
                'approval_status' => __('Invalid accepted status.'),
            ]);
        }

        $fromStatus = $record->approval_status ?: null;

        $payload = [
            'approval_status' => $status,
            'approved_by' => $actor->id,
            'approved_at' => Carbon::now(),
            'rejection_reason' => null,
        ];

        if (in_array($status, [self::REJECTED, self::SENT_BACK], true)) {
            $payload['rejection_reason'] = $reason ?: ($status === self::REJECTED ? __('Rejected') : __('Sent back'));
        } elseif (filled($reason)) {
            $payload['rejection_reason'] = $reason;
        }

        $record->forceFill($payload)->save();

        $this->writeActivity(
            $record,
            RecordActivity::EVENT_APPROVAL_STATUS_CHANGED,
            $actor,
            fromStatus: $fromStatus,
            toStatus: $status,
            reason: $payload['rejection_reason'],
        );

        return $record->fresh();
    }

    public function approve(Model $record, ?User $actor = null, ?string $reason = null): Model
    {
        return $this->updateStatus($record, self::APPROVED, $actor, $reason);
    }

    public function reject(Model $record, ?User $actor = null, ?string $reason = null): Model
    {
        return $this->updateStatus($record, self::REJECTED, $actor, $reason);
    }

    public function isPending(Model $record): bool
    {
        return ($record->approval_status ?? self::APPROVED) === self::PENDING;
    }

    public function isAwaitingDecision(Model $record): bool
    {
        return in_array(
            $record->approval_status ?? self::APPROVED,
            [self::PENDING, self::UNDER_REVIEW, self::SENT_BACK],
            true,
        );
    }

    public function isApproved(Model $record): bool
    {
        return ($record->approval_status ?? self::APPROVED) === self::APPROVED;
    }

    /**
     * @return array<string, mixed>
     */
    public static function statusValidationRules(string $attribute = 'approval_status'): array
    {
        return [$attribute => ['required', 'string', Rule::in(self::statuses())]];
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

    protected function writeActivity(
        Model $record,
        string $event,
        ?User $actor,
        ?string $fromStatus = null,
        ?string $toStatus = null,
        ?string $reason = null,
        ?array $meta = null,
        mixed $at = null,
    ): void {
        if (! $record->exists) {
            return;
        }

        RecordActivity::query()->create([
            'subject_type' => $record->getMorphClass(),
            'subject_id' => $record->getKey(),
            'event' => $event,
            'actor_id' => $actor?->id,
            'from_status' => $fromStatus,
            'to_status' => $toStatus,
            'reason' => $reason,
            'meta' => $meta,
            'created_at' => $at ?? now(),
        ]);
    }
}
