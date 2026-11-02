<?php

namespace App\Http\Controllers\Settings;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Models\PolicyDocument;
use App\Services\Assistant\PolicyIndex;
use App\Support\CurrentCompany;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** HR maintains the policies the assistant answers from. */
class PolicyController extends Controller
{
    public function __construct(private PolicyIndex $index) {}

    public function index(Request $request): Response
    {
        $this->allow($request);

        return Inertia::render('settings/Policies', [
            'documents' => PolicyDocument::withCount('chunks')->orderBy('title')->get()->map(fn (PolicyDocument $d) => [
                'id' => $d->id, 'title' => $d->title, 'chunks' => $d->chunks_count, 'words' => str_word_count($d->body), 'updated_at' => $d->updated_at?->toIso8601String(),
            ]),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->allow($request);
        $data = $request->validate([
            'title' => ['required', 'string', 'max:120'],
            'body' => ['required_without:file', 'nullable', 'string', 'max:60000'],
            'file' => ['required_without:body', 'nullable', 'file', 'mimes:txt,md', 'max:512'],
        ], ['body.required_without' => 'Paste the policy text or upload a .txt or .md file.']);
        $body = $data['body'] ?? (string) file_get_contents($request->file('file')->getRealPath());
        $doc = PolicyDocument::create(['company_id' => app(CurrentCompany::class)->id(), 'title' => $data['title'], 'body' => $body, 'uploaded_by' => $request->user()->id]);
        $n = $this->index->index($doc);

        return back()->with('success', "Added {$doc->title}: {$n} passages the assistant can quote.");
    }

    public function destroy(Request $request, PolicyDocument $document): RedirectResponse
    {
        $this->allow($request);
        $document->delete();

        return back()->with('success', "Removed {$document->title}. The assistant no longer quotes it.");
    }

    private function allow(Request $request): void
    {
        abort_unless($request->user()->can(Permission::EmployeesManage->value), 403);
    }
}
