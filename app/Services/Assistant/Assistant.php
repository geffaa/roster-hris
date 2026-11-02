<?php

namespace App\Services\Assistant;

use App\Models\AssistantMessage;
use App\Models\Company;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Ask HR: answers from the company's policies and the person's own data, always with sources.
 * With Claude configured it writes a short cited answer; otherwise it shows the best matching passages.
 */
class Assistant
{
    public function __construct(private PolicySearch $search, private PersonalContext $personal, private ClaudeClient $claude) {}

    /** @return array{segments: list<array{text: string, cites: list<int>}>, sources: list<array<string, mixed>>, mode: string, note: ?string, id: int} */
    public function ask(User $user, Company $company, string $question): array
    {
        $employee = $user->employee;
        $chunks = $this->search->search($question);
        $sources = [];
        $documents = [];
        if ($employee) {
            $documents[] = ['title' => 'Your HR data (only you can see this)', 'text' => $this->personal->for($employee)];
            $sources[] = ['kind' => 'personal', 'title' => 'Your HR data', 'heading' => null, 'excerpt' => null];
        }
        foreach ($chunks as $c) {
            $documents[] = ['title' => $c->document->title.($c->heading ? " — {$c->heading}" : ''), 'text' => $c->text];
            $sources[] = ['kind' => 'policy', 'title' => $c->document->title, 'heading' => $c->heading, 'excerpt' => mb_strimwidth($c->text, 0, 280, '…')];
        }

        if ($chunks->isEmpty() && ! $this->personalQuestion($question)) {
            return $this->save($user, $company, $question, [
                'segments' => [['text' => __("I couldn't find this in the company's HR policies, so I won't guess. Ask HR directly, or rephrase with the policy topic (leave, attendance, overtime, payroll)."), 'cites' => []]],
                'sources' => [], 'mode' => 'refused', 'note' => null,
            ]);
        }

        if ($this->claude->enabled()) {
            try {
                $r = $this->claude->ask($this->system($company, $user), $documents, $question);
                if ($r['stop'] === 'refusal' || $r['segments'] === []) {
                    throw new \RuntimeException('No answer: '.($r['stop'] ?? 'empty'));
                }

                return $this->save($user, $company, $question, ['segments' => $r['segments'], 'sources' => $sources, 'mode' => 'claude', 'note' => null], $r);
            } catch (Throwable $e) {
                Log::warning('Assistant fell back to extractive mode', ['error' => $e->getMessage()]);

                return $this->save($user, $company, $question, $this->extractive($question, $documents, $sources, __('The AI assistant is unavailable right now, so these are the closest matching passages.')));
            }
        }

        return $this->save($user, $company, $question, $this->extractive($question, $documents, $sources, __('AI answers are not switched on for this demo, so these are the closest matching passages.')));
    }

    private function system(Company $company, User $user): string
    {
        $language = ($user->locale ?? 'en') === 'id' ? 'Bahasa Indonesia' : 'English';

        return <<<TXT
        You are Roster's HR assistant for {$company->name}. Answer the employee's question using only the documents in the message: passages from the company's HR policies and, when present, a document with this employee's own HR data.

        Cite the passages you rely on. If the documents don't answer the question, say that you can't find it in the company's policies and suggest asking HR; don't fill gaps with general knowledge about laws or other companies, and never estimate numbers that aren't in the documents. Answer for this employee only.

        Keep it short: two to five sentences in plain language, no headings. Reply in {$language}.
        TXT;
    }

    private function personalQuestion(string $q): bool
    {
        return (bool) preg_match('/\b(my|mine|i have|left|balance|payslip|salary|net pay|saya|sisa|saldo|slip|gaji|cuti saya)\b/iu', $q);
    }

    /**
     * @param  list<array{title: string, text: string}>  $documents
     * @param  list<array<string, mixed>>  $sources
     * @return array{segments: list<array{text: string, cites: list<int>}>, sources: list<array<string, mixed>>, mode: string, note: ?string}
     */
    private function extractive(string $question, array $documents, array $sources, string $note): array
    {
        $segments = [];
        foreach ($sources as $i => $s) {
            if ($s['kind'] === 'personal') {
                $facts = $this->relevantFacts($question, $documents[$i]['text']);
                if ($facts !== '') {
                    $segments[] = ['text' => $facts, 'cites' => [$i]];
                }
            } elseif (count(array_filter($segments, fn ($x) => $sources[$x['cites'][0]]['kind'] === 'policy')) < 2) {
                $segments[] = ['text' => mb_strimwidth($documents[$i]['text'], 0, 420, '…'), 'cites' => [$i]];
            }
        }

        return ['segments' => $segments, 'sources' => $sources, 'mode' => 'extractive', 'note' => $note];
    }

    /** The lines of the person's own data that match what they asked about. */
    private function relevantFacts(string $q, string $context): string
    {
        $topics = [
            '/leave|cuti|holiday|libur|days off/i' => '/leave/i',
            '/pay|salary|slip|gaji|net|tax|pajak/i' => '/payslip/i',
            '/shift|pattern|jam kerja|schedule/i' => '/pattern/i',
        ];
        $lines = [];
        foreach ($topics as $ask => $line) {
            if (preg_match($ask, $q)) {
                $lines = [...$lines, ...array_filter(explode("\n", $context), fn ($l) => preg_match($line, $l))];
            }
        }

        return implode("\n", array_unique($lines));
    }

    /**
     * @param  array{segments: list<array{text: string, cites: list<int>}>, sources: list<array<string, mixed>>, mode: string, note: ?string}  $answer
     * @param  array<string, mixed>  $run
     * @return array{segments: list<array{text: string, cites: list<int>}>, sources: list<array<string, mixed>>, mode: string, note: ?string, id: int}
     */
    private function save(User $user, Company $company, string $question, array $answer, array $run = []): array
    {
        $m = AssistantMessage::create([
            'company_id' => $company->id, 'user_id' => $user->id, 'question' => $question, 'answer' => $answer, 'mode' => $answer['mode'],
            'model' => $run['model'] ?? null, 'input_tokens' => $run['input_tokens'] ?? null, 'output_tokens' => $run['output_tokens'] ?? null,
        ]);

        return $answer + ['id' => $m->id];
    }
}
