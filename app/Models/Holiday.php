<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $country_code
 * @property ?string $region
 * @property Carbon $date
 * @property string $name
 * @property bool $collective
 */
class Holiday extends Model
{
    protected $fillable = ['country_code', 'region', 'date', 'name', 'collective'];

    protected function casts(): array
    {
        return ['date' => 'date', 'collective' => 'boolean'];
    }
}
