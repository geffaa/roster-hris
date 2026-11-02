<?php

namespace App\Support;

use App\Models\Company;

/**
 * Holds the company the current request acts on. Set by SetCurrentCompany middleware
 * after verifying membership; read by the BelongsToCompany global scope.
 */
final class CurrentCompany
{
    private ?Company $company = null;

    public function set(Company $company): void
    {
        $this->company = $company;
        setPermissionsTeamId($company->id);
    }

    public function get(): ?Company
    {
        return $this->company;
    }

    public function id(): ?int
    {
        return $this->company?->id;
    }

    public function forget(): void
    {
        $this->company = null;
        setPermissionsTeamId(null);
    }
}
