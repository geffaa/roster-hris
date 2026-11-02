<?php

namespace App\Models;

use App\Enums\IdentifierType;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Crypt;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

/**
 * @property int $id
 * @property int $employee_id
 * @property IdentifierType $type
 * @property string $value
 * @property string $value_last4
 */
class EmployeeIdentifier extends Model implements AuditableContract
{
    use Auditable;

    protected $fillable = ['employee_id', 'type', 'value'];

    protected $hidden = ['value'];

    /** Only the last four digits ever reach the audit log. */
    protected array $auditExclude = ['value'];

    protected function casts(): array
    {
        return ['type' => IdentifierType::class, 'value' => 'encrypted'];
    }

    protected function value(): Attribute
    {
        return Attribute::make(
            set: function (string $value) {
                $clean = preg_replace('/\s+/', '', $value);
                $digits = preg_replace('/\D+/', '', $clean) ?: $clean;

                return ['value' => Crypt::encryptString($clean), 'value_last4' => mb_substr($digits, -4)];
            },
        );
    }

    public function masked(): string
    {
        return '•••• '.$this->value_last4;
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
}
