<?php

use App\Enums\Role;
use App\Models\Company;
use App\Models\Document;
use App\Models\Employee;
use Database\Seeders\RoleSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use OwenIt\Auditing\Models\Audit;

beforeEach(function () {
    Storage::fake('local');
    $this->seed(RoleSeeder::class);
    $this->company = Company::factory()->create();
    $this->lead = Employee::factory()->for($this->company)->create();
    $this->employee = Employee::factory()->for($this->company)->create(['manager_id' => $this->lead->id]);
    $this->hr = memberOf($this->company, Role::HrAdmin);
});

function upload($test, $employee, array $extra = [])
{
    return $test->post(route('employees.documents.store', $employee), [
        'file' => UploadedFile::fake()->create('contract.pdf', 120, 'application/pdf'),
        'type' => 'contract',
        ...$extra,
    ]);
}

it('lets HR upload a document to private storage and logs it', function () {
    upload(signIn($this->hr), $this->employee, ['expires_at' => now()->addYear()->toDateString()])->assertRedirect();

    $doc = Document::first();
    expect($doc->name)->toBe('contract')
        ->and($doc->path)->toStartWith("companies/{$this->company->id}/employees/{$this->employee->id}/");
    Storage::disk('local')->assertExists($doc->path);
    expect(Audit::where('auditable_type', Document::class)->first()->company_id)->toBe($this->company->id);
});

it('rejects executables and past expiry dates', function () {
    signIn($this->hr)->post(route('employees.documents.store', $this->employee), [
        'file' => UploadedFile::fake()->create('run.exe', 10, 'application/x-msdownload'),
        'type' => 'contract',
        'expires_at' => '2020-01-01',
    ])->assertSessionHasErrors(['file', 'expires_at']);
});

it('lets the employee download their own document but not their manager', function () {
    upload(signIn($this->hr), $this->employee);
    $doc = Document::first();

    $self = memberOf($this->company, Role::Employee, $this->employee);
    signIn($self)->get(route('employees.documents.show', [$this->employee, $doc]))->assertOk();

    $manager = memberOf($this->company, Role::Manager, $this->lead);
    signIn($manager)->get(route('employees.documents.show', [$this->employee, $doc]))->assertForbidden();
    signIn($self)->post(route('employees.documents.store', $this->employee), [])->assertForbidden();
});

it('returns 404 for a document of another company', function () {
    $other = Employee::factory()->for(Company::factory())->create();
    $doc = $other->documents()->create(['type' => 'other', 'name' => 'x', 'path' => 'x.pdf']);

    signIn($this->hr)->get(route('employees.documents.show', [$other, $doc]))->assertNotFound();
    signIn($this->hr)->delete(route('employees.documents.destroy', [$this->employee, $doc]))->assertNotFound();
});

it('deletes the file when HR removes a document', function () {
    upload(signIn($this->hr), $this->employee);
    $doc = Document::first();

    $this->delete(route('employees.documents.destroy', [$this->employee, $doc]))->assertRedirect();
    Storage::disk('local')->assertMissing($doc->path);
    expect(Document::count())->toBe(0);
});
