<?php

/*
 * Generic progressive pack for companies outside a country pack. The numbers are ILLUSTRATIVE round values for the demo,
 * not any country's real tax (UK PAYE/NI included). Companies must replace them before relying on the result.
 */
$verified = '2026-11-01';

return [
    'config' => [
        'method' => 'progressive_monthly',
        'currency' => 'GBP',
        'decimals' => 2,
        'allowance_annual' => 12_000,
        'bands_annual' => [[36_000, 20], [114_000, 40], [null, 45]],
        'contributions' => [
            ['code' => 'social_ee', 'name' => 'Social insurance', 'employer' => 0, 'employee' => 8, 'cap' => 4_200, 'exempt_below' => 1_000, 'taxable_employer' => false, 'deductible_employee' => false],
            ['code' => 'social_er', 'name' => 'Employer social insurance', 'employer' => 13.8, 'employee' => 0, 'cap' => null, 'exempt_below' => 800, 'taxable_employer' => false, 'deductible_employee' => false],
            ['code' => 'pension', 'name' => 'Workplace pension', 'employer' => 3, 'employee' => 5, 'cap' => 4_200, 'exempt_below' => 500, 'taxable_employer' => false, 'deductible_employee' => false],
        ],
        'overtime' => ['hourly_divisor' => 162.5, 'workday' => [[null, 1.5]], 'restday' => [[null, 2]]],
        'thr' => null,
    ],
    'sources' => [
        'bands_annual' => ['note' => 'Illustrative demo values, not a real tax table', 'verified' => $verified],
        'allowance_annual' => ['note' => 'Illustrative demo value', 'verified' => $verified],
        'contributions.social_ee' => ['note' => 'Illustrative: 8% between 1,000 and 4,200 a month', 'verified' => $verified],
        'contributions.social_er' => ['note' => 'Illustrative: 13.8% above 800 a month', 'verified' => $verified],
        'contributions.pension' => ['note' => 'Illustrative: 5% employee, 3% employer between 500 and 4,200 a month', 'verified' => $verified],
        'overtime' => ['note' => 'Illustrative: 1.5× on workdays, 2× on rest days; hourly = monthly ÷ 162.5', 'verified' => $verified],
    ],
];
