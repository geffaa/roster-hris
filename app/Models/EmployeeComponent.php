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
 * @property int $salary_component_id
 * @property string $amount
 * @property Carbon $effective_from
 * @property ?Carbon $effective_to
 * @property ?string $note
 * @property-read SalaryComponent $component
 * @property-read Employee $employee
 */
class EmployeeComponent extends Model implements AuditableContract
{
    use Auditable, BelongsToCompany;

    protected $fillable = ['company_id', 'employee_id', 'salary_component_id', 'amount', 'effective_from', 'effective_to', 'note', 'created_by'];

    protected function casts(): array
    {
        return ['effective_from' => 'date', 'effective_to' => 'date', 'amount' => 'decimal:2'];
    }

    public function transformAudit(array $data): array
    {
        $data['company_id'] = $this->company_id;

        return $data;
    }

    /** @return BelongsTo<SalaryComponent, $this> */
    public function component(): BelongsTo
    {
        return $this->belongsTo(SalaryComponent::class, 'salary_component_id');
    }

    /** @return BelongsTo<Employee, $this> */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
