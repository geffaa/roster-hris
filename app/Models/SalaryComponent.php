<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $company_id
 * @property string $code
 * @property string $name
 * @property string $kind
 * @property bool $taxable
 * @property bool $fixed
 * @property bool $recurring
 */
class SalaryComponent extends Model
{
    use BelongsToCompany;

    protected $fillable = ['company_id', 'code', 'name', 'kind', 'taxable', 'fixed', 'recurring'];

    protected $attributes = ['kind' => 'earning', 'taxable' => true, 'fixed' => true, 'recurring' => true];

    protected function casts(): array
    {
        return ['taxable' => 'boolean', 'fixed' => 'boolean', 'recurring' => 'boolean'];
    }
}
