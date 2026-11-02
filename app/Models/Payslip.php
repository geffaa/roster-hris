<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $company_id
 * @property int $payroll_item_id
 * @property int $employee_id
 * @property Carbon $published_at
 * @property ?Carbon $first_viewed_at
 * @property-read PayrollItem $item
 */
class Payslip extends Model
{
    use BelongsToCompany;

    protected $fillable = ['company_id', 'payroll_item_id', 'employee_id', 'published_at', 'first_viewed_at'];

    protected function casts(): array
    {
        return ['published_at' => 'datetime', 'first_viewed_at' => 'datetime'];
    }

    /** @return BelongsTo<PayrollItem, $this> */
    public function item(): BelongsTo
    {
        return $this->belongsTo(PayrollItem::class, 'payroll_item_id');
    }
}
