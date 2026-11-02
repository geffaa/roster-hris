<?php

namespace App\Http\Controllers\Me;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\PayrollItem;
use App\Models\Payslip;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Symfony\Component\HttpFoundation\Response;

/** S10 — an employee's own payslips: list, detail, PDF. Only the owner can open one; others get 404. */
class PayslipController extends Controller
{
    public function index(Request $request): InertiaResponse
    {
        $employee = TodayController::employeeOf($request);
        $slips = Payslip::with('item.run:id,period_start,pay_date')->where('employee_id', $employee->id)->get()
            ->sortByDesc(fn (Payslip $p) => $p->item->run->period_start)->values();

        return Inertia::render('me/Pay', [
            'slips' => $slips->map(fn (Payslip $p) => [
                'id' => $p->id, 'period' => $p->item->run->period_start->toDateString(), 'pay_date' => $p->item->run->pay_date->toDateString(),
                'net' => (float) $p->item->net, 'gross' => (float) $p->item->gross, 'currency' => $p->item->currency, 'new' => $p->first_viewed_at === null,
            ]),
        ]);
    }

    public function show(Request $request, Payslip $payslip): InertiaResponse
    {
        $item = $this->own($request, $payslip);
        if ($payslip->first_viewed_at === null) {
            $payslip->update(['first_viewed_at' => now()]);
        }

        return Inertia::render('me/Payslip', ['slip' => $this->present($payslip, $item)]);
    }

    public function pdf(Request $request, Payslip $payslip): Response
    {
        $item = $this->own($request, $payslip);
        $slip = $this->present($payslip, $item);
        $company = Company::findOrFail($item->company_id);

        return Pdf::loadView('pdf.payslip', ['slip' => $slip, 'company' => $company])
            ->setPaper('a4')
            ->stream('payslip-'.Str::slug($slip['name']).'-'.substr($slip['period'], 0, 7).'.pdf');
    }

    private function own(Request $request, Payslip $payslip): PayrollItem
    {
        $employee = TodayController::employeeOf($request);
        abort_unless($payslip->employee_id === $employee->id, 404);

        return $payslip->item()->with('run:id,period_start,pay_date,rule_pack_version_id', 'run.rulePackVersion:id,version')->firstOrFail();
    }

    /** @return array<string, mixed> */
    private function present(Payslip $payslip, PayrollItem $item): array
    {
        return [
            'id' => $payslip->id,
            'period' => $item->run->period_start->toDateString(),
            'pay_date' => $item->run->pay_date->toDateString(),
            'name' => $item->context['name'],
            'employee_no' => $item->context['employee_no'],
            'job_title' => $item->context['job_title'],
            'department' => $item->context['department'],
            'tax_status' => $item->context['tax_status'],
            'bank' => $item->context['bank'],
            'unpaid_dates' => $item->context['unpaid_dates'] ?? [],
            'currency' => $item->currency,
            'decimals' => $item->currency === 'IDR' ? 0 : 2,
            'gross' => (float) $item->gross,
            'tax' => (float) $item->tax,
            'employee_contributions' => (float) $item->employee_contributions,
            'employer_contributions' => (float) $item->employer_contributions,
            'other_deductions' => (float) $item->other_deductions,
            'net' => (float) $item->net,
            'breakdown' => $item->breakdown,
            'tax_detail' => $item->tax_detail,
            'version' => $item->run->rulePackVersion?->version,
        ];
    }
}
