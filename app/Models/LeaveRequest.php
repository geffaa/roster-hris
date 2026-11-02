<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

/**
 * @property int $id
 * @property int $company_id
 * @property int $employee_id
 * @property int $leave_type_id
 * @property Carbon $start_date
 * @property Carbon $end_date
 * @property string $half_day
 * @property string $days
 * @property ?string $reason
 * @property string $status
 * @property ?Carbon $original_end_date
 * @property Carbon $created_at
 * @property-read Employee $employee
 * @property-read LeaveType $type
 */
class LeaveRequest extends Model implements AuditableContract
{
    use Auditable, BelongsToCompany;

    protected $fillable = ['company_id', 'employee_id', 'leave_type_id', 'start_date', 'end_date', 'half_day', 'days', 'reason', 'status', 'original_end_date'];

    protected $attributes = ['half_day' => 'none', 'status' => 'pending'];

    protected function casts(): array
    {
        return ['start_date' => 'date', 'end_date' => 'date', 'original_end_date' => 'date', 'days' => 'decimal:1'];
    }

    public function transformAudit(array $data): array
    {
        $data['company_id'] = $this->company_id;

        return $data;
    }

    /** @return BelongsTo<Employee, $this> */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /** @return BelongsTo<LeaveType, $this> */
    public function type(): BelongsTo
    {
        return $this->belongsTo(LeaveType::class, 'leave_type_id');
    }
}
