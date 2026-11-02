<?php

namespace App\Http\Controllers\Pay;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Models\PayrollRun;
use App\Models\RulePack;
use App\Models\RulePackVersion;
use App\Models\User;
use App\Services\Payroll\RulePacks;
use App\Support\CurrentCompany;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** S12 — the company's rule pack: dated versions, a draft editor with sources, and approval by a second person. */
class RulePackController extends Controller
{
    public function __construct(private RulePacks $packs, private CurrentCompany $company) {}

    public function show(Request $request, ?RulePackVersion $version = null): Response
    {
        abort_unless($request->user()->can(Permission::PayrollView->value) || $request->user()->can(Permission::RulePacksManage->value), 403);
        $pack = $this->pack();
        $versions = RulePackVersion::where('rule_pack_id', $pack->id)->where('status', '!=', 'discarded')->orderBy('effective_from')->orderBy('id')->get();
        $today = now($this->company->get()->timezone)->toDateString();
        $active = $versions->filter(fn ($v) => $v->status === 'approved' && $v->effective_from->toDateString() <= $today)->last();
        $version ??= $versions->first(fn ($v) => $v->isEditable() || $v->status === 'pending') ?? $active ?? $versions->last();
        abort_unless($version && $version->rule_pack_id === $pack->id, 404);
        $version->loadMissing('basedOn');
        $runsBy = PayrollRun::whereNotNull('rule_pack_version_id')->selectRaw('rule_pack_version_id, count(*) as n')->groupBy('rule_pack_version_id')->pluck('n', 'rule_pack_version_id');
        $names = User::whereIn('id', $versions->pluck('created_by')->merge($versions->pluck('approved_by'))->filter())->pluck('name', 'id');
        $user = $request->user();
        $diff = $version->based_on_id ? $this->packs->diff($version) : [];

        return Inertia::render('pay/RulePack', [
            'pack' => ['id' => $pack->id, 'code' => $pack->code, 'name' => $pack->name, 'method' => $pack->method, 'country' => $pack->country_code],
            'versions' => $versions->values()->map(function (RulePackVersion $v, int $i) use ($versions, $active, $runsBy, $today) {
                $next = $versions->slice($i + 1)->first(fn ($n) => $n->status === 'approved');

                return [
                    'id' => $v->id, 'version' => $v->version, 'status' => $v->status, 'effective_from' => $v->effective_from->toDateString(),
                    'until' => $v->status === 'approved' && $next ? $next->effective_from->copy()->subDay()->toDateString() : null,
                    'state' => match (true) {
                        $v->status !== 'approved' => $v->status,
                        $active?->id === $v->id => 'active',
                        $v->effective_from->toDateString() > $today => 'scheduled',
                        default => 'superseded',
                    },
                    'runs' => $runsBy[$v->id] ?? 0,
                ];
            }),
            'version' => [
                'id' => $version->id, 'version' => $version->version, 'status' => $version->status, 'effective_from' => $version->effective_from->toDateString(),
                'config' => $version->config, 'sources' => $version->sources, 'change_note' => $version->change_note, 'rejected_reason' => $version->rejected_reason,
                'based_on' => $version->basedOn?->version, 'base_config' => $version->basedOn?->config, 'author' => $names[$version->created_by] ?? null, 'author_id' => $version->created_by,
                'approver' => $names[$version->approved_by] ?? null, 'submitted_at' => $version->submitted_at?->toIso8601String(),
                'approved_at' => $version->approved_at?->toIso8601String(), 'created_at' => $version->created_at?->toDateString(),
                'editable' => $version->isEditable(),
            ],
            'diff' => $diff,
            'unsourced' => $this->packs->unsourced($version, $diff),
            'active' => $active?->id,
            'can' => [
                'manage' => $user->can(Permission::RulePacksManage->value),
                'approve' => $user->can(Permission::RulePacksApprove->value) && $version->status === 'pending' && $version->created_by !== $user->id,
            ],
            'today' => $today,
        ]);
    }

    public function draft(Request $request, RulePackVersion $version): RedirectResponse
    {
        $this->allow($request, Permission::RulePacksManage, $version);
        abort_unless($version->status === 'approved', 422, 'Start a draft from an approved version.');
        $open = RulePackVersion::where('rule_pack_id', $version->rule_pack_id)->whereIn('status', ['draft', 'pending', 'rejected'])->first();
        if ($open) {
            return redirect()->route('rule-packs.show', $open)->with('success', "{$open->version} is already open; finish or discard it first.");
        }
        $draft = $this->packs->createDraft($version->load('pack'), $request->user());

        return redirect()->route('rule-packs.show', $draft)->with('success', "Draft {$draft->version} started from {$version->version}.");
    }

    public function update(Request $request, RulePackVersion $version): RedirectResponse
    {
        $this->allow($request, Permission::RulePacksManage, $version);
        $data = $request->validate([
            'effective_from' => ['required', 'date_format:Y-m-d'],
            'config' => ['required', 'array'],
            'sources' => ['array'],
            'sources.*.note' => ['required', 'string', 'max:500'],
            'sources.*.verified' => ['required', 'date_format:Y-m-d'],
            'change_note' => ['nullable', 'string', 'max:1000'],
        ]);
        $this->packs->update($version, $data['effective_from'], $data['config'], $data['sources'] ?? [], $data['change_note'] ?? null);

        return back()->with('success', 'Draft saved.');
    }

    public function submit(Request $request, RulePackVersion $version): RedirectResponse
    {
        $this->allow($request, Permission::RulePacksManage, $version);
        $this->packs->submit($version);

        return back()->with('success', "{$version->version} sent for approval. Someone other than you must approve it.");
    }

    public function approve(Request $request, RulePackVersion $version): RedirectResponse
    {
        $this->allow($request, Permission::RulePacksApprove, $version);
        $this->packs->approve($version, $request->user());

        return back()->with('success', "{$version->version} approved. Payroll from ".$version->effective_from->format('j M Y').' will use it.');
    }

    public function reject(Request $request, RulePackVersion $version): RedirectResponse
    {
        $this->allow($request, Permission::RulePacksApprove, $version);
        $data = $request->validate(['reason' => ['required', 'string', 'max:500']]);
        $this->packs->reject($version, $request->user(), $data['reason']);

        return back()->with('success', "{$version->version} sent back to the author.");
    }

    public function discard(Request $request, RulePackVersion $version): RedirectResponse
    {
        $this->allow($request, Permission::RulePacksManage, $version);
        $this->packs->discard($version);

        return redirect()->route('rule-packs.show')->with('success', "{$version->version} discarded. It stays in the history.");
    }

    private function pack(): RulePack
    {
        $id = $this->company->get()->rule_pack_id;
        abort_unless($id !== null, 404);

        return RulePack::findOrFail($id);
    }

    private function allow(Request $request, Permission $permission, RulePackVersion $version): void
    {
        abort_unless($request->user()->can($permission->value), 403);
        abort_unless($version->rule_pack_id === $this->pack()->id, 404);
    }
}
