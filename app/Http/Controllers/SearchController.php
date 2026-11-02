<?php

namespace App\Http\Controllers;

use App\Enums\Permission;
use App\Models\Employee;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** ⌘K: people the signed-in person may see, by name, employee number or job title. */
class SearchController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $user = $request->user();
        $q = trim((string) $request->validate(['q' => ['nullable', 'string', 'max:60']])['q']);
        if (mb_strlen($q) < 2 || ! $user->can('viewAny', Employee::class)) {
            return response()->json(['people' => []]);
        }
        $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $q).'%';
        $people = Employee::query()->with('department:id,name')
            ->when(! $user->can(Permission::EmployeesViewAll->value), fn ($w) => $w->where('manager_id', $user->employee()->value('id') ?? 0))
            ->where(fn ($w) => $w->where('full_name', 'ilike', $like)->orWhere('employee_no', 'ilike', $like)->orWhere('job_title', 'ilike', $like))
            ->orderBy('full_name')->limit(8)->get(['id', 'full_name', 'employee_no', 'job_title', 'department_id', 'status']);

        return response()->json(['people' => $people->map(fn (Employee $e) => [
            'id' => $e->id, 'name' => $e->full_name, 'sub' => trim("{$e->employee_no} · {$e->job_title}".($e->department ? " · {$e->department->name}" : '')),
            'url' => route('employees.show', $e->id, false), 'resigned' => $e->status === 'resigned',
        ])]);
    }
}
