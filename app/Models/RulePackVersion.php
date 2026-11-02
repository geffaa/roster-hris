<?php

namespace App\Models;

use App\Payroll\RulePackSnapshot;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use LogicException;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

/**
 * @property int $id
 * @property int $rule_pack_id
 * @property string $version
 * @property Carbon $effective_from
 * @property string $status
 * @property array<string, mixed> $config
 * @property array<string, array{note: string, verified: string, verify?: bool}> $sources
 * @property ?string $change_note
 * @property ?int $based_on_id
 * @property ?int $created_by
 * @property ?Carbon $submitted_at
 * @property ?int $approved_by
 * @property ?Carbon $approved_at
 * @property ?string $rejected_reason
 * @property-read RulePack $pack
 * @property-read ?RulePackVersion $basedOn
 * @property-read ?User $author
 * @property-read ?User $approver
 */
class RulePackVersion extends Model implements AuditableContract
{
    use Auditable;

    protected $fillable = ['rule_pack_id', 'version', 'effective_from', 'status', 'config', 'sources', 'change_note', 'based_on_id', 'created_by', 'submitted_at', 'approved_by', 'approved_at', 'rejected_reason'];

    protected $attributes = ['status' => 'draft'];

    protected function casts(): array
    {
        return ['effective_from' => 'date', 'config' => 'array', 'sources' => 'array', 'submitted_at' => 'datetime', 'approved_at' => 'datetime'];
    }

    /** Versions are part of the payroll record: they can be discarded or superseded, never deleted. */
    protected static function booted(): void
    {
        static::deleting(fn () => throw new LogicException('Rule pack versions cannot be deleted.'));
    }

    public function snapshot(): RulePackSnapshot
    {
        return new RulePackSnapshot($this->version, $this->config);
    }

    public function isEditable(): bool
    {
        return in_array($this->status, ['draft', 'rejected'], true);
    }

    /** @return BelongsTo<RulePack, $this> */
    public function pack(): BelongsTo
    {
        return $this->belongsTo(RulePack::class, 'rule_pack_id');
    }

    /** @return BelongsTo<RulePackVersion, $this> */
    public function basedOn(): BelongsTo
    {
        return $this->belongsTo(RulePackVersion::class, 'based_on_id');
    }

    /** @return BelongsTo<User, $this> */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return BelongsTo<User, $this> */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
