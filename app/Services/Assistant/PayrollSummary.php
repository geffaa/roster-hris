<?php

namespace App\Services\Assistant;

use App\Models\AssistantMessage;
use App\Models\PayrollItem;
use App\Models\PayrollRun;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Throwable;

/** A short briefing on what changed in a run and what to check before approving. Facts come from the run itself. */
class PayrollSummary
{
    public function __construct(private GroqClient $llm) {}

    /** @return array{points: list<string>, mode: string, note: ?string} */
    public function summarize(PayrollRun $run, User $user): array
    {
        $facts = $this->facts($run);
        $result = ['points' => $this->plain($facts), 'mode' => 'rules', 'note' => __('Written from the run figures by rules; switch on AI answers for a written briefing.')];

        if ($this->llm->enabled()) {
            try {
                $r = $this->llm->ask(
                    'You brief a payroll officer before they approve a payroll run. Use only the figures in the document. Write 3 to 6 short bullet points, most important first: what changed against last month and why, who needs a check before approval, and anything that will block payment. No preamble. Start each bullet with "- ". Name people. Use the currency as given.',
                    [['title' => "{$run->reference} figures", 'text' => $facts['text']]],
                    'Brief me on this run.',
                    1200,
                );
                $text = trim(implode('', array_column($r['segments'], 'text')));
                $points = array_values(array_filter(array_map(fn ($l) => trim(ltrim(trim($l), '-•* ')), explode("\n", $text))));
                if ($points !== [] && $r['stop'] !== 'content_filter') {
                    $result = ['points' => $points, 'mode' => 'ai', 'note' => null];
                }
            } catch (Throwable $e) {
                Log::warning('Payroll summary fell back to rules', ['error' => $e->getMessage()]);
            }
        }

        AssistantMessage::create(['company_id' => $run->company_id, 'user_id' => $user->id, 'kind' => 'payroll_summary', 'question' => $run->reference, 'answer' => $result, 'mode' => $result['mode']]);

        return $result;
    }

    /** @return array{text: string, changes: array<int, array<string, mixed>>, flags: array<int, string>, totals: array<string, mixed>, previous: ?PayrollRun, joiners: array<int, mixed>, leavers: array<int, mixed>} */
    private function facts(PayrollRun $run): array
    {
        $items = PayrollItem::where('payroll_run_id', $run->id)->get();
        $previous = PayrollRun::whereIn('status', ['locked', 'published'])->whereDate('period_start', '<', $run->period_start)->orderByDesc('period_start')->first();
        $changes = $items->filter(fn ($i) => $i->previous_net !== null && abs((float) $i->net - (float) $i->previous_net) >= 1)
            ->map(fn ($i) => ['name' => $i->context['name'], 'from' => (float) $i->previous_net, 'to' => (float) $i->net, 'pct' => round(((float) $i->net - (float) $i->previous_net) / max(1, (float) $i->previous_net) * 100, 1), 'why' => implode(', ', $i->context['one_offs'] ?? []) ?: (($i->context['unpaid_days'] ?? 0) ? "{$i->context['unpaid_days']} unpaid days" : (($i->context['overtime_hours'] ?? 0) ? "{$i->context['overtime_hours']} h overtime" : ''))])
            ->sortByDesc(fn ($c) => abs($c['pct']))->values()->all();
        $flags = $items->flatMap(fn ($i) => collect($i->flags)->whereIn('level', ['error', 'warning', 'check'])->map(fn ($f) => "{$i->context['name']}: {$f['message']}".($f['level'] === 'check' && $i->reviewed_at ? ' (reviewed)' : '')))->values()->all();
        $joiners = $items->filter(fn ($i) => $i->context['joined'] ?? null)->map(fn ($i) => $i->context['name'])->values()->all();
        $leavers = $items->filter(fn ($i) => $i->context['leaving'] ?? null)->map(fn ($i) => $i->context['name'])->values()->all();
        $totals = $run->totals ?? [];
        $cur = $items->first()->currency ?? '';
        $fmt = fn ($v) => number_format((float) $v);

        $text = implode("\n", array_filter([
            "Run {$run->reference} for ".$run->period_start->format('F Y').", {$run->headcount} people, currency {$cur}.",
            'Totals: gross '.$fmt($totals['gross'] ?? 0).', tax '.$fmt($totals['tax'] ?? 0).', net '.$fmt($totals['net'] ?? 0).'.',
            $previous ? 'Previous month ('.$previous->period_start->format('F').'): net '.$fmt($previous->totals['net'] ?? 0).'.' : null,
            $changes ? "Net pay changes:\n".implode("\n", array_map(fn ($c) => "- {$c['name']}: {$fmt($c['from'])} to {$fmt($c['to'])} ({$c['pct']}%)".($c['why'] ? ", because of {$c['why']}" : ''), array_slice($changes, 0, 8))) : 'No net pay changes against last month.',
            $flags ? "Flags:\n- ".implode("\n- ", $flags) : 'No flags.',
            $joiners ? 'Joined this month: '.implode(', ', $joiners).'.' : null,
            $leavers ? 'Leaving this month (final pay with year-end tax recalculation): '.implode(', ', $leavers).'.' : null,
            'Approvals: '.$run->approvals().' of 2.',
        ]));

        return compact('text', 'changes', 'flags', 'totals', 'previous', 'joiners', 'leavers');
    }

    /**
     * @param  array{text: string, changes: array<int, array<string, mixed>>, flags: array<int, string>, totals: array<string, mixed>, previous: ?PayrollRun, joiners: array<int, mixed>, leavers: array<int, mixed>}  $f
     * @return list<string>
     */
    private function plain(array $f): array
    {
        $out = [];
        $prevNet = $f['previous']->totals['net'] ?? null;
        if ($prevNet !== null && isset($f['totals']['net'])) {
            $pct = round(((float) $f['totals']['net'] - (float) $prevNet) / max(1, (float) $prevNet) * 100, 1);
            $out[] = sprintf('Net pay is %s%% %s than last month, with %d %s changing.', abs($pct), $pct >= 0 ? 'higher' : 'lower', count($f['changes']), count($f['changes']) === 1 ? 'person' : 'people');
        }
        foreach (array_slice($f['changes'], 0, 3) as $c) {
            $out[] = sprintf('%s: %s%s%%%s.', $c['name'], $c['pct'] > 0 ? '+' : '', $c['pct'], $c['why'] ? " ({$c['why']})" : '');
        }
        foreach (array_slice($f['flags'], 0, 3) as $flag) {
            $out[] = $flag;
        }
        if ($f['leavers']) {
            $out[] = 'Final pay with year-end tax recalculation for '.implode(', ', $f['leavers']).'.';
        }

        return $out ?: ['No changes against last month and nothing flagged.'];
    }
}
