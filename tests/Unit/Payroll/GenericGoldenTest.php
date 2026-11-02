<?php

use App\Payroll\PayrollCalculator;
use App\Payroll\PayslipInput;
use App\Payroll\RulePackSnapshot;

/* Generic (illustrative) pack: allowance 12,000/yr; 20% to 36,000, 40% to 114,000, 45% above; amounts in GBP. */
function gen(float $salary, int $days = 30, int $periodDays = 30)
{
    $pack = new RulePackSnapshot('GEN-2026.1', (require __DIR__.'/../../../database/data/rule-packs/generic.php')['config']);

    return (new PayrollCalculator)->calculate(new PayslipInput('September 2026', $periodDays, [['amount' => $salary, 'days' => $days]], $salary, 22), $pack);
}

it('G1 · basic rate only', function () {
    // tax (36,000 − 12,000) × 20% / 12 = 400; social 8% × 2,000 = 160; pension 5% × 2,500 = 125
    $r = gen(3_000);
    expect($r->tax)->toBe(40_000)->and($r->line('social_ee_ee')->amount)->toBe(-16_000)
        ->and($r->line('social_er_er')->amount)->toBe(30_360)->and($r->line('pension_ee')->amount)->toBe(-12_500)
        ->and($r->net)->toBe(231_500);
});

it('G2 · into the higher band, social insurance capped', function () {
    // (60,000 − 12,000): 7,200 + 4,800 = 12,000 → 1,000/month; social (4,200 − 1,000) × 8% = 256; pension 185
    $r = gen(5_000);
    expect($r->tax)->toBe(100_000)->and($r->line('social_ee_ee')->amount)->toBe(-25_600)->and($r->net)->toBe(355_900);
});

it('G3 · under the allowance and the social threshold', function () {
    $r = gen(900);
    expect($r->tax)->toBe(0)->and($r->line('social_ee_ee'))->toBeNull()
        ->and($r->line('social_er_er')->amount)->toBe(1_380)->and($r->net)->toBe(88_000);
});

it('G4 · top band', function () {
    // (144,000 − 12,000): 7,200 + 31,200 + 8,100 = 46,500 → 3,875/month
    $r = gen(12_000);
    expect($r->tax)->toBe(387_500)->and($r->line('social_er_er')->amount)->toBe(154_560)->and($r->net)->toBe(768_400);
});

it('G5 · joiner on the 16th is prorated and taxed on the prorated pay', function () {
    // 4,000 × 15/30 = 2,000; tax 200; social 80; pension 75
    $r = gen(4_000, 15);
    expect($r->gross)->toBe(200_000)->and($r->tax)->toBe(20_000)->and($r->net)->toBe(164_500);
});
