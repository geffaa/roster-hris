<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $company_id
 * @property string $name
 * @property string $start_time
 * @property string $end_time
 * @property bool $crosses_midnight
 * @property int $grace_min
 * @property list<int> $working_days
 * @property bool $is_default
 */
class WorkPattern extends Model
{
    use BelongsToCompany;

    protected $fillable = ['company_id', 'name', 'start_time', 'end_time', 'crosses_midnight', 'grace_min', 'working_days', 'is_default'];

    protected $attributes = ['crosses_midnight' => false, 'grace_min' => 10, 'is_default' => false];

    protected function casts(): array
    {
        return ['crosses_midnight' => 'boolean', 'working_days' => 'array', 'is_default' => 'boolean', 'grace_min' => 'integer'];
    }

    public function label(): string
    {
        $days = $this->working_days === [1, 2, 3, 4, 5] ? 'Mon–Fri' : implode(', ', array_map(fn ($d) => ['', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'][$d], $this->working_days));

        return sprintf('%s · %s–%s', $days, substr($this->start_time, 0, 5), substr($this->end_time, 0, 5));
    }
}
