<?php

namespace App\Enums;

enum IdentifierType: string
{
    case Nik = 'nik';
    case Npwp = 'npwp';
    case BpjsKesehatan = 'bpjs_kes';
    case BpjsKetenagakerjaan = 'bpjs_tk';
    case Ssn = 'ssn';
    case NationalInsurance = 'ni';
    case Tin = 'tin';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Nik => 'NIK (national ID)',
            self::Npwp => 'NPWP (tax ID)',
            self::BpjsKesehatan => 'BPJS Kesehatan',
            self::BpjsKetenagakerjaan => 'BPJS Ketenagakerjaan',
            self::Ssn => 'Social Security Number',
            self::NationalInsurance => 'National Insurance number',
            self::Tin => 'Tax ID',
            self::Other => 'Other ID',
        };
    }
}
