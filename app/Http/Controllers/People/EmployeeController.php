<?php

namespace App\Http\Controllers\People;

use App\Enums\IdentifierType;
use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Models\Compensation;
use App\Models\Department;
use App\Models\Document;
use App\Models\Employee;
use App\Models\EmployeeIdentifier;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use OwenIt\Auditing\Models\Audit;

class EmployeeController extends Controller
{
    public function index(Request $request): Response|RedirectResponse
    {
        $user = $request->user();

        if (! $user->can('viewAny', Employee::class)) {
            $self = $user->employee;
            abort_if($self === null, 403);

            return redirect()->route('employees.show', $self);
        }

        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'department' => ['nullable', 'integer'],
            'status' => ['nullable', 'in:active,on_leave,resigned'],
        ]);

        $query = Employee::query()
            ->with(['department:id,name', 'location:id,name', 'manager:id,full_name'])
            ->when(! $user->can(Permission::EmployeesViewAll->value), fn ($q) => $q->where('manager_id', $user->employee()->value('id') ?? 0))
            ->when($filters['q'] ?? null, function ($q, $term) {
                $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $term).'%';
                $q->where(fn ($w) => $w->where('full_name', 'ilike', $like)->orWhere('employee_no', 'ilike', $like)->orWhere('job_title', 'ilike', $like));
            })
            ->when($filters['department'] ?? null, fn ($q, $id) => $q->where('department_id', $id))
            ->when($filters['status'] ?? null, fn ($q, $status) => $q->where('status', $status), fn ($q) => $q->where('status', '!=', 'resigned'))
            ->orderBy('full_name');

        $employees = $query->paginate(25)->withQueryString()->through(fn (Employee $e) => [
            'id' => $e->id,
            'employee_no' => $e->employee_no,
            'full_name' => $e->full_name,
            'initials' => $e->initials(),
            'job_title' => $e->job_title,
            'department' => $e->department?->name,
            'location' => $e->location?->name,
            'manager' => $e->manager?->full_name,
            'status' => $e->status,
            'hire_date' => $e->hire_date->toDateString(),
        ]);

        return Inertia::render('people/EmployeesIndex', [
            'employees' => $employees,
            'filters' => $filters,
            'departments' => Department::query()->orderBy('name')->get(['id', 'name']),
            'counts' => [
                'active' => Employee::where('status', 'active')->count(),
                'on_leave' => Employee::where('status', 'on_leave')->count(),
            ],
            'scope' => $user->can(Permission::EmployeesViewAll->value) ? 'company' : 'team',
            'canImport' => $user->can('create', Employee::class),
        ]);
    }

    public function show(Request $request, Employee $employee): Response
    {
        $this->authorize('view', $employee);
        $user = $request->user();

        $employee->load(['department:id,name', 'location:id,name', 'manager:id,full_name,job_title', 'identifiers', 'contracts', 'reports:id,full_name,job_title,manager_id']);

        $canCompensation = $user->can('viewCompensation', $employee);
        $canActivity = $user->can('viewActivity', $employee);
        $company = $employee->company;

        return Inertia::render('people/EmployeeShow', [
            'employee' => [
                'id' => $employee->id,
                'employee_no' => $employee->employee_no,
                'full_name' => $employee->full_name,
                'initials' => $employee->initials(),
                'job_title' => $employee->job_title,
                'work_email' => $employee->work_email,
                'status' => $employee->status,
                'hire_date' => $employee->hire_date->toDateString(),
                'department' => $employee->department?->name,
                'location' => $employee->location?->name,
                'manager' => $employee->manager ? ['id' => $employee->manager->id, 'name' => $employee->manager->full_name, 'title' => $employee->manager->job_title] : null,
                'reports' => $employee->reports->map(fn ($r) => ['id' => $r->id, 'name' => $r->full_name, 'title' => $r->job_title])->values(),
                'contract' => $employee->contracts->first()?->only(['type', 'start_date', 'end_date']),
            ],
            'identifiers' => $employee->identifiers->sortBy(fn (EmployeeIdentifier $i) => array_search($i->type, IdentifierType::cases(), true))->map(fn (EmployeeIdentifier $i) => [
                'id' => $i->id,
                'type' => $i->type->value,
                'label' => $i->type->label(),
                'masked' => $i->masked(),
            ])->values(),
            'compensation' => $canCompensation ? $employee->compensations()->with('author:id,name')->get()->map(fn ($c) => [
                'id' => $c->id,
                'effective_from' => $c->effective_from->toDateString(),
                'base_salary' => $c->base_salary,
                'currency' => $c->currency,
                'tax_status' => $c->tax_status,
                'reason' => $c->reason,
                'author' => $c->author?->name,
                'is_current' => false,
            ])->pipe(function ($rows) {
                $today = now()->toDateString();
                $currentIndex = $rows->search(fn ($r) => $r['effective_from'] <= $today);

                return $rows->map(fn ($r, $i) => [...$r, 'is_current' => $i === $currentIndex])->values();
            }) : null,
            'activity' => $canActivity ? $this->activityFor($employee) : null,
            'documents' => $user->can('viewDocuments', $employee) ? $employee->documents()->latest()->get()->map(fn (Document $d) => [
                'id' => $d->id,
                'name' => $d->name,
                'type' => DocumentController::TYPES[$d->type] ?? $d->type,
                'extension' => strtoupper(pathinfo($d->path, PATHINFO_EXTENSION)),
                'uploaded_at' => $d->created_at->toDateString(),
                'expires_at' => $d->expires_at?->toDateString(),
            ])->values() : null,
            'documentTypes' => collect(DocumentController::TYPES)->map(fn ($label, $value) => ['value' => $value, 'label' => $label])->values(),
            'can' => [
                'reveal' => $user->can('revealIdentifiers', $employee),
                'manageCompensation' => $user->can('manageCompensation', $employee),
                'manageDocuments' => $user->can('manageDocuments', $employee),
            ],
            'currency' => $company->base_currency,
        ]);
    }

    /** Recent audit events for the employee and their identifiers and compensation rows. */
    private function activityFor(Employee $employee): array
    {
        $identifierIds = $employee->identifiers->pluck('id');
        $compensationIds = $employee->compensations()->pluck('id');
        $documentIds = $employee->documents()->pluck('id');

        $audits = Audit::query()
            ->where(function ($q) use ($employee, $identifierIds, $compensationIds, $documentIds) {
                $q->where(fn ($w) => $w->where('auditable_type', Employee::class)->where('auditable_id', $employee->id))
                    ->orWhere(fn ($w) => $w->where('auditable_type', EmployeeIdentifier::class)->whereIn('auditable_id', $identifierIds))
                    ->orWhere(fn ($w) => $w->where('auditable_type', Compensation::class)->whereIn('auditable_id', $compensationIds))
                    ->orWhere(fn ($w) => $w->where('auditable_type', Document::class)->whereIn('auditable_id', $documentIds));
            })
            ->latest('id')
            ->limit(15)
            ->get();

        $names = User::query()->whereIn('id', $audits->pluck('user_id')->filter())->pluck('name', 'id');

        return $audits
            ->map(fn (Audit $a) => [
                'id' => $a->id,
                'event' => $a->event,
                'subject' => class_basename($a->auditable_type),
                'user' => $names[$a->getAttribute('user_id')] ?? 'System',
                'at' => $a->created_at->toIso8601String(),
                'changes' => $a->event === 'revealed'
                    ? [IdentifierType::tryFrom($a->new_values['type'] ?? '')?->label() ?? 'ID number']
                    : collect($a->new_values ?? [])->except(['created_at', 'updated_at', 'id', 'employee_id', 'company_id', 'created_by', 'currency', 'pay_frequency', 'path'])->keys()->values(),
                'tags' => $a->tags,
            ])
            ->all();
    }
}
