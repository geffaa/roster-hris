<?php

namespace App\Http\Controllers\People;

use App\Http\Controllers\Controller;
use App\Models\Document;
use App\Models\Employee;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Employee documents live on the private disk and are only served through this controller. */
class DocumentController extends Controller
{
    public const TYPES = ['contract' => 'Employment contract', 'id_card' => 'ID card', 'tax' => 'Tax document', 'certificate' => 'Certificate', 'other' => 'Other'];

    public function store(Request $request, Employee $employee): RedirectResponse
    {
        $this->authorize('manageDocuments', $employee);

        $data = $request->validate([
            'file' => ['required', 'file', 'max:10240', 'mimes:pdf,jpg,jpeg,png'],
            'type' => ['required', 'in:'.implode(',', array_keys(self::TYPES))],
            'name' => ['nullable', 'string', 'max:120'],
            'expires_at' => ['nullable', 'date', 'after:today'],
        ]);

        $file = $request->file('file');
        $path = $file->storeAs(
            "companies/{$employee->company_id}/employees/{$employee->id}",
            Str::uuid().'.'.strtolower($file->getClientOriginalExtension()),
            'local',
        );

        $employee->documents()->create([
            'type' => $data['type'],
            'name' => ($data['name'] ?? null) ?: pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME),
            'path' => $path,
            'expires_at' => $data['expires_at'] ?? null,
        ]);

        return back()->with('success', 'Document uploaded.');
    }

    public function show(Employee $employee, Document $document): StreamedResponse
    {
        abort_unless($document->employee_id === $employee->id, 404);
        $this->authorize('viewDocuments', $employee);

        return Storage::disk('local')->download($document->path, Str::slug($document->name).'.'.pathinfo($document->path, PATHINFO_EXTENSION));
    }

    public function destroy(Employee $employee, Document $document): RedirectResponse
    {
        abort_unless($document->employee_id === $employee->id, 404);
        $this->authorize('manageDocuments', $employee);

        Storage::disk('local')->delete($document->path);
        $document->delete();

        return back()->with('success', 'Document removed.');
    }
}
