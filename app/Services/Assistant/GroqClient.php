<?php

namespace App\Services\Assistant;

use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * The one place Roster talks to an LLM: Groq's OpenAI-compatible chat completions API.
 * Documents are numbered [1], [2], …; the model cites them with those markers and we turn them back into sources.
 * Returns plain arrays so callers and tests never depend on the provider.
 */
class GroqClient
{
    public const ENDPOINT = 'https://api.groq.com/openai/v1/chat/completions';

    public function enabled(): bool
    {
        return filled(config('services.groq.key'));
    }

    public function model(): string
    {
        return (string) config('services.groq.model', 'openai/gpt-oss-120b');
    }

    /**
     * @param  list<array{title: string, text: string}>  $documents
     * @return array{text: string, segments: list<array{text: string, cites: list<int>}>, model: string, stop: ?string, input_tokens: ?int, output_tokens: ?int}
     */
    public function ask(string $system, array $documents, string $question, int $maxTokens = 2000): array
    {
        $numbered = collect($documents)->map(fn ($d, $i) => '['.($i + 1)."] {$d['title']}\n{$d['text']}")->implode("\n\n");
        $user = $documents === [] ? $question : "Documents:\n\n{$numbered}\n\nQuestion: {$question}";
        $cite = $documents === [] ? '' : "\n\nWhen you use a document, put its number in square brackets right after the sentence, like [2]. Only cite numbers that exist.";

        $response = Http::withToken((string) config('services.groq.key'))->acceptJson()->timeout(30)->retry(2, 500, throw: false)
            ->post(self::ENDPOINT, [
                'model' => $this->model(),
                'messages' => [['role' => 'system', 'content' => $system.$cite], ['role' => 'user', 'content' => $user]],
                'max_completion_tokens' => $maxTokens,
                'temperature' => 0.2,
            ]);
        if (! $response->successful()) {
            throw new RuntimeException('Groq request failed: HTTP '.$response->status());
        }
        $choice = $response->json('choices.0');
        $text = self::normalise((string) ($choice['message']['content'] ?? ''));

        return [
            'text' => $text,
            'segments' => self::segments($text, count($documents)),
            'model' => (string) $response->json('model', $this->model()),
            'stop' => $choice['finish_reason'] ?? null,
            'input_tokens' => $response->json('usage.prompt_tokens'),
            'output_tokens' => $response->json('usage.completion_tokens'),
        ];
    }

    /** Some models use narrow or non-breaking spaces and non-breaking hyphens; plain characters read and wrap better. */
    public static function normalise(string $text): string
    {
        $text = preg_replace('/[\x{00A0}\x{2007}\x{2009}\x{200A}\x{202F}]/u', ' ', $text) ?? $text;
        $text = preg_replace('/[\x{2010}\x{2011}]/u', '-', $text) ?? $text;

        return trim(preg_replace('/[ \t]{2,}/', ' ', $text) ?? $text);
    }

    /**
     * "Leave is 12 days [2]. Ask HR [3][1]." → segments with zero-based document indexes; unknown numbers are dropped.
     *
     * @return list<array{text: string, cites: list<int>}>
     */
    public static function segments(string $text, int $documents): array
    {
        if ($text === '') {
            return [];
        }
        $out = [];
        foreach (preg_split('/(?<=[.!?\]])\s+(?=\S)/u', $text) ?: [] as $sentence) {
            preg_match_all('/\[(\d{1,2})\]/', $sentence, $m);
            $cites = array_values(array_unique(array_filter(array_map(fn ($n) => (int) $n - 1, $m[1]), fn ($i) => $i >= 0 && $i < $documents)));
            $clean = trim(preg_replace('/\s*\[\d{1,2}\]/', '', $sentence) ?? $sentence);
            if ($clean !== '') {
                $out[] = ['text' => ($out === [] ? '' : ' ').$clean, 'cites' => $cites];
            }
        }

        return $out;
    }
}
