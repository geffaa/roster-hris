<?php

namespace App\Services\Payroll;

use App\Models\PayrollRun;
use App\Models\RulePack;
use App\Models\RulePackVersion;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * Dated, two-person-approved versions of a rule pack. The version used for a period is the latest approved one
 * whose effective date is on or before the period end, so changing rates never rewrites past payroll.
 */
class RulePacks
{
    /** Keys a person may change in the editor; the tax method and TER tables stay fixed in code and data. */
    public const EDITABLE = ['contributions', 'ptkp', 'position_cost', 'art17', 'overtime', 'thr', 'allowance_annual', 'bands_annual'];

    public function versionFor(RulePack $pack, CarbonInterface $date): RulePackVersion
    {
        return RulePackVersion::where('rule_pack_id', $pack->id)->where('status', 'approved')
            ->whereDate('effective_from', '<=', $date)->orderByDesc('effective_from')->first()
            ?? throw new RuntimeException("No approved {$pack->code} rule pack version in force on {$date->toDateString()}.");
    }

    public function createDraft(RulePackVersion $base, User $author): RulePackVersion
    {
        $year = now()->year + 1;
        $n = 1;
        while (RulePackVersion::where('rule_pack_id', $base->rule_pack_id)->where('version', "{$base->pack->code}-{$year}.{$n}")->exists()) {
            $n++;
        }

        return RulePackVersion::create([
            'rule_pack_id' => $base->rule_pack_id,
            'version' => "{$base->pack->code}-{$year}.{$n}",
            // The next month, but never before the version it copies (that would rewrite its months too).
            'effective_from' => now()->addMonthNoOverflow()->startOfMonth()->max($base->effective_from->copy()->addMonthNoOverflow()->startOfMonth()),
            'config' => $base->config,
            'sources' => $base->sources,
            'based_on_id' => $base->id,
            'created_by' => $author->id,
        ]);
    }

    /**
     * @param  array<string, mixed>  $config  only EDITABLE keys are taken
     * @param  array<string, array{note: string, verified: string}>  $sources
     */
    public function update(RulePackVersion $draft, string $effectiveFrom, array $config, array $sources, ?string $note): RulePackVersion
    {
        $this->assertEditable($draft);
        $merged = $draft->config;
        foreach (self::EDITABLE as $key) {
            if (array_key_exists($key, $config) && array_key_exists($key, $merged)) {
                $merged[$key] = $config[$key];
            }
        }
        $this->validateNumbers($merged);
        $this->validateDate($draft, $effectiveFrom);

        $draft->update([
            'effective_from' => $effectiveFrom,
            'config' => $merged,
            'sources' => array_replace($draft->sources, array_map(fn ($s) => ['note' => trim($s['note']), 'verified' => $s['verified']], $sources)),
            'change_note' => $note,
            'status' => 'draft',
            'rejected_reason' => null,
        ]);

        return $draft;
    }

    public function submit(RulePackVersion $draft): void
    {
        $this->assertEditable($draft);
        $changes = $this->diff($draft);
        $problems = [];
        if ($changes === []) {
            $problems['changes'] = 'Nothing has changed from the version this draft is based on.';
        }
        foreach ($this->unsourced($draft, $changes) as $key) {
            $problems["sources.{$key}"] = "Add the regulation or notice behind {$key} and the date you checked it.";
        }
        if (blank($draft->change_note)) {
            $problems['change_note'] = 'Say in one line why these numbers change.';
        }
        $this->validateDate($draft, $draft->effective_from->toDateString());
        if ($problems) {
            throw ValidationException::withMessages($problems);
        }
        $draft->update(['status' => 'pending', 'submitted_at' => now()]);
    }

    public function approve(RulePackVersion $version, User $approver): void
    {
        if ($version->status !== 'pending') {
            throw ValidationException::withMessages(['version' => 'Only a version waiting for approval can be approved.']);
        }
        if ($version->created_by === $approver->id) {
            throw ValidationException::withMessages(['version' => 'A second person must approve: you prepared this version.']);
        }
        $this->validateDate($version, $version->effective_from->toDateString());
        $version->update(['status' => 'approved', 'approved_by' => $approver->id, 'approved_at' => now()]);
    }

    public function reject(RulePackVersion $version, User $approver, string $reason): void
    {
        if ($version->status !== 'pending' || $version->created_by === $approver->id) {
            throw ValidationException::withMessages(['version' => 'Only someone other than the author can reject a version waiting for approval.']);
        }
        $version->update(['status' => 'rejected', 'rejected_reason' => $reason]);
    }

    public function discard(RulePackVersion $version): void
    {
        $this->assertEditable($version);
        $version->update(['status' => 'discarded']);
    }

    /** @return list<array{key: string, label: string, from: mixed, to: mixed}> */
    public function diff(RulePackVersion $version): array
    {
        $base = $version->loadMissing('basedOn')->basedOn->config ?? [];
        $out = [];
        foreach ($version->config['contributions'] ?? [] as $c) {
            $old = collect($base['contributions'] ?? [])->firstWhere('code', $c['code']) ?? [];
            foreach (['employer' => 'employer %', 'employee' => 'employee %', 'cap' => 'wage cap', 'exempt_below' => 'threshold'] as $field => $label) {
                if (($old[$field] ?? null) != ($c[$field] ?? null)) {
                    $out[] = ['key' => "contributions.{$c['code']}", 'label' => "{$c['name']} {$label}", 'from' => $old[$field] ?? null, 'to' => $c[$field] ?? null];
                }
            }
        }
        foreach (['ptkp' => 'PTKP', 'position_cost' => 'Biaya jabatan', 'overtime' => 'Overtime', 'thr' => 'THR'] as $key => $label) {
            foreach (Arr::dot($version->config[$key] ?? []) as $path => $value) {
                if (Arr::get($base[$key] ?? [], $path) != $value) {
                    $out[] = ['key' => $key, 'label' => "{$label} ".str_replace(['_', '.'], ' ', $path), 'from' => Arr::get($base[$key] ?? [], $path), 'to' => $value];
                }
            }
        }
        foreach (['art17' => 'Article 17 bands', 'bands_annual' => 'Tax bands', 'allowance_annual' => 'Tax-free allowance'] as $key => $label) {
            if (array_key_exists($key, $version->config) && ($base[$key] ?? null) != $version->config[$key]) {
                $out[] = ['key' => $key, 'label' => $label, 'from' => $base[$key] ?? null, 'to' => $version->config[$key]];
            }
        }

        return $out;
    }

    /**
     * A changed number needs its source updated too: a note and check date that differ from the version it is based on.
     *
     * @param  list<array{key: string}>  $changes
     * @return list<string>
     */
    public function unsourced(RulePackVersion $version, array $changes): array
    {
        $before = $version->loadMissing('basedOn')->basedOn->sources ?? [];

        return collect($changes)->pluck('key')->unique()->filter(function (string $key) use ($version, $before) {
            $s = $version->sources[$key] ?? null;

            return $s === null || blank($s['note']) || ($before[$key] ?? null) == $s;
        })->values()->all();
    }

    private function assertEditable(RulePackVersion $version): void
    {
        if (! $version->isEditable()) {
            throw ValidationException::withMessages(['version' => "Version {$version->version} is {$version->status} and can no longer be changed. Duplicate it instead."]);
        }
    }

    /** @param array<string, mixed> $config */
    private function validateNumbers(array $config): void
    {
        $errors = [];
        foreach ($config['contributions'] ?? [] as $i => $c) {
            foreach (['employer', 'employee'] as $f) {
                if (! is_numeric($c[$f] ?? null) || $c[$f] < 0 || $c[$f] > 100) {
                    $errors["config.contributions.{$i}.{$f}"] = "{$c['name']}: {$f} rate must be a percentage between 0 and 100.";
                }
            }
            if (($c['cap'] ?? null) !== null && (! is_numeric($c['cap']) || $c['cap'] <= 0)) {
                $errors["config.contributions.{$i}.cap"] = "{$c['name']}: the wage cap must be a positive amount, or empty for no cap.";
            }
        }
        foreach ($config['ptkp'] ?? [] as $k => $v) {
            if (! is_numeric($v) || $v < 0) {
                $errors["config.ptkp.{$k}"] = 'PTKP amounts must be zero or more.';
            }
        }
        foreach (['art17', 'bands_annual'] as $key) {
            $previous = 0;
            foreach ($config[$key] ?? [] as $i => [$upper, $rate]) {
                if (! is_numeric($rate) || $rate < 0 || $rate > 100 || ($upper !== null && $upper <= $previous)) {
                    $errors["config.{$key}.{$i}"] = 'Bands must rise and rates must be percentages.';
                }
                $previous = $upper ?? PHP_INT_MAX;
            }
        }
        if ($errors) {
            throw ValidationException::withMessages($errors);
        }
    }

    /** One version per start date, starting on the 1st, and never inside a period that is already locked. */
    private function validateDate(RulePackVersion $version, string $date): void
    {
        if (substr($date, -2) !== '01') {
            throw ValidationException::withMessages(['effective_from' => 'Versions start on the 1st of a month, so a payroll period never mixes two versions.']);
        }
        $clash = RulePackVersion::where('rule_pack_id', $version->rule_pack_id)->where('id', '!=', $version->id)
            ->whereIn('status', ['approved', 'pending'])->whereDate('effective_from', $date)->value('version');
        if ($clash) {
            throw ValidationException::withMessages(['effective_from' => "{$clash} already starts on this date."]);
        }
        $locked = PayrollRun::withoutGlobalScopes()->whereIn('status', ['locked', 'published'])
            ->whereHas('company', fn ($q) => $q->where('rule_pack_id', $version->rule_pack_id))
            ->max('period_end');
        if ($locked && $date <= substr((string) $locked, 0, 10)) {
            throw ValidationException::withMessages(['effective_from' => 'Payroll up to '.date('j M Y', (int) strtotime((string) $locked)).' is already locked. Start this version after that.']);
        }
    }
}
