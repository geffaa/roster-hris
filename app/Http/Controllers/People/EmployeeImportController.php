<?php

namespace App\Http\Controllers\People;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Services\EmployeeImport;
use App\Support\CurrentCompany;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EmployeeImportController extends Controller
{
    public function __construct(private EmployeeImport $importer, private CurrentCompany $company) {}

    public function create(Request $request): Response
    {
        $this->authorize('create', Employee::class);

        return Inertia::render('people/EmployeeImport', ['preview' => null, 'columns' => EmployeeImport::COLUMNS]);
    }

    public function template(Request $request): StreamedResponse
    {
        $this->authorize('create', Employee::class);
        $example = ['NK-0101', 'Rahma Aulia', 'Customer Success Lead', '2026-11-02', 'rahma.aulia@example.id', 'Operations', 'NK-0020', 'Jakarta HQ', 'permanent', '18500000', 'TK/0'];

        return response()->streamDownload(function () use ($example) {
            $out = fopen('php://output', 'w');
            fputcsv($out, array_keys(EmployeeImport::COLUMNS));
            fputcsv($out, $example);
            fclose($out);
        }, 'roster-employee-import-template.csv', ['Content-Type' => 'text/csv']);
    }

    /** Parse and validate; nothing is written until the HR admin confirms. */
    public function preview(Request $request): RedirectResponse
    {
        $this->authorize('create', Employee::class);
        $request->validate(['file' => ['required', 'file', 'max:5120', 'extensions:csv,xlsx,txt']]);

        try {
            $rows = $this->importer->read($request->file('file'));
        } catch (RuntimeException $e) {
            return back()->withErrors(['file' => $e->getMessage()]);
        }

        $token = (string) Str::uuid();
        Cache::put($this->key($request, $token), [
            'company_id' => $this->company->id(),
            'filename' => $request->file('file')->getClientOriginalName(),
            'rows' => $this->importer->validate($rows, $this->company->get()),
        ], now()->addMinutes(30));

        return redirect()->route('employees.import.show', $token);
    }

    public function show(Request $request, string $token): Response|RedirectResponse
    {
        $this->authorize('create', Employee::class);
        $preview = $this->pending($request, $token);

        if (! $preview) {
            return redirect()->route('employees.import.create')->withErrors(['file' => 'That preview expired. Upload the file again.']);
        }

        return Inertia::render('people/EmployeeImport', [
            'preview' => [...$preview, 'token' => $token],
            'columns' => EmployeeImport::COLUMNS,
        ]);
    }

    public function store(Request $request, string $token): RedirectResponse
    {
        $this->authorize('create', Employee::class);
        $preview = $this->pending($request, $token);
        abort_if(! $preview, 404);

        try {
            $count = $this->importer->import($preview['rows'], $this->company->get(), $request->user());
        } catch (RuntimeException $e) {
            return back()->withErrors(['file' => $e->getMessage()]);
        }

        Cache::forget($this->key($request, $token));

        return redirect()->route('employees.index')->with('success', "{$count} employees imported. Each one is recorded in the audit log.");
    }

    /** A preview belongs to the user and company that created it. */
    private function pending(Request $request, string $token): ?array
    {
        $preview = Cache::get($this->key($request, $token));

        return $preview && $preview['company_id'] === $this->company->id() ? $preview : null;
    }

    private function key(Request $request, string $token): string
    {
        return 'employee-import:'.$request->user()->id.':'.$token;
    }
}
