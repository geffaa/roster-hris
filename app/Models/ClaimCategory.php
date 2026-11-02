<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $company_id
 * @property string $code
 * @property string $name
 * @property ?string $monthly_limit
 * @property bool $receipt_required
 */
class ClaimCategory extends Model
{
    use BelongsToCompany;

    protected $fillable = ['company_id', 'code', 'name', 'monthly_limit', 'receipt_required'];

    protected function casts(): array
    {
        return ['receipt_required' => 'boolean'];
    }
}
