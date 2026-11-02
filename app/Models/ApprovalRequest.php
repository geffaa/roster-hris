<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * One request moving through ordered approval steps (leave, attendance correction, overtime).
 *
 * @property int $id
 * @property int $company_id
 * @property string $type
 * @property string $subject_type
 * @property int $subject_id
 * @property int $requester_id
 * @property string $status
 * @property int $current_step
 * @property ?Carbon $decided_at
 * @property Carbon $created_at
 * @property-read Employee $requester
 * @property-read Collection<int, ApprovalStep> $steps
 * @property-read LeaveRequest|AttendanceCorrection|OvertimeRequest|BankAccountChange|Claim $subject
 */
class ApprovalRequest extends Model
{
    use BelongsToCompany;

    protected $fillable = ['company_id', 'type', 'subject_type', 'subject_id', 'requester_id', 'status', 'current_step', 'decided_at'];

    protected $attributes = ['status' => 'pending', 'current_step' => 1];

    protected function casts(): array
    {
        return ['decided_at' => 'datetime', 'current_step' => 'integer'];
    }

    /** @return MorphTo<Model, $this> */
    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    /** @return BelongsTo<Employee, $this> */
    public function requester(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'requester_id');
    }

    /** @return HasMany<ApprovalStep, $this> */
    public function steps(): HasMany
    {
        return $this->hasMany(ApprovalStep::class)->orderBy('step');
    }
}
