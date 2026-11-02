<?php

namespace App\Http\Controllers\Insights;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Models\AttendanceRecord;
use App\Models\BankAccountChange;
use App\Models\Compensation;
use App\Models\Document;
use App\Models\Employee;
use App\Models\EmployeeComponent;
use App\Models\EmployeeIdentifier;
use App\Models\LeaveRequest;
use App\Models\OvertimeRequest;
use App\Models\PayrollRun;
use App\Models\RulePackVersion;
use App\Models\User;
use App\Support\CurrentCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;
use OwenIt\Auditing\Models\Audit;

/** S13 (audit part) — who changed what and when, for auditors and super admins. */
class AuditLogController extends Controller
{
    /** Area label per audited model; also the filter list. */
    private const AREAS = [
        Employee::class => 'Employee record',
        Compensation::class => 'Salary',
        EmployeeIdentifier::class => 'Identifier',
        Document::class => 'Document',
        EmployeeComponent::class => 'Pay component',
        BankAccountChange::class => 'Bank account',
        LeaveRequest::class => 'Leave',
        AttendanceRecord::class => 'Attendance',
        OvertimeRequest::class => 'Overtime',
        PayrollRun::class => 'Payroll run',
        RulePackVersion::class => 'Rule pack',
    ];

    /** Field values never shown, even masked, because they are encrypted at rest. */
    private const SECRET = ['personal_data', 'bank_account', 'account_number', 'value', 'two_factor_secret'];

    public function __invoke(Request $request, CurrentCompany $current): Response
    {
        abort_unless($request->user()->can(Permission::AuditView->value), 403);
        $company = $current->get();
        $f = $request->validate([
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
            'user' => ['nullable', 'integer'],
            'area' => ['nullable', 'in:'.implode(',', array_keys(self::AREAS))],
            'event' => ['nullable', 'string', 'max:40'],
        ]);

        $query = Audit::query()
            ->where(fn (Builder $q) => $q->where('company_id', $company->id)
                // Rule packs are shared templates: show changes to the pack this company uses.
                ->orWhere(fn ($q) => $q->where('auditable_type', RulePackVersion::class)
                    ->whereIn('auditable_id', RulePackVersion::where('rule_pack_id', $company->rule_pack_id)->select('id'))))
            ->when($f['from'] ?? null, fn ($q, $d) => $q->where('created_at', '>=', $d.' 00:00:00'))
            ->when($f['to'] ?? null, fn ($q, $d) => $q->where('created_at', '<=', $d.' 23:59:59'))
            ->when($f['user'] ?? null, fn ($q, $u) => $q->where('user_id', $u))
            ->when($f['area'] ?? null, fn ($q, $a) => $q->where('auditable_type', $a))
            ->when($f['event'] ?? null, fn ($q, $e) => $q->where('event', $e));

        $events = (clone $query)->reorder()->distinct()->pluck('event')->sort()->values();
        $page = $query->latest('id')->paginate(40)->withQueryString();
        $this->names = User::pluck('name', 'id')->all();
        /** @var Collection<int, Audit> $items */
        $items = collect($page->items());
        $labels = $this->labels($items);
        $people = User::whereIn('id', $items->pluck('user_id')->filter())->pluck('name', 'id');

        return Inertia::render('insights/AuditLog', [
            'entries' => $items->map(fn (Audit $a) => [
                'id' => $a->id,
                'at' => $a->created_at?->toIso8601String(),
                'user' => $people[$a->getAttribute('user_id')] ?? ($a->getAttribute('user_id') ? 'Removed user' : 'System'),
                'event' => $a->event,
                'area' => self::AREAS[$a->auditable_type] ?? class_basename($a->auditable_type),
                'record' => $labels[$a->auditable_type][$a->auditable_id] ?? '#'.$a->auditable_id,
                'link' => $this->link($a, $labels),
                'changes' => $this->changes($a),
                'tags' => $a->tags,
                'ip' => $a->getAttribute('ip_address'),
            ]),
            'pager' => ['current' => $page->currentPage(), 'last' => $page->lastPage(), 'total' => $page->total(), 'from' => $page->firstItem(), 'to' => $page->lastItem()],
            'filters' => $f + ['from' => null, 'to' => null, 'user' => null, 'area' => null, 'event' => null],
            'options' => [
                'areas' => collect(self::AREAS)->map(fn ($label, $value) => ['value' => $value, 'label' => $label])->values(),
                'users' => User::whereHas('companies', fn ($q) => $q->whereKey($company->id))->orderBy('name')->get(['id', 'name'])->map(fn ($u) => ['value' => $u->id, 'label' => $u->name]),
                'events' => $events->map(fn ($e) => ['value' => $e, 'label' => ucfirst(str_replace('_', ' ', $e))]),
            ],
        ]);
    }

    /** @var array<int, string> user names for *_by fields */
    private array $names = [];

    /** @return list<array{field: string, from: mixed, to: mixed}> */
    private function changes(Audit $a): array
    {
        $old = (array) $a->old_values;
        $new = (array) $a->new_values;
        $out = [];
        foreach (array_unique([...array_keys($old), ...array_keys($new)]) as $field) {
            // Internal keys say nothing to a reader; the record name is already in the heading.
            if (in_array($field, ['updated_at', 'created_at', 'id', 'company_id', 'employee_id', 'rule_pack_id', 'based_on_id', 'salary_component_id', 'payroll_run_id'], true)) {
                continue;
            }
            $secret = in_array($field, self::SECRET, true);
            $out[] = [
                'field' => preg_replace('/ (\d)$/', ' ($1)', str_replace('_', ' ', $field)),
                'from' => $secret ? (array_key_exists($field, $old) ? 'hidden' : null) : $this->readable($field, $old[$field] ?? null),
                'to' => $secret ? (array_key_exists($field, $new) ? 'hidden' : null) : $this->readable($field, $new[$field] ?? null),
            ];
        }

        return array_slice($out, 0, 8);
    }

    private function readable(string $field, mixed $v): mixed
    {
        if ($v === null || $v === '') {
            return null;
        }
        if (preg_match('/(^|_)by(_\d)?$/', $field) && is_numeric($v)) {
            return $this->names[(int) $v] ?? 'User #'.$v;
        }
        if (is_string($v) && preg_match('/^\d{4}-\d{2}-\d{2} 00:00:00$/', $v)) {
            return substr($v, 0, 10);
        }
        if (is_numeric($v) && in_array($field, ['base_salary', 'amount', 'gross', 'net'], true)) {
            return number_format((float) $v, fmod((float) $v, 1.0) ? 2 : 0);
        }

        return $this->short($v);
    }

    private function short(mixed $v): mixed
    {
        if (is_array($v)) {
            return 'changed';
        }

        return is_string($v) && mb_strlen($v) > 80 ? mb_substr($v, 0, 77).'…' : $v;
    }

    /**
     * @param  Collection<int, Audit>  $audits
     * @return array<string, array<int, string>>
     */
    private function labels(Collection $audits): array
    {
        $by = $audits->groupBy('auditable_type')->map(fn ($g) => $g->pluck('auditable_id')->unique()->values()->all());
        $name = fn (string $model, array $ids, \Closure $label, array $with = []) => $model::withoutGlobalScopes()->with($with)->whereIn('id', $ids)->get()->mapWithKeys(fn ($m) => [$m->id => $label($m)])->all();
        $emp = fn ($m) => $m->employee->full_name ?? 'Employee';
        $withEmp = ['employee' => fn ($q) => $q->withoutGlobalScopes()->select('id', 'full_name')];

        $out = [];
        foreach ($by as $type => $ids) {
            $out[$type] = match ($type) {
                Employee::class => $name($type, $ids, fn ($m) => $m->full_name),
                Compensation::class, EmployeeIdentifier::class, Document::class, EmployeeComponent::class, BankAccountChange::class,
                LeaveRequest::class, AttendanceRecord::class, OvertimeRequest::class => $name($type, $ids, $emp, $withEmp),
                PayrollRun::class => $name($type, $ids, fn ($m) => $m->reference),
                RulePackVersion::class => $name($type, $ids, fn ($m) => $m->version),
                default => [],
            };
        }

        return $out;
    }

    /** @param array<string, array<int, string>> $labels */
    private function link(Audit $a, array $labels): ?string
    {
        return match ($a->auditable_type) {
            Employee::class => route('employees.show', $a->auditable_id),
            PayrollRun::class => route('payroll.show', $a->auditable_id),
            RulePackVersion::class => route('rule-packs.show', $a->auditable_id),
            default => null,
        };
    }
}
