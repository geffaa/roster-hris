<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $approval_request_id
 * @property int $step
 * @property ?int $approver_id
 * @property ?int $delegated_from_id
 * @property string $status
 * @property ?int $decided_by
 * @property ?Carbon $decided_at
 * @property ?string $comment
 * @property-read ApprovalRequest $request
 * @property-read ?Employee $approver
 * @property-read ?Employee $delegatedFrom
 * @property-read ?User $decider
 */
class ApprovalStep extends Model
{
    protected $fillable = ['approval_request_id', 'step', 'approver_id', 'delegated_from_id', 'status', 'decided_by', 'decided_at', 'comment'];

    protected function casts(): array
    {
        return ['decided_at' => 'datetime', 'step' => 'integer'];
    }

    /** @return BelongsTo<ApprovalRequest, $this> */
    public function request(): BelongsTo
    {
        return $this->belongsTo(ApprovalRequest::class, 'approval_request_id');
    }

    /** @return BelongsTo<Employee, $this> */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'approver_id');
    }

    /** @return BelongsTo<Employee, $this> */
    public function delegatedFrom(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'delegated_from_id');
    }

    /** @return BelongsTo<User, $this> */
    public function decider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }
}
