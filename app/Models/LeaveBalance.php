<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Stores only what cannot be derived: carried-over days and manual adjustments. Entitlement and usage are computed.
 *
 * @property int $id
 * @property int $employee_id
 * @property int $leave_type_id
 * @property int $year
 * @property string $carried_over
 * @property string $adjustment
 */
class LeaveBalance extends Model
{
    protected $fillable = ['employee_id', 'leave_type_id', 'year', 'carried_over', 'adjustment'];

    protected function casts(): array
    {
        return ['carried_over' => 'decimal:1', 'adjustment' => 'decimal:1'];
    }
}
