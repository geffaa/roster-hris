<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

/**
 * Times are stored in UTC and always come from the server clock, never the device.
 *
 * @property int $id
 * @property int $company_id
 * @property int $employee_id
 * @property Carbon $work_date
 * @property Carbon $clock_in_at
 * @property ?Carbon $clock_out_at
 * @property ?int $in_distance_m
 * @property ?int $location_id
 * @property bool $remote
 * @property string $status
 * @property int $late_min
 * @property-read Employee $employee
 * @property-read ?Location $location
 */
class AttendanceRecord extends Model implements AuditableContract
{
    use Auditable, BelongsToCompany;

    protected $fillable = ['company_id', 'employee_id', 'work_date', 'clock_in_at', 'clock_out_at', 'in_lat', 'in_lng', 'out_lat', 'out_lng', 'in_distance_m', 'location_id', 'remote', 'status', 'late_min'];

    /** Coordinates are personal data; the log keeps times and status only. */
    protected array $auditExclude = ['in_lat', 'in_lng', 'out_lat', 'out_lng'];

    protected $attributes = ['remote' => false, 'status' => 'ok', 'late_min' => 0];

    protected function casts(): array
    {
        return ['work_date' => 'date', 'clock_in_at' => 'datetime', 'clock_out_at' => 'datetime', 'remote' => 'boolean', 'late_min' => 'integer', 'in_distance_m' => 'integer'];
    }

    public function transformAudit(array $data): array
    {
        $data['company_id'] = $this->company_id;

        return $data;
    }

    /** @return BelongsTo<Employee, $this> */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /** @return BelongsTo<Location, $this> */
    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    /** @return HasMany<AttendanceCorrection, $this> */
    public function corrections(): HasMany
    {
        return $this->hasMany(AttendanceCorrection::class);
    }
}
