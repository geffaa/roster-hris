<?php

namespace App\Services;

use App\Models\Claim;
use App\Models\ClaimCategory;
use App\Models\Company;
use App\Models\Employee;
use App\Services\Time\ApprovalService;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Reimbursement claims: checked against category limits, approved by the manager, paid with the next payroll. */
class ClaimService
{
    public function __construct(private ApprovalService $approvals) {}

    public function submit(Employee $e, ClaimCategory $category, string $spentOn, float $amount, string $description, ?UploadedFile $receipt): Claim
    {
        $tz = Company::findOrFail($e->company_id)->timezone;
        $date = CarbonImmutable::parse($spentOn, $tz);
        $today = CarbonImmutable::now($tz)->startOfDay();
        if ($date->greaterThan($today)) {
            throw ValidationException::withMessages(['spent_on' => __('Claims are for money already spent.')]);
        }
        if ($date->lessThan($today->subDays(90))) {
            throw ValidationException::withMessages(['spent_on' => __('Claims must be sent within 90 days of spending.')]);
        }
        if ($category->receipt_required && ! $receipt) {
            throw ValidationException::withMessages(['receipt' => __('Add a photo of the receipt for :category.', ['category' => $category->name])]);
        }
        if ($category->monthly_limit !== null) {
            $used = (float) Claim::where('employee_id', $e->id)->where('claim_category_id', $category->id)->whereIn('status', ['pending', 'approved', 'paid'])
                ->whereYear('spent_on', $date->year)->whereMonth('spent_on', $date->month)->sum('amount');
            $left = (float) $category->monthly_limit - $used;
            if ($amount > $left) {
                throw ValidationException::withMessages(['amount' => __(':category is limited to :limit a month; :left is left for :month.', [
                    'category' => $category->name, 'limit' => number_format((float) $category->monthly_limit), 'left' => number_format(max(0, $left)), 'month' => $date->translatedFormat('F'),
                ])]);
            }
        }

        return DB::transaction(function () use ($e, $category, $date, $amount, $description, $receipt) {
            $claim = Claim::create([
                'company_id' => $e->company_id, 'employee_id' => $e->id, 'claim_category_id' => $category->id, 'spent_on' => $date->toDateString(),
                'amount' => $amount, 'description' => $description,
                'receipt_path' => $receipt?->store("companies/{$e->company_id}/claims", 'local'),
            ]);
            $this->approvals->submit('claim', $claim, $e);

            return $claim;
        });
    }
}
