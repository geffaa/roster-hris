<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

/**
 * @property int $id
 * @property int $company_id
 * @property string $reference
 * @property Carbon $period_start
 * @property Carbon $period_end
 * @property Carbon $pay_date
 * @property bool $include_thr
 * @property string $status
 * @property ?int $rule_pack_version_id
 * @property int $progress
 * @property int $headcount
 * @property ?array<string, mixed> $totals
 * @property ?int $created_by
 * @property ?Carbon $calculated_at
 * @property ?int $calculated_by
 * @property ?int $approved_by_1
 * @property ?Carbon $approved_at_1
 * @property ?int $approved_by_2
 * @property ?Carbon $approved_at_2
 * @property ?int $locked_by
 * @property ?Carbon $locked_at
 * @property ?Carbon $published_at
 * @property-read Company $company
 * @property-read ?RulePackVersion $rulePackVersion
 * @property-read Collection<int, PayrollItem> $items
 */
class PayrollRun extends Model implements AuditableContract
{
    use Auditable, BelongsToCompany;

    protected $fillable = [
        'company_id', 'reference', 'period_start', 'period_end', 'pay_date', 'include_thr', 'status', 'rule_pack_version_id',
        'progress', 'headcount', 'totals', 'created_by', 'calculated_at', 'calculated_by', 'approved_by_1', 'approved_at_1',
        'approved_by_2', 'approved_at_2', 'locked_by', 'locked_at', 'published_at',
    ];

    protected $attributes = ['status' => 'draft', 'include_thr' => false, 'progress' => 0, 'headcount' => 0];

    protected array $auditInclude = ['status', 'approved_by_1', 'approved_by_2', 'locked_by', 'published_at', 'rule_pack_version_id', 'include_thr'];

    protected function casts(): array
    {
        return [
            'period_start' => 'date', 'period_end' => 'date', 'pay_date' => 'date', 'include_thr' => 'boolean', 'totals' => 'array',
            'calculated_at' => 'datetime', 'approved_at_1' => 'datetime', 'approved_at_2' => 'datetime', 'locked_at' => 'datetime', 'published_at' => 'datetime',
        ];
    }

    public function transformAudit(array $data): array
    {
        $data['company_id'] = $this->company_id;

        return $data;
    }

    public function isFinal(): bool
    {
        return in_array($this->status, ['locked', 'published'], true);
    }

    public function approvals(): int
    {
        return ($this->approved_by_1 ? 1 : 0) + ($this->approved_by_2 ? 1 : 0);
    }

    /** @return BelongsTo<RulePackVersion, $this> */
    public function rulePackVersion(): BelongsTo
    {
        return $this->belongsTo(RulePackVersion::class);
    }

    /** @return HasMany<PayrollItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(PayrollItem::class);
    }
}
