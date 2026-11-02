<?php

use App\Enums\Role;
use App\Models\Company;
use App\Models\Employee;
use App\Models\EmployeeIdentifier;
use App\Support\CurrentCompany;
use Database\Seeders\RoleSeeder;
use OwenIt\Auditing\Models\Audit;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    config(['audit.console' => true]);
    $this->company = Company::factory()->create();
    app(CurrentCompany::class)->set($this->company);
    $this->hr = memberOf($this->company, Role::HrAdmin);
    $this->auditor = memberOf($this->company, Role::Auditor);
});

it('shows who changed what, hiding encrypted values', function () {
    $this->actingAs($this->hr);
    $e = Employee::factory()->for($this->company)->create(['job_title' => 'Designer']);
    $e->update(['job_title' => 'Lead Designer', 'bank_name' => 'BCA', 'bank_account' => '5270011223']);

    signIn($this->auditor)->get(route('audit-log', ['area' => Employee::class, 'event' => 'updated']))
        ->assertInertia(fn ($p) => $p->component('insights/AuditLog')->has('entries', 1)
            ->where('entries.0.user', $this->hr->name)->where('entries.0.record', $e->full_name)
            ->where('entries.0.changes', fn ($c) => collect($c)->contains(fn ($x) => $x['field'] === 'job title' && $x['from'] === 'Designer' && $x['to'] === 'Lead Designer')
                && ! str_contains(json_encode($c), '5270011223')));
});

it('keeps other companies out and is only for auditors and super admins', function () {
    $other = Company::factory()->create();
    Audit::create(['company_id' => $other->id, 'event' => 'updated', 'auditable_type' => Employee::class, 'auditable_id' => 1, 'old_values' => [], 'new_values' => ['job_title' => 'Secret']]);
    Audit::create(['company_id' => $this->company->id, 'event' => 'revealed', 'auditable_type' => EmployeeIdentifier::class, 'auditable_id' => 1, 'old_values' => [], 'new_values' => ['type' => 'nik'], 'tags' => 'pii']);

    signIn($this->auditor)->get(route('audit-log'))->assertInertia(fn ($p) => $p->has('entries', 1)->where('entries.0.event', 'revealed')->where('entries.0.tags', 'pii'));
    signIn($this->hr)->get(route('audit-log'))->assertForbidden();
});
