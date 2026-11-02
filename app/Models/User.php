<?php

namespace App\Models;

use App\Enums\Role;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Spatie\Permission\Traits\HasRoles;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property ?Carbon $email_verified_at
 * @property ?int $current_company_id
 * @property ?string $two_factor_secret
 * @property ?array<int, string> $two_factor_recovery_codes
 * @property ?Carbon $two_factor_confirmed_at
 * @property-read ?Employee $employee
 */
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, Notifiable;

    protected $fillable = ['name', 'email', 'password', 'current_company_id', 'last_login_at', 'locale'];

    protected $hidden = ['password', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'two_factor_secret' => 'encrypted',
            'two_factor_recovery_codes' => 'encrypted:array',
            'two_factor_confirmed_at' => 'datetime',
            'last_login_at' => 'datetime',
        ];
    }

    /** @return BelongsToMany<Company, $this> */
    public function companies(): BelongsToMany
    {
        return $this->belongsToMany(Company::class)->withPivot('is_default')->withTimestamps();
    }

    /** The employee record for this user in the current company (company-scoped). */
    /** @return HasOne<Employee, $this> */
    public function employee(): HasOne
    {
        return $this->hasOne(Employee::class);
    }

    public function belongsToCompany(int $companyId): bool
    {
        return $this->companies()->whereKey($companyId)->exists();
    }

    public function hasTwoFactorEnabled(): bool
    {
        return $this->two_factor_confirmed_at !== null && $this->two_factor_secret !== null;
    }

    /** True when any role held in the current company handles salaries or identifiers. */
    public function requiresTwoFactor(): bool
    {
        return $this->getRoleNames()->contains(fn (string $name) => Role::tryFrom($name)?->requiresTwoFactor() ?? false);
    }

    public function roleInCurrentCompany(): ?Role
    {
        $order = [Role::SuperAdmin, Role::HrAdmin, Role::PayrollOfficer, Role::Auditor, Role::Manager, Role::Employee];
        $names = $this->getRoleNames()->all();

        foreach ($order as $role) {
            if (in_array($role->value, $names, true)) {
                return $role;
            }
        }

        return null;
    }
}
