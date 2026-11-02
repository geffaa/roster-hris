<?php

use App\Enums\Role;
use App\Models\AssistantMessage;
use App\Models\Company;
use App\Models\Employee;
use App\Models\PolicyDocument;
use App\Services\Assistant\ClaudeClient;
use App\Services\Assistant\PolicyIndex;
use App\Services\Assistant\PolicySearch;
use App\Support\CurrentCompany;
use Illuminate\Support\Carbon;

beforeEach(function () {
    $this->t = timeSetup();
    app(CurrentCompany::class)->set($this->t['company']);
    foreach (['leave' => 'Leave policy', 'attendance' => 'Attendance and working hours', 'overtime' => 'Overtime', 'payroll' => 'Payroll and payslips'] as $f => $title) {
        app(PolicyIndex::class)->index(PolicyDocument::create(['company_id' => $this->t['company']->id, 'title' => $title, 'body' => file_get_contents(database_path("data/policies/{$f}.md"))]));
    }
    $this->me = Employee::factory()->for($this->t['company'])->create(['full_name' => 'Dimas Arya', 'hire_date' => '2023-06-05']);
    $this->colleague = Employee::factory()->for($this->t['company'])->create(['full_name' => 'Nadia Rahmawati', 'hire_date' => '2021-02-15']);
    $this->user = memberOf($this->t['company'], Role::Employee, $this->me);
    config(['services.anthropic.key' => null]);
    $this->travelTo(Carbon::parse('2026-11-16 10:00', 'Asia/Jakarta'));
});

afterEach(fn () => Carbon::setTestNow());

/** 20 questions with a known source: the right policy must be in the top three passages. */
dataset('known', [
    ['How many annual leave days do I get per year?', 'Leave policy'],
    ['Can annual leave be carried over to next year?', 'Leave policy'],
    ['Do I need a doctor note for sick leave?', 'Leave policy'],
    ['How long is maternity leave?', 'Leave policy'],
    ['How many days of marriage leave?', 'Leave policy'],
    ['Is unpaid leave deducted from salary?', 'Leave policy'],
    ['Can I cancel approved leave that already started?', 'Leave policy'],
    ['Who approves leave when my manager is away?', 'Leave policy'],
    ['What are the office working hours?', 'Attendance and working hours'],
    ['How far from the office can I clock in?', 'Attendance and working hours'],
    ['What is the grace period before I am late?', 'Attendance and working hours'],
    ['I forgot to clock out, what should I do?', 'Attendance and working hours'],
    ['Can I work remotely?', 'Attendance and working hours'],
    ['How is overtime on a rest day paid?', 'Overtime'],
    ['What is the hourly rate for overtime?', 'Overtime'],
    ['How do I request overtime?', 'Overtime'],
    ['When is salary paid each month?', 'Payroll and payslips'],
    ['How is THR calculated?', 'Payroll and payslips'],
    ['How do I change my bank account?', 'Payroll and payslips'],
    ['How much BPJS Kesehatan is deducted?', 'Payroll and payslips'],
]);

it('finds the right policy for known questions', function (string $question, string $title) {
    $top = app(PolicySearch::class)->search($question, 3)->map(fn ($c) => $c->document->title);
    expect($top)->toContain($title);
})->with('known');

it('answers from passages with sources when AI is off, and uses only the asker\'s own data', function () {
    $json = signIn($this->user)->postJson(route('me.ask.store'), ['question' => 'How many annual leave days do I have left?'])->assertOk()->json();

    expect($json['mode'])->toBe('extractive')->and($json['sources'][0]['kind'])->toBe('personal')
        ->and(collect($json['sources'])->pluck('title'))->toContain('Leave policy')
        ->and(json_encode($json))->toContain('Annual leave 2026')->not->toContain('Nadia');
    expect(AssistantMessage::where('user_id', $this->user->id)->count())->toBe(1);
});

it('says it does not know instead of guessing', function () {
    $json = signIn($this->user)->postJson(route('me.ask.store'), ['question' => 'What is the capital of France?'])->json();
    expect($json['mode'])->toBe('refused')->and($json['sources'])->toBe([]);
});

it('never retrieves another company\'s policies', function () {
    $other = Company::factory()->create();
    PolicyDocument::withoutGlobalScopes()->create(['company_id' => $other->id, 'title' => 'Secret bonus scheme', 'body' => 'Zebracorn bonus applies.']);
    app(PolicyIndex::class)->index(PolicyDocument::withoutGlobalScopes()->where('company_id', $other->id)->sole());

    expect(app(PolicySearch::class)->search('zebracorn bonus'))->toBeEmpty();
});

it('sends Claude only policy passages and the asker\'s own data, and keeps the citations', function () {
    config(['services.anthropic.key' => 'test-key']);
    $fake = new class extends ClaudeClient
    {
        /** @var list<array{title: string, text: string}> */
        public array $documents = [];

        public function ask(string $system, array $documents, string $question, int $maxTokens = 2000): array
        {
            $this->documents = $documents;

            return ['segments' => [['text' => 'You have 12 days left.', 'cites' => [0]], ['text' => ' Leave is approved by your manager.', 'cites' => [1]]], 'model' => 'claude-opus-5-5', 'stop' => 'end_turn', 'input_tokens' => 900, 'output_tokens' => 40];
        }
    };
    app()->instance(ClaudeClient::class, $fake);

    $json = signIn($this->user)->postJson(route('me.ask.store'), ['question' => 'How many leave days do I have and who approves them?'])->json();

    expect($json['mode'])->toBe('claude')->and($json['segments'][0]['cites'])->toBe([0])
        ->and($fake->documents[0]['title'])->toContain('only you can see')
        ->and(json_encode($fake->documents))->toContain('Dimas Arya')->not->toContain('Nadia Rahmawati');
    expect(AssistantMessage::sole()->output_tokens)->toBe(40);
});

it('falls back to passages when Claude fails', function () {
    config(['services.anthropic.key' => 'test-key']);
    app()->instance(ClaudeClient::class, new class extends ClaudeClient
    {
        public function ask(string $system, array $documents, string $question, int $maxTokens = 2000): array
        {
            throw new RuntimeException('network down');
        }
    });

    $json = signIn($this->user)->postJson(route('me.ask.store'), ['question' => 'How is overtime paid?'])->json();
    expect($json['mode'])->toBe('extractive')->and($json['note'])->toContain('unavailable');
});
