<?php

use App\Enums\Role;
use App\Http\Middleware\RequireTwoFactor;
use App\Models\Company;
use App\Models\Employee;
use App\Models\Holiday;
use App\Models\LeaveType;
use App\Models\Location;
use App\Models\RulePack;
use App\Models\RulePackVersion;
use App\Models\User;
use App\Models\WorkPattern;
use App\Support\CurrentCompany;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind a different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function something()
{
    // ..
}

/*
|--------------------------------------------------------------------------
| Roster helpers
|--------------------------------------------------------------------------
*/

/** Create a user who belongs to $company with $role, optionally linked to an employee record. */
function memberOf(Company $company, Role $role, ?Employee $employee = null): User
{
    $user = User::factory()->create();
    $user->companies()->attach($company->id, ['is_default' => true]);
    setPermissionsTeamId($company->id);
    $user->assignRole($role->value);
    setPermissionsTeamId(null);
    $user->unsetRelation('roles');
    $employee?->forceFill(['user_id' => $user->id])->save();

    return $user;
}

/** Act as $user with two-factor already enrolled and passed for this session. */
function signIn(User $user): TestCase
{
    $user->forceFill(['two_factor_secret' => 'JBSWY3DPEHPK3PXPJBSWY3DPEHPK3PXP', 'two_factor_confirmed_at' => now()])->save();

    return test()->actingAs($user)->withSession([RequireTwoFactor::SESSION_KEY => $user->id]);
}

/**
 * A Jakarta company with an 08:30–17:30 Mon–Fri default shift (10 min grace), a geofenced office,
 * a public holiday on Tue 17 Nov 2026, and annual (12 days, 12 months' service), sick and unpaid leave.
 *
 * @return array{company: Company, office: Location, pattern: WorkPattern, annual: LeaveType, sick: LeaveType, unpaid: LeaveType}
 */
function timeSetup(): array
{
    test()->seed(RoleSeeder::class);
    $company = Company::factory()->create(['timezone' => 'Asia/Jakarta', 'country_code' => 'ID']);
    $office = Location::create(['company_id' => $company->id, 'name' => 'Jakarta HQ', 'lat' => -6.2297, 'lng' => 106.8295, 'radius_m' => 120]);
    $pattern = WorkPattern::create(['company_id' => $company->id, 'name' => 'Office', 'start_time' => '08:30', 'end_time' => '17:30', 'grace_min' => 10, 'working_days' => [1, 2, 3, 4, 5], 'is_default' => true]);
    Holiday::create(['country_code' => 'ID', 'date' => '2026-11-17', 'name' => 'Test Holiday']);
    Holiday::create(['country_code' => 'ID', 'date' => '2026-12-25', 'name' => 'Christmas Day']);
    Holiday::create(['country_code' => 'ID', 'date' => '2027-01-01', 'name' => 'New Year']);
    $type = fn (array $a) => LeaveType::create(['company_id' => $company->id, ...$a]);

    return [
        'company' => $company,
        'office' => $office,
        'pattern' => $pattern,
        'annual' => $type(['code' => 'annual', 'name' => 'Annual leave', 'annual_days' => 12, 'min_service_months' => 12]),
        'sick' => $type(['code' => 'sick', 'name' => 'Sick leave', 'annual_days' => null]),
        'unpaid' => $type(['code' => 'unpaid', 'name' => 'Unpaid leave', 'paid' => false, 'annual_days' => null, 'requires_second_approval' => true, 'half_day_allowed' => false]),
    ];
}

/**
 * timeSetup() plus the Indonesia rule pack (ID-2026.1 from 1 Jan, ID-2026.2 from 1 Mar with the higher JP cap),
 * a payroll officer and an HR admin who can approve, and the company set as current for direct service calls.
 *
 * @return array<string, mixed>
 */
function payrollSetup(): array
{
    $t = timeSetup();
    $data = require database_path('data/rule-packs/indonesia.php');
    $pack = RulePack::create(['country_code' => 'ID', 'code' => 'ID', 'name' => 'Indonesia', 'method' => $data['config']['method']]);
    $author = User::factory()->create();
    $v1 = RulePackVersion::create(['rule_pack_id' => $pack->id, 'version' => 'ID-2026.1', 'effective_from' => '2026-01-01', 'status' => 'approved', 'config' => $data['config'], 'sources' => $data['sources'], 'created_by' => $author->id]);
    $config = $data['config'];
    $config['contributions'][1]['cap'] = 11_086_300;
    $v2 = RulePackVersion::create(['rule_pack_id' => $pack->id, 'version' => 'ID-2026.2', 'effective_from' => '2026-03-01', 'status' => 'approved', 'config' => $config, 'sources' => $data['sources'], 'based_on_id' => $v1->id, 'created_by' => $author->id]);
    $t['company']->update(['rule_pack_id' => $pack->id, 'pay_day' => 25]);
    app(CurrentCompany::class)->set($t['company']->refresh());

    return $t + [
        'pack' => $pack, 'v1' => $v1, 'v2' => $v2,
        'officer' => memberOf($t['company'], Role::PayrollOfficer),
        'hr' => memberOf($t['company'], Role::HrAdmin),
    ];
}

/** An employee on $salary a month with a bank account, hired well before 2026 unless told otherwise. */
function hire(Company $company, float $salary, string $status = 'TK/0', string $hired = '2024-01-08', ?string $bank = '5270011223'): Employee
{
    $e = Employee::factory()->for($company)->create(['hire_date' => $hired, 'bank_name' => $bank ? 'BCA' : null, 'bank_account' => $bank]);
    $e->compensations()->create(['effective_from' => $hired, 'base_salary' => $salary, 'currency' => $company->base_currency, 'tax_status' => $status, 'reason' => 'Starting salary']);

    return $e;
}
