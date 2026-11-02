<?php

namespace App\Http\Controllers\People;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\Employee;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class OrgChartController extends Controller
{
    public function __invoke(Request $request): Response|RedirectResponse
    {
        $user = $request->user();

        if (! $user->can('viewAny', Employee::class)) {
            $self = $user->employee;
            abort_if($self === null, 403);

            return redirect()->route('employees.show', $self);
        }

        $people = Employee::query()
            ->where('status', '!=', 'resigned')
            ->with('department:id,name')
            ->orderBy('full_name')
            ->get(['id', 'full_name', 'job_title', 'manager_id', 'department_id', 'status']);

        $byManager = $people->groupBy(fn (Employee $e) => $e->manager_id ?? 0);
        $ids = $people->pluck('id')->flip();

        $build = function (Employee $e) use (&$build, $byManager): array {
            $children = ($byManager[$e->id] ?? collect())->map(fn (Employee $c) => $build($c))->values()->all();

            return [
                'id' => $e->id,
                'name' => $e->full_name,
                'title' => $e->job_title,
                'department' => $e->department?->name,
                'on_leave' => $e->status === 'on_leave',
                'size' => array_sum(array_map(fn ($c) => $c['size'] + 1, $children)),
                'children' => $children,
            ];
        };

        if ($user->can(Permission::EmployeesViewAll->value)) {
            // Roots: no manager, or a manager who has left (so nobody disappears from the chart).
            $roots = $people->filter(fn (Employee $e) => $e->manager_id === null || ! isset($ids[$e->manager_id]));
            $scope = 'company';
        } else {
            $roots = $people->where('id', $user->employee()->value('id'));
            $scope = 'team';
        }

        return Inertia::render('people/OrgChart', [
            'roots' => $roots->map(fn (Employee $e) => $build($e))->values(),
            'departments' => Department::query()->orderBy('name')->get(['id', 'name']),
            'total' => $scope === 'company' ? $people->count() : null,
            'scope' => $scope,
        ]);
    }
}
