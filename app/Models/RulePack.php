<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property ?string $country_code
 * @property string $code
 * @property string $name
 * @property string $method
 * @property-read Collection<int, RulePackVersion> $versions
 */
class RulePack extends Model
{
    protected $fillable = ['country_code', 'code', 'name', 'method'];

    /** @return HasMany<RulePackVersion, $this> */
    public function versions(): HasMany
    {
        return $this->hasMany(RulePackVersion::class)->orderBy('effective_from')->orderBy('id');
    }
}
