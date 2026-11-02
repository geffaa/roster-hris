<?php

namespace App\Http\Controllers;

use App\Enums\Permission;
use App\Models\Employee;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** Minimal M1 home: headcount and shortcuts. The full role-based home arrives in M4. */
class DashboardController extends Controller
{
    public function __invoke(Request $request): Response|RedirectResponse
    {
        $user = $request->user();

        // Employees without team or company access live in the phone app.
        if (! $user->can('viewAny', Employee::class) && $user->employee()->exists()) {
            return redirect()->route('me.today');
        }
        $seesCompany = $user->can(Permission::EmployeesViewAll->value);
        $me = $user->employee;

        return Inertia::render('Dashboard', [
            'stats' => $seesCompany ? [
                'headcount' => Employee::where('status', '!=', 'resigned')->count(),
                'on_leave' => Employee::where('status', 'on_leave')->count(),
                'joined_this_quarter' => Employee::whereDate('hire_date', '>=', now()->firstOfQuarter())->count(),
                'contracts_ending' => Employee::whereHas('contracts', fn ($q) => $q->whereBetween('end_date', [now(), now()->addDays(60)]))->count(),
            ] : null,
            'team' => ! $seesCompany && $me ? Employee::where('manager_id', $me->id)->count() : null,
            'me' => $me ? ['id' => $me->id, 'job_title' => $me->job_title] : null,
        ]);
    }
}
