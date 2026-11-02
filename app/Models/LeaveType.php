<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $company_id
 * @property string $code
 * @property string $name
 * @property bool $paid
 * @property ?string $annual_days
 * @property string $accrual
 * @property bool $event_based
 * @property int $min_service_months
 * @property string $max_carry_over
 * @property bool $allow_negative
 * @property bool $half_day_allowed
 * @property bool $requires_second_approval
 * @property string $color
 */
class LeaveType extends Model
{
    use BelongsToCompany;

    protected $fillable = ['company_id', 'rule_pack_id', 'code', 'name', 'paid', 'annual_days', 'accrual', 'event_based', 'min_service_months', 'max_carry_over', 'allow_negative', 'half_day_allowed', 'requires_second_approval', 'color'];

    /** Mirror the column defaults so new instances behave like rows read back from the database. */
    protected $attributes = [
        'paid' => true,
        'accrual' => 'upfront',
        'event_based' => false,
        'min_service_months' => 0,
        'max_carry_over' => 0,
        'allow_negative' => false,
        'half_day_allowed' => true,
        'requires_second_approval' => false,
        'color' => '#7FA7D9',
    ];

    protected function casts(): array
    {
        return [
            'paid' => 'boolean', 'event_based' => 'boolean', 'allow_negative' => 'boolean', 'half_day_allowed' => 'boolean', 'requires_second_approval' => 'boolean',
            'annual_days' => 'decimal:1', 'max_carry_over' => 'decimal:1', 'min_service_months' => 'integer',
        ];
    }

    public function isLimited(): bool
    {
        return $this->annual_days !== null;
    }
}
