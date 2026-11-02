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
 * @property Carbon $work_date
 * @property string $start_time
 * @property string $end_time
 * @property int $minutes
 * @property string $reason
 * @property string $status
 * @property-read Employee $employee
 */
class OvertimeRequest extends Model implements AuditableContract
{
    use Auditable, BelongsToCompany;

    protected $fillable = ['company_id', 'employee_id', 'work_date', 'start_time', 'end_time', 'minutes', 'reason', 'status'];

    protected $attributes = ['status' => 'pending'];

    protected function casts(): array
    {
        return ['work_date' => 'date', 'minutes' => 'integer'];
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
}
