<?php

namespace App\Http\Middleware;

use App\Models\Company;
use App\Support\CurrentCompany;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolves the company the signed-in user is acting for, verifies membership,
 * and scopes Eloquent queries and role checks to it for the rest of the request.
 */
class SetCurrentCompany
{
    public function __construct(private CurrentCompany $current) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return $next($request);
        }

        $candidates = array_filter([
            $request->session()->get('company_id'),
            $user->current_company_id,
            $user->companies()->orderByDesc('company_user.is_default')->value('companies.id'),
        ]);

        $company = null;
        foreach ($candidates as $id) {
            if ($user->belongsToCompany((int) $id)) {
                $company = Company::find($id);
                break;
            }
        }

        abort_if($company === null, 403, 'Your account is not linked to any company yet.');

        $this->current->set($company);
        $request->session()->put('company_id', $company->id);

        if ($user->current_company_id !== $company->id) {
            $user->forceFill(['current_company_id' => $company->id])->saveQuietly();
        }

        // Roles are per company; drop anything loaded for another company.
        $user->unsetRelation('roles')->unsetRelation('permissions');

        return $next($request);
    }
}
