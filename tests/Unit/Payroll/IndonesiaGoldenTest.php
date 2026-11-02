<?php

use App\Payroll\PayrollCalculator;
use App\Payroll\PayslipInput;
use App\Payroll\RulePackSnapshot;

/*
 * Golden cases for the Indonesia pack. Expected values are worked out by hand in the comments.
 * Employer JKK 0.24%, JKM 0.30% and BPJS Kesehatan 4% are part of taxable gross (PMK 168/2023);
 * employee JHT 2% and JP 1% reduce yearly taxable income in the final month.
 */
function idPack(string $version = '2026.2'): RulePackSnapshot
{
    $config = (require __DIR__.'/../../../database/data/rule-packs/indonesia.php')['config'];
    if ($version === '2026.2') {
        $config['contributions'][1]['cap'] = 11_086_300; // JP wage cap from March 2026
    }

    return new RulePackSnapshot("ID-{$version}", $config);
}

function month(float $salary, array $extra = []): PayslipInput
{
    return new PayslipInput(...[
        'periodLabel' => 'October 2026', 'periodDays' => 31, 'periodWorkingDays' => 22,
        'salarySegments' => [['amount' => $salary, 'days' => 31]], 'currentSalary' => $salary, ...$extra,
    ]);
}

function calc(PayslipInput $in, ?RulePackSnapshot $pack = null)
{
    return (new PayrollCalculator)->calculate($in, $pack ?? idPack());
}

it('1 · TK/0 on Rp10 juta: TER A 2.5%', function () {
    // bruto 10,000,000 + JKK 24,000 + JKM 30,000 + Kes 400,000 = 10,454,000 → A row 10,350,001–10,700,000 = 2.5% → 261,350
    $r = calc(month(10_000_000));
    expect($r->taxBruto)->toBe(10_454_000)->and($r->tax)->toBe(261_350)
        ->and($r->employeeContributions)->toBe(400_000)->and($r->employerContributions)->toBe(1_024_000)
        ->and($r->net)->toBe(9_338_650)->and($r->taxDetail['category'])->toBe('A');
});

it('2 · K/1 above the JP and Kesehatan caps: TER B 7%', function () {
    // wage 16,400,000; JP on cap 11,086,300 → 110,863 / 221,726; Kes on 12,000,000 → 120,000 / 480,000; JHT 328,000
    // bruto 16,400,000 + 39,360 + 49,200 + 480,000 = 16,968,560 → B 16,400,001–18,450,000 = 7% → 1,187,799
    $r = calc(month(15_000_000, ['taxStatus' => 'K/1', 'allowances' => [['code' => 'fixed', 'name' => 'Fixed allowance', 'amount' => 1_400_000]]]));
    expect($r->line('jp_ee')->amount)->toBe(-110_863)->and($r->line('jp_ee')->basis)->toBe('1% of 11,086,300 (cap)')
        ->and($r->line('bpjs_kes_er')->amount)->toBe(480_000)
        ->and($r->taxBruto)->toBe(16_968_560)->and($r->tax)->toBe(1_187_799)->and($r->net)->toBe(14_653_338);
});

it('3 · uses the JP cap of the version in force: Rp10,547,400 before March, Rp11,086,300 after', function () {
    expect(calc(month(12_000_000), idPack('2026.1'))->line('jp_ee')->amount)->toBe(-105_474)
        ->and(calc(month(12_000_000), idPack('2026.2'))->line('jp_ee')->amount)->toBe(-110_863);
});

it('4 · K/3 below the first TER C threshold pays no tax', function () {
    // bruto 6,000,000 + 14,400 + 18,000 + 240,000 = 6,272,400 ≤ 6,600,000 → 0%
    $r = calc(month(6_000_000, ['taxStatus' => 'K/3']));
    expect($r->tax)->toBe(0)->and($r->taxDetail['category'])->toBe('C')->and($r->net)->toBe(5_760_000);
});

it('5 · two days of unpaid leave come off base pay, not off contributions', function () {
    // 6,250,000 ÷ 22 × 2 = 568,182; gross 5,681,818; bruto + 15,000 + 18,750 + 250,000 = 5,965,568 → A 0.75% → 44,741
    $r = calc(month(6_250_000, ['taxStatus' => 'TK/1', 'unpaidLeaveDays' => 2]));
    expect($r->line('unpaid_leave')->amount)->toBe(-568_182)->and($r->gross)->toBe(5_681_818)
        ->and($r->line('jht_ee')->amount)->toBe(-125_000)->and($r->tax)->toBe(44_741)->and($r->net)->toBe(5_387_077);
});

it('6 · joiner on 14 October is prorated by calendar days, contributions too', function () {
    // 7,000,000 × 18/31 = 4,064,516; JHT 81,290, JP 40,645, Kes 40,645; bruto 4,249,046 → 0%
    $r = calc(new PayslipInput('October 2026', 31, [['amount' => 7_000_000, 'days' => 18]], 7_000_000, 22));
    expect($r->line('base')->amount)->toBe(4_064_516)->and($r->line('base')->basis)->toBe('18 of 31 days')
        ->and($r->employeeContributions)->toBe(162_580)->and($r->tax)->toBe(0)->and($r->net)->toBe(3_901_936);
});

it('7 · overtime: 1.5× then 2× on a workday, 2×/3×/4× on a rest day, hourly = 1/173', function () {
    // hourly 8,000,000/173; 3 h workday = 5.5 units → 254,335; 10 h rest day = 23 units → 1,063,584
    // bruto 9,317,919 + 19,200 + 24,000 + 320,000 = 9,681,119 → A 2% → 193,622
    $r = calc(month(8_000_000, ['overtime' => [
        ['date' => '2026-10-14', 'hours' => 3, 'rest_day' => false],
        ['date' => '2026-10-17', 'hours' => 10, 'rest_day' => true],
    ]]));
    expect($r->line('overtime')->amount)->toBe(1_317_919)->and($r->line('jht_ee')->amount)->toBe(-160_000)
        ->and($r->tax)->toBe(193_622)->and($r->net)->toBe(8_804_297);
});

it('8 · THR of one month lifts the month into a higher TER bracket', function () {
    // gross 18,000,000; bruto + 21,600 + 27,000 + 360,000 = 18,408,600 → A 16,950,001–19,750,000 = 8% → 1,472,688
    $r = calc(month(9_000_000, ['taxStatus' => 'K/0', 'includeThr' => true, 'serviceMonths' => 40]));
    expect($r->line('thr')->amount)->toBe(9_000_000)->and($r->tax)->toBe(1_472_688)->and($r->net)->toBe(16_167_312);
});

it('9 · THR is pro rata under 12 months of service', function () {
    // 6,000,000 × 7/12 = 3,500,000; bruto 9,772,400 → A 2% → 195,448
    $r = calc(month(6_000_000, ['includeThr' => true, 'serviceMonths' => 7]));
    expect($r->line('thr')->amount)->toBe(3_500_000)->and($r->line('thr')->basis)->toBe('7/12 of monthly wage')
        ->and($r->tax)->toBe(195_448)->and($r->net)->toBe(9_064_552);
});

it('10 · December recalculates the year with Article 17', function () {
    // year bruto 12 × 10,454,000 = 125,448,000; biaya jabatan capped 6,000,000; JHT+JP 3,600,000
    // neto 115,848,000 − PTKP 54,000,000 = PKP 61,848,000 → 3,000,000 + 277,200 = 3,277,200 − withheld 2,874,850 = 402,350
    $r = calc(month(10_000_000, ['periodLabel' => 'December 2026', 'finalPeriod' => true,
        'ytd' => ['tax_bruto' => 114_994_000, 'tax_withheld' => 2_874_850, 'employee_deductible' => 3_300_000, 'months' => 11]]));
    expect($r->taxDetail['pkp'])->toBe(61_848_000)->and($r->taxDetail['annual_tax'])->toBe(3_277_200)
        ->and($r->tax)->toBe(402_350)->and($r->net)->toBe(9_197_650);
});

it('11 · December refunds tax over-withheld earlier in the year', function () {
    // year bruto 120,454,000; biaya jabatan 6,000,000; JHT+JP 3,300,000; PTKP K/1 63,000,000 → PKP 48,154,000 → 2,407,700
    // withheld 6,000,000 → refund 3,592,300
    $r = calc(month(10_000_000, ['taxStatus' => 'K/1', 'finalPeriod' => true,
        'ytd' => ['tax_bruto' => 110_000_000, 'tax_withheld' => 6_000_000, 'employee_deductible' => 3_000_000, 'months' => 11]]));
    expect($r->tax)->toBe(-3_592_300)->and($r->line('tax')->amount)->toBe(3_592_300)->and($r->net)->toBe(13_192_300);
});

it('12 · leaving on 15 June makes June the final month, with biaya jabatan capped for 6 months', function () {
    // June base 4,000,000 (15/30); bruto 4,181,600; year bruto 45,997,600; biaya jabatan 5% = 2,299,880 (< 3,000,000)
    // neto 42,377,720 < PTKP 54,000,000 → yearly tax 0 → refund the 627,240 withheld Jan–May
    $r = calc(new PayslipInput('June 2026', 30, [['amount' => 8_000_000, 'days' => 15]], 8_000_000, 22, finalPeriod: true,
        ytd: ['tax_bruto' => 41_816_000, 'tax_withheld' => 627_240, 'employee_deductible' => 1_200_000, 'months' => 5]));
    expect($r->taxDetail['position_cost'])->toBe(2_299_880)->and($r->taxDetail['pkp'])->toBe(0)
        ->and($r->tax)->toBe(-627_240)->and($r->net)->toBe(4_467_240);
});

it('13 · a salary change mid-month is split by days', function () {
    // 10,000,000 × 15/31 + 12,000,000 × 16/31 = 4,838,710 + 6,193,548 = 11,032,258
    $r = calc(new PayslipInput('October 2026', 31, [['amount' => 10_000_000, 'days' => 15], ['amount' => 12_000_000, 'days' => 16]], 12_000_000, 22));
    expect($r->line('base')->amount)->toBe(11_032_258);
});

it('14 · one-off deductions reduce net pay but not taxable gross', function () {
    $r = calc(month(10_000_000, ['oneOffs' => [['code' => 'loan', 'name' => 'Salary advance repayment', 'amount' => 500_000, 'kind' => 'deduction']]]));
    expect($r->taxBruto)->toBe(10_454_000)->and($r->tax)->toBe(261_350)->and($r->net)->toBe(8_838_650);
});

it('rejects an unknown PTKP status', function () {
    calc(month(10_000_000, ['taxStatus' => 'K/9']));
})->throws(InvalidArgumentException::class);
