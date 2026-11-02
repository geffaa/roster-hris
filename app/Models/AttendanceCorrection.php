<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Keeps the original times next to the requested ones so an approved correction never erases history.
 *
 * @property int $id
 * @property int $attendance_record_id
 * @property int $requested_by
 * @property ?Carbon $original_in
 * @property ?Carbon $original_out
 * @property Carbon $new_in
 * @property Carbon $new_out
 * @property string $reason
 * @property string $status
 * @property-read AttendanceRecord $record
 */
class AttendanceCorrection extends Model
{
    protected $fillable = ['attendance_record_id', 'requested_by', 'original_in', 'original_out', 'new_in', 'new_out', 'reason', 'status'];

    protected function casts(): array
    {
        return ['original_in' => 'datetime', 'original_out' => 'datetime', 'new_in' => 'datetime', 'new_out' => 'datetime'];
    }

    /** @return BelongsTo<AttendanceRecord, $this> */
    public function record(): BelongsTo
    {
        return $this->belongsTo(AttendanceRecord::class, 'attendance_record_id');
    }
}
