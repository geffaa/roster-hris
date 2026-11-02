<?php

namespace App\Services\Assistant;

use Anthropic\Client;

/**
 * The one place Roster talks to Claude. Returns plain arrays so callers and tests never depend on SDK classes.
 * Server-side refusal fallbacks are on ("default" routing), so a declined request is retried on a suitable model.
 */
class ClaudeClient
{
    public const MODEL = 'claude-opus-5-5';

    public function enabled(): bool
    {
        return filled(config('services.anthropic.key'));
    }

    /**
     * @param  list<array{title: string, text: string}>  $documents
     * @return array{segments: list<array{text: string, cites: list<int>}>, model: string, stop: ?string, input_tokens: ?int, output_tokens: ?int}
     */
    public function ask(string $system, array $documents, string $question, int $maxTokens = 2000): array
    {
        $content = [];
        foreach ($documents as $d) {
            $content[] = ['type' => 'document', 'source' => ['type' => 'text', 'mediaType' => 'text/plain', 'data' => $d['text']], 'title' => $d['title'], 'citations' => ['enabled' => true]];
        }
        $content[] = ['type' => 'text', 'text' => $question];

        $message = $this->client()->beta->messages->create(
            model: self::MODEL,
            maxTokens: $maxTokens,
            system: $system,
            messages: [['role' => 'user', 'content' => $content]],
            outputConfig: ['effort' => 'low'],
            fallbacks: 'default',
            betas: ['server-side-fallback-2026-07-01'],
        );

        $segments = [];
        foreach ($message->content as $block) {
            if ($block->type !== 'text') {
                continue;
            }
            $cites = [];
            foreach ($block->citations ?? [] as $c) {
                if (isset($c->documentIndex)) {
                    $cites[] = (int) $c->documentIndex;
                }
            }
            $segments[] = ['text' => $block->text, 'cites' => array_values(array_unique($cites))];
        }

        return [
            'segments' => $segments,
            'model' => (string) $message->model,
            'stop' => $message->stopReason,
            'input_tokens' => $message->usage->inputTokens,
            'output_tokens' => $message->usage->outputTokens,
        ];
    }

    private function client(): Client
    {
        return new Client(apiKey: (string) config('services.anthropic.key'));
    }
}
