<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $company_id
 * @property int $payroll_run_id
 * @property int $employee_id
 * @property string $currency
 * @property string $gross
 * @property string $tax_bruto
 * @property string $tax
 * @property string $employee_contributions
 * @property string $employer_contributions
 * @property string $employee_deductible
 * @property string $other_deductions
 * @property string $net
 * @property ?string $previous_net
 * @property list<array{code: string, label: string, section: string, amount: int, basis: ?string, rule: ?string}> $breakdown
 * @property array<string, mixed> $tax_detail
 * @property list<array{type: string, level: string, message: string}> $flags
 * @property array<string, mixed> $context
 * @property ?int $reviewed_by
 * @property ?Carbon $reviewed_at
 * @property-read PayrollRun $run
 * @property-read Employee $employee
 * @property-read ?Payslip $payslip
 */
class PayrollItem extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'company_id', 'payroll_run_id', 'employee_id', 'currency', 'gross', 'tax_bruto', 'tax', 'employee_contributions',
        'employer_contributions', 'employee_deductible', 'other_deductions', 'net', 'previous_net', 'breakdown', 'tax_detail',
        'flags', 'context', 'reviewed_by', 'reviewed_at',
    ];

    protected function casts(): array
    {
        return ['breakdown' => 'array', 'tax_detail' => 'array', 'flags' => 'array', 'context' => 'array', 'reviewed_at' => 'datetime'];
    }

    public function hasFlag(string $level): bool
    {
        return collect($this->flags)->contains('level', $level);
    }

    /** Items with a "check" flag need a reviewer before the run can be approved. */
    public function needsReview(): bool
    {
        return $this->hasFlag('check') && $this->reviewed_at === null;
    }

    /** @return BelongsTo<PayrollRun, $this> */
    public function run(): BelongsTo
    {
        return $this->belongsTo(PayrollRun::class, 'payroll_run_id');
    }

    /** @return BelongsTo<Employee, $this> */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /** @return HasOne<Payslip, $this> */
    public function payslip(): HasOne
    {
        return $this->hasOne(Payslip::class);
    }
}
