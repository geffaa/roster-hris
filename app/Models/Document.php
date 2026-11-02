<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

/**
 * @property int $id
 * @property string $type
 * @property string $name
 * @property string $path
 * @property ?Carbon $expires_at
 * @property Carbon $created_at
 */
class Document extends Model implements AuditableContract
{
    use Auditable;

    protected $fillable = ['employee_id', 'type', 'name', 'path', 'expires_at'];

    protected function casts(): array
    {
        return ['expires_at' => 'date'];
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
