<?php

namespace App\Http\Controllers\Me;

use App\Http\Controllers\Controller;
use App\Models\AssistantMessage;
use App\Services\Assistant\Assistant;
use App\Support\CurrentCompany;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** Ask HR — the employee-facing assistant. Each person sees only their own questions. */
class AskController extends Controller
{
    public function __construct(private Assistant $assistant, private CurrentCompany $company) {}

    public function index(Request $request): Response
    {
        return Inertia::render('me/Ask', [
            'history' => AssistantMessage::where('user_id', $request->user()->id)->where('kind', 'question')->latest()->limit(10)->get()->reverse()->values()
                ->map(fn (AssistantMessage $m) => ['id' => $m->id, 'question' => $m->question, ...$m->answer]),
            'ai' => filled(config('services.groq.key')),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate(['question' => ['required', 'string', 'min:3', 'max:500']]);

        return response()->json($this->assistant->ask($request->user(), $this->company->get(), trim($data['question'])) + ['question' => trim($data['question'])]);
    }
}
