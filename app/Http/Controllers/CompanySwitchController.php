<?php

namespace App\Http\Controllers;

use App\Models\Company;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class CompanySwitchController extends Controller
{
    public function __invoke(Request $request, Company $company): RedirectResponse
    {
        abort_unless($request->user()->belongsToCompany($company->id), 404);

        $request->session()->put('company_id', $company->id);
        $request->user()->forceFill(['current_company_id' => $company->id])->saveQuietly();

        return redirect()->route('dashboard');
    }
}
