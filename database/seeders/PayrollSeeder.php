<?php

namespace Database\Seeders;

use App\Models\AttendanceRecord;
use App\Models\Company;
use App\Models\Employee;
use App\Models\EmployeeComponent;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\OvertimeRequest;
use App\Models\PayrollRun;
use App\Models\RulePack;
use App\Models\RulePackVersion;
use App\Models\SalaryComponent;
use App\Models\User;
use App\Services\Payroll\PayrollRuns;
use App\Support\CurrentCompany;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/**
 * Rule packs (Indonesia ID-2026.1 and ID-2026.2, a pending ID-2027.1; Generic), salary components, bank details,
 * and payroll history: January–September locked and published, October calculated and waiting for two approvals.
 */
class PayrollSeeder extends Seeder
{
    public function run(): void
    {
        $super = User::where('email', 'super@roster.test')->firstOrFail();
        $hr = User::where('email', 'hr@roster.test')->firstOrFail();
        $payroll = User::where('email', 'payroll@roster.test')->firstOrFail();
        $ukHr = User::where('email', 'uk.hr@roster.test')->firstOrFail();

        [$id, $gen] = $this->packs($super, $hr);
        $nk = Company::where('name', 'Nusantara Kreatif')->firstOrFail();
        $nw = Company::where('name', 'Northwind Studio')->firstOrFail();
        $nk->update(['rule_pack_id' => $id->id, 'pay_day' => 25]);
        $nw->update(['rule_pack_id' => $gen->id, 'pay_day' => 28]);

        $this->indonesiaPay($nk);
        $this->genericPay($nw);

        $this->history($nk, 10, $payroll, $hr, thrMonth: 3);
        $this->history($nw, 10, $ukHr, $super);
    }

    /** @return array{0: RulePack, 1: RulePack} */
    private function packs(User $super, User $hr): array
    {
        $idData = require database_path('data/rule-packs/indonesia.php');
        $genData = require database_path('data/rule-packs/generic.php');

        $id = RulePack::create(['country_code' => 'ID', 'code' => 'ID', 'name' => 'Indonesia', 'method' => $idData['config']['method']]);
        $v1 = RulePackVersion::create([
            'rule_pack_id' => $id->id, 'version' => 'ID-2026.1', 'effective_from' => '2026-01-01', 'status' => 'approved',
            'config' => $idData['config'], 'sources' => $idData['sources'], 'change_note' => 'Rates for 2026: TER (PP 58/2023), BPJS, PTKP, overtime and THR.',
            'created_by' => $super->id, 'submitted_at' => '2025-12-10 10:00', 'approved_by' => $hr->id, 'approved_at' => '2025-12-11 09:30',
        ]);

        $v1->forceFill(['created_at' => '2025-12-08 11:00'])->save();
        $config = $idData['config'];
        $config['contributions'][1]['cap'] = 11_086_300;
        $sources = $idData['sources'];
        $sources['contributions.jp'] = ['note' => 'PP 45/2015; wage cap Rp11.086.300 from March 2026 (BPJS Ketenagakerjaan letter B/1226/022026)', 'verified' => '2026-11-01'];
        $v2 = RulePackVersion::create([
            'rule_pack_id' => $id->id, 'version' => 'ID-2026.2', 'effective_from' => '2026-03-01', 'status' => 'approved',
            'config' => $config, 'sources' => $sources, 'change_note' => 'JP wage cap rises with 2025 GDP growth from March.', 'based_on_id' => $v1->id,
            'created_by' => $hr->id, 'submitted_at' => '2026-02-26 14:10', 'approved_by' => $super->id, 'approved_at' => '2026-02-27 08:45',
        ]);
        $v2->forceFill(['created_at' => '2026-02-25 16:30'])->save();

        // A draft someone else must approve: the company's work-accident risk class was reassessed.
        $next = $config;
        $next['contributions'][2]['employer'] = 0.54;
        $nextSources = $sources;
        $nextSources['contributions.jkk'] = ['note' => 'PP 44/2015 risk class "low" (0.54%) after the Bandung studio added a print workshop; BPJS TK reassessment letter on file', 'verified' => '2026-10-31'];
        RulePackVersion::create([
            'rule_pack_id' => $id->id, 'version' => 'ID-2027.1', 'effective_from' => '2027-01-01', 'status' => 'pending',
            'config' => $next, 'sources' => $nextSources, 'change_note' => 'JKK risk class moves from very low to low.', 'based_on_id' => $v2->id,
            'created_by' => $super->id, 'submitted_at' => '2026-10-31 16:20',
        ])->forceFill(['created_at' => '2026-10-30 09:00'])->save();

        $gen = RulePack::create(['country_code' => null, 'code' => 'GEN', 'name' => 'Generic (illustrative)', 'method' => $genData['config']['method']]);
        RulePackVersion::create([
            'rule_pack_id' => $gen->id, 'version' => 'GEN-2026.1', 'effective_from' => '2026-01-01', 'status' => 'approved',
            'config' => $genData['config'], 'sources' => $genData['sources'], 'change_note' => 'Illustrative progressive bands for the demo.',
            'created_by' => $super->id, 'submitted_at' => '2025-12-10 10:00', 'approved_by' => $hr->id, 'approved_at' => '2025-12-11 09:30',
        ])->forceFill(['created_at' => '2025-12-08 11:00'])->save();

        return [$id, $gen];
    }

    private function indonesiaPay(Company $c): void
    {
        $component = fn (string $code, string $name, array $a = []) => SalaryComponent::create(['company_id' => $c->id, 'code' => $code, 'name' => $name, ...$a]);
        $position = $component('position', 'Position allowance');
        $meal = $component('meal', 'Meal & transport allowance', ['fixed' => false]);
        $bonus = $component('bonus', 'Performance bonus', ['fixed' => false, 'recurring' => false]);
        $advance = $component('advance', 'Salary advance repayment', ['kind' => 'deduction', 'taxable' => false, 'fixed' => false, 'recurring' => false]);

        $people = Employee::withoutGlobalScopes()->where('company_id', $c->id)->get()->keyBy('employee_no');
        $banks = ['BCA', 'Mandiri', 'BNI', 'BRI', 'CIMB Niaga'];
        foreach ($people->values() as $i => $e) {
            if ($e->employee_no !== 'NK-0016') { // the intern has not sent bank details yet
                $e->update(['bank_name' => $banks[$i % 5], 'bank_account' => (string) (5270_000_000 + $i * 7_331_907)]);
            }
            $from = max('2026-01-01', $e->hire_date->toDateString());
            $add = fn (SalaryComponent $sc, int $amount, string $date, ?string $note = null) => EmployeeComponent::create(['company_id' => $c->id, 'employee_id' => $e->id, 'salary_component_id' => $sc->id, 'amount' => $amount, 'effective_from' => $date, 'note' => $note]);
            $add($meal, $e->employee_no === 'NK-0016' ? 500_000 : 900_000, $from);
            $lead = ['NK-0001' => 5_000_000, 'NK-0002' => 2_000_000, 'NK-0003' => 1_500_000, 'NK-0005' => 1_500_000, 'NK-0010' => 2_000_000, 'NK-0017' => 1_500_000, 'NK-0023' => 1_500_000][$e->employee_no] ?? null;
            if ($lead) {
                $add($position, $lead, $from);
            }
        }

        $one = fn (string $no, SalaryComponent $sc, int $amount, string $date, string $note) => EmployeeComponent::create(['company_id' => $c->id, 'employee_id' => $people[$no]->id, 'salary_component_id' => $sc->id, 'amount' => $amount, 'effective_from' => $date, 'note' => $note]);
        $one('NK-0011', $bonus, 8_000_000, '2026-10-20', 'Q3 bonus');
        $one('NK-0001', $bonus, 15_000_000, '2026-10-20', 'Q3 bonus');
        $one('NK-0024', $bonus, 4_500_000, '2026-07-20', 'Q2 sales target');
        $one('NK-0018', $advance, 1_500_000, '2026-10-25', 'Advance from 2 Oct');

        // October inputs from Time: approved overtime for Andi, two approved unpaid days for Yusuf.
        foreach ([['2026-10-14', '17:30', '20:30', 180], ['2026-10-24', '09:00', '15:00', 360]] as [$date, $from, $to, $min]) {
            OvertimeRequest::create(['company_id' => $c->id, 'employee_id' => $people['NK-0024']->id, 'work_date' => $date, 'start_time' => $from, 'end_time' => $to, 'minutes' => $min,
                'reason' => $date === '2026-10-24' ? 'Saturday stand at the Jakarta Creative Expo' : 'Proposal deadline for Batik Keraton pitch', 'status' => 'approved']);
        }
        $unpaid = LeaveType::withoutGlobalScopes()->where('company_id', $c->id)->where('code', 'unpaid')->firstOrFail();
        LeaveRequest::create(['company_id' => $c->id, 'employee_id' => $people['NK-0004']->id, 'leave_type_id' => $unpaid->id, 'start_date' => '2026-10-20', 'end_date' => '2026-10-21', 'days' => 2, 'reason' => 'Family matter in Makassar', 'status' => 'approved']);
        AttendanceRecord::withoutGlobalScopes()->where('employee_id', $people['NK-0004']->id)->whereBetween('work_date', ['2026-10-20', '2026-10-21'])->delete();

        // Bayu resigned on 24 October: October is his final month, with the year-end tax recalculation.
        $people['NK-0026']->update(['end_date' => '2026-10-24']);
    }

    private function genericPay(Company $c): void
    {
        $bonus = SalaryComponent::create(['company_id' => $c->id, 'code' => 'bonus', 'name' => 'Bonus', 'fixed' => false, 'recurring' => false]);
        $people = Employee::withoutGlobalScopes()->where('company_id', $c->id)->get();
        foreach ($people->values() as $i => $e) {
            $e->update(['bank_name' => ['Barclays', 'Monzo', 'HSBC UK', 'NatWest'][$i % 4], 'bank_account' => sprintf('20-%02d-%02d %08d', 10 + $i, 40 + $i, 31_926_000 + $i * 4_517)]);
        }
        EmployeeComponent::create(['company_id' => $c->id, 'employee_id' => $people->firstWhere('employee_no', 'NW-003')->id, 'salary_component_id' => $bonus->id, 'amount' => 1500, 'effective_from' => '2026-10-15', 'note' => 'Launch bonus']);
    }

    /** Lock and publish every month before $openMonth; calculate $openMonth and leave it for review. */
    private function history(Company $company, int $openMonth, User $officer, User $approver, ?int $thrMonth = null): void
    {
        $current = app(CurrentCompany::class);
        $current->set($company);
        $runs = app(PayrollRuns::class);
        for ($m = 1; $m <= $openMonth; $m++) {
            $month = CarbonImmutable::create(2026, $m, 1);
            $this->travel($month->endOfMonth()->setTime(9, 0));
            $run = $runs->create($company, $month, $m === $thrMonth, $officer);
            $runs->calculate($run, $officer);
            if ($m === $openMonth) {
                break;
            }
            foreach ($run->items()->get() as $item) {
                if ($item->needsReview()) {
                    $runs->review($item, $officer);
                }
            }
            $runs->approve($run, $officer);
            $runs->approve($run->refresh(), $approver);
            $runs->lock($run->refresh(), $officer);
            $this->travel($month->endOfMonth()->setTime(15, 0));
            $runs->publish($run->refresh());
        }
        $this->travel(null);
        $current->forget();
        PayrollRun::withoutGlobalScopes()->where('company_id', $company->id)->count();
    }

    private function travel(?CarbonImmutable $at): void
    {
        CarbonImmutable::setTestNow($at);
        Carbon::setTestNow($at);
    }
}
