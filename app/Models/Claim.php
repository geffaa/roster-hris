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
 * @property int $claim_category_id
 * @property Carbon $spent_on
 * @property string $amount
 * @property string $description
 * @property ?string $receipt_path
 * @property string $status
 * @property ?int $payroll_run_id
 * @property-read ClaimCategory $category
 * @property-read Employee $employee
 */
class Claim extends Model implements AuditableContract
{
    use Auditable, BelongsToCompany;

    protected $fillable = ['company_id', 'employee_id', 'claim_category_id', 'spent_on', 'amount', 'description', 'receipt_path', 'status', 'payroll_run_id'];

    protected $attributes = ['status' => 'pending'];

    protected function casts(): array
    {
        return ['spent_on' => 'date', 'amount' => 'decimal:2'];
    }

    public function transformAudit(array $data): array
    {
        $data['company_id'] = $this->company_id;

        return $data;
    }

    /** @return BelongsTo<ClaimCategory, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(ClaimCategory::class, 'claim_category_id');
    }

    /** @return BelongsTo<Employee, $this> */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
