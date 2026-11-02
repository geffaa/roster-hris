<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

/**
 * One effective-dated salary row. Rows are append-only: a raise is a new row, never an edit.
 *
 * @property int $id
 * @property int $employee_id
 * @property Carbon $effective_from
 * @property string $base_salary
 * @property string $currency
 * @property ?string $tax_status
 * @property string $reason
 * @property-read ?User $author
 */
class Compensation extends Model implements AuditableContract
{
    use Auditable;

    protected $table = 'compensation_history';

    protected $fillable = ['employee_id', 'effective_from', 'base_salary', 'currency', 'pay_frequency', 'tax_status', 'reason', 'created_by'];

    protected function casts(): array
    {
        return ['effective_from' => 'date', 'base_salary' => 'decimal:2'];
    }

    public function transformAudit(array $data): array
    {
        $data['company_id'] = $this->employee()->withoutGlobalScopes()->value('company_id');

        return $data;
    }

    /** @return BelongsTo<Employee, $this> */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /** @return BelongsTo<User, $this> */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
