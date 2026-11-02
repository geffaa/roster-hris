<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

/**
 * @property int $id
 * @property int $company_id
 * @property int $employee_id
 * @property string $bank_name
 * @property string $account_number
 * @property string $account_last4
 * @property string $holder_name
 * @property string $status
 * @property-read Employee $employee
 */
class BankAccountChange extends Model implements AuditableContract
{
    use Auditable, BelongsToCompany;

    protected $fillable = ['company_id', 'employee_id', 'bank_name', 'account_number', 'holder_name', 'status'];

    protected $attributes = ['status' => 'pending'];

    /** The full number is encrypted and never enters the audit log. */
    protected array $auditExclude = ['account_number'];

    protected function casts(): array
    {
        return ['account_number' => 'encrypted'];
    }

    protected static function booted(): void
    {
        static::saving(function (BankAccountChange $c) {
            if ($c->isDirty('account_number')) {
                $c->account_last4 = substr(preg_replace('/\D/', '', $c->account_number) ?: '0000', -4);
            }
        });
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
