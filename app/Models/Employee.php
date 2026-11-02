<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

/**
 * @property int $id
 * @property int $company_id
 * @property ?int $user_id
 * @property string $employee_no
 * @property string $full_name
 * @property string $job_title
 * @property ?string $work_email
 * @property Carbon $hire_date
 * @property ?Carbon $end_date
 * @property ?int $manager_id
 * @property string $status
 * @property ?array<string, mixed> $personal_data
 * @property ?string $bank_name
 * @property ?string $bank_account
 * @property ?string $bank_account_last4
 * @property-read Company $company
 * @property-read ?Department $department
 * @property-read ?Location $location
 * @property-read ?Employee $manager
 * @property-read Collection<int, Employee> $reports
 * @property-read Collection<int, EmployeeIdentifier> $identifiers
 * @property-read Collection<int, Contract> $contracts
 */
class Employee extends Model implements AuditableContract
{
    use Auditable, BelongsToCompany, HasFactory;

    protected $fillable = [
        'company_id', 'user_id', 'employee_no', 'full_name', 'preferred_name', 'job_title', 'work_email',
        'hire_date', 'end_date', 'department_id', 'manager_id', 'location_id', 'status', 'personal_data',
        'bank_name', 'bank_account',
    ];

    /** Personal data is encrypted at rest; never copy it into the audit log in clear text. */
    protected array $auditExclude = ['personal_data', 'bank_account'];

    protected function casts(): array
    {
        return [
            'hire_date' => 'date',
            'end_date' => 'date',
            'personal_data' => 'encrypted:array',
            'bank_account' => 'encrypted',
        ];
    }

    /** Keep the last four digits in clear for lists and payslips; the full number stays encrypted. */
    protected static function booted(): void
    {
        static::saving(function (Employee $e) {
            if ($e->isDirty('bank_account')) {
                $digits = preg_replace('/\D/', '', (string) $e->bank_account);
                $e->bank_account_last4 = $digits === '' ? null : substr($digits, -4);
            }
        });
    }

    public function transformAudit(array $data): array
    {
        $data['company_id'] = $this->company_id;

        return $data;
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Department, $this> */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    /** @return BelongsTo<Location, $this> */
    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    /** @return BelongsTo<self, $this> */
    public function manager(): BelongsTo
    {
        return $this->belongsTo(self::class, 'manager_id');
    }

    /** @return HasMany<self, $this> */
    public function reports(): HasMany
    {
        return $this->hasMany(self::class, 'manager_id');
    }

    /** @return HasMany<EmployeeIdentifier, $this> */
    public function identifiers(): HasMany
    {
        return $this->hasMany(EmployeeIdentifier::class);
    }

    /** @return HasMany<Contract, $this> */
    public function contracts(): HasMany
    {
        return $this->hasMany(Contract::class)->orderByDesc('start_date');
    }

    /** @return HasMany<Compensation, $this> */
    public function compensations(): HasMany
    {
        return $this->hasMany(Compensation::class)->orderByDesc('effective_from')->orderByDesc('id');
    }

    /** The compensation row in force today (latest effective_from on or before today). */
    /** @return HasOne<Compensation, $this> */
    public function currentCompensation(): HasOne
    {
        return $this->hasOne(Compensation::class)->ofMany(
            ['effective_from' => 'max', 'id' => 'max'],
            fn ($q) => $q->whereDate('effective_from', '<=', now()->toDateString()),
        );
    }

    /** @return HasMany<AttendanceRecord, $this> */
    public function attendance(): HasMany
    {
        return $this->hasMany(AttendanceRecord::class);
    }

    /** @return HasMany<LeaveRequest, $this> */
    public function leaveRequests(): HasMany
    {
        return $this->hasMany(LeaveRequest::class);
    }

    /** @return HasMany<Document, $this> */
    public function documents(): HasMany
    {
        return $this->hasMany(Document::class);
    }

    public function initials(): string
    {
        $parts = preg_split('/\s+/', trim($this->full_name)) ?: [];

        return mb_strtoupper(mb_substr($parts[0] ?? '', 0, 1).mb_substr(end($parts) ?: '', 0, 1));
    }
}
