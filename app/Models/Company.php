<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $name
 * @property string $country_code
 * @property string $base_currency
 * @property string $timezone
 * @property ?string $legal_name
 * @property string $proration_policy
 * @property ?int $rule_pack_id
 * @property int $pay_day
 * @property-read ?RulePack $rulePack
 */
class Company extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'legal_name', 'country_code', 'base_currency', 'timezone', 'locale', 'proration_policy', 'rule_pack_id', 'pay_day'];

    /** @return BelongsToMany<User, $this> */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withPivot('is_default')->withTimestamps();
    }

    /** @return HasMany<Employee, $this> */
    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class);
    }

    /** @return BelongsTo<RulePack, $this> */
    public function rulePack(): BelongsTo
    {
        return $this->belongsTo(RulePack::class);
    }
}
