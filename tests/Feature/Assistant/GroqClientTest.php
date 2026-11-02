<?php

use App\Services\Assistant\GroqClient;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

it('turns [n] markers into sources and drops numbers that do not exist', function () {
    expect(GroqClient::segments('Annual leave is 12 days [2]. Your manager approves it [3][1]. See also [9].', 3))->toBe([
        ['text' => 'Annual leave is 12 days.', 'cites' => [1]],
        ['text' => ' Your manager approves it.', 'cites' => [2, 0]],
        ['text' => ' See also.', 'cites' => []],
    ]);
});

it('calls Groq\'s chat completions with numbered documents and reads the answer', function () {
    config(['services.groq.key' => 'gsk_test', 'services.groq.model' => 'openai/gpt-oss-120b']);
    Http::fake(['api.groq.com/*' => Http::response([
        'model' => 'openai/gpt-oss-120b',
        'choices' => [['message' => ['role' => 'assistant', 'content' => 'You have 9 days left [1]. Leave needs your manager\'s approval [2].'], 'finish_reason' => 'stop']],
        'usage' => ['prompt_tokens' => 812, 'completion_tokens' => 31],
    ])]);

    $r = app(GroqClient::class)->ask('Answer from the documents.', [['title' => 'Your HR data', 'text' => 'Annual leave: 9 days'], ['title' => 'Leave policy', 'text' => 'Managers approve leave.']], 'How many days?');

    expect($r['segments'][0]['cites'])->toBe([0])->and($r['segments'][1]['cites'])->toBe([1])->and($r['output_tokens'])->toBe(31);
    Http::assertSent(fn (Request $req) => $req->url() === GroqClient::ENDPOINT
        && $req->hasHeader('Authorization', 'Bearer gsk_test')
        && $req['model'] === 'openai/gpt-oss-120b'
        && str_contains($req['messages'][1]['content'], "[2] Leave policy\nManagers approve leave."));
});

it('reports a failed request so the assistant can fall back', function () {
    config(['services.groq.key' => 'gsk_test']);
    Http::fake(['api.groq.com/*' => Http::response(['error' => ['message' => 'rate limited']], 429)]);

    app(GroqClient::class)->ask('x', [], 'y');
})->throws(RuntimeException::class, 'HTTP 429');
