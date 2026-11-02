<?php

/*
 * Indonesia rule pack, version ID-2026.1 (from 1 Jan 2026). ID-2026.2 (from 1 Mar 2026) only raises the JP wage cap;
 * see RulePackSeeder. Every number carries a source note; "verify" marks numbers taken from secondary sources.
 */
$verified = '2026-11-01';

return [
    'config' => [
        'method' => 'ter_monthly_with_december_true_up',
        'currency' => 'IDR',
        'decimals' => 0,
        'ptkp' => ['base' => 54_000_000, 'married' => 4_500_000, 'per_dependant' => 4_500_000, 'max_dependants' => 3],
        'ter_categories' => ['A' => ['TK/0', 'TK/1', 'K/0'], 'B' => ['TK/2', 'TK/3', 'K/1', 'K/2'], 'C' => ['K/3']],
        'ter' => require __DIR__.'/ter-pp58-2023.php',
        'art17' => [[60_000_000, 5], [250_000_000, 15], [500_000_000, 25], [5_000_000_000, 30], [null, 35]],
        'position_cost' => ['rate' => 5, 'monthly_cap' => 500_000],
        'contributions' => [
            ['code' => 'jht', 'name' => 'BPJS JHT', 'employer' => 3.7, 'employee' => 2, 'cap' => null, 'exempt_below' => 0, 'taxable_employer' => false, 'deductible_employee' => true],
            ['code' => 'jp', 'name' => 'BPJS JP', 'employer' => 2, 'employee' => 1, 'cap' => 10_547_400, 'exempt_below' => 0, 'taxable_employer' => false, 'deductible_employee' => true],
            ['code' => 'jkk', 'name' => 'BPJS JKK', 'employer' => 0.24, 'employee' => 0, 'cap' => null, 'exempt_below' => 0, 'taxable_employer' => true, 'deductible_employee' => false],
            ['code' => 'jkm', 'name' => 'BPJS JKM', 'employer' => 0.3, 'employee' => 0, 'cap' => null, 'exempt_below' => 0, 'taxable_employer' => true, 'deductible_employee' => false],
            ['code' => 'bpjs_kes', 'name' => 'BPJS Kesehatan', 'employer' => 4, 'employee' => 1, 'cap' => 12_000_000, 'exempt_below' => 0, 'taxable_employer' => true, 'deductible_employee' => false],
        ],
        'overtime' => ['hourly_divisor' => 173, 'workday' => [[1, 1.5], [null, 2]], 'restday' => [[8, 2], [9, 3], [null, 4]]],
        'thr' => ['min_service_months' => 1, 'full_after_months' => 12],
    ],
    'sources' => [
        'ter' => ['note' => 'PP 58/2023 Lampiran (TER A/B/C), via Ortax reproduction Feb 2024', 'verified' => $verified],
        'ter_categories' => ['note' => 'PP 58/2023 Pasal 2: A = TK/0, TK/1, K/0; B = TK/2, TK/3, K/1, K/2; C = K/3', 'verified' => $verified],
        'art17' => ['note' => 'UU PPh Pasal 17 ayat (1) huruf a as amended by UU HPP 7/2021', 'verified' => $verified],
        'ptkp' => ['note' => 'PMK 101/PMK.010/2016, unchanged by UU HPP', 'verified' => $verified],
        'position_cost' => ['note' => 'Biaya jabatan 5%, max Rp6.000.000 a year (Rp500.000 a month); PMK 168/2023', 'verified' => $verified],
        'contributions.jht' => ['note' => 'PP 46/2015: 3.7% employer, 2% employee, no wage cap', 'verified' => $verified],
        'contributions.jp' => ['note' => 'PP 45/2015; wage cap Rp10.547.400 from Mar 2025 (BPJS Ketenagakerjaan)', 'verified' => $verified],
        'contributions.jkk' => ['note' => 'PP 44/2015: 0.24%–1.74% by risk class; 0.24% (very low risk) assumed for an office company', 'verified' => $verified],
        'contributions.jkm' => ['note' => 'PP 44/2015: 0.30% employer. JKP is funded by recomposing JKK/JKM (PP 37/2021), no extra cost', 'verified' => $verified],
        'contributions.bpjs_kes' => ['note' => 'Perpres 82/2018 as amended (Perpres 59/2024 Pasal 32): 4% + 1%, wage cap Rp12.000.000, floor UMK not applied. Verify: news sources only for 2026', 'verified' => $verified, 'verify' => true],
        'overtime' => ['note' => 'Kepmenakertrans 102/2004 and PP 35/2021: hourly = 1/173 of monthly wage; workday 1.5× first hour, 2× after; rest day (5-day week) 2× hours 1–8, 3× 9th, 4× 10th+', 'verified' => $verified],
        'thr' => ['note' => 'Permenaker 6/2016: 1 month wage after 12 months of service, pro rata from 1 month', 'verified' => $verified],
    ],
];
