<?php

namespace App\Services\Assistant;

use App\Models\PolicyChunk;
use Illuminate\Support\Collection;

/** Full-text search over the current company's policy passages (company scope applies through the model). */
class PolicySearch
{
    /** Words that carry no meaning for matching, in English and Indonesian. */
    private const STOP = ['the', 'and', 'for', 'are', 'can', 'how', 'what', 'when', 'does', 'with', 'have', 'many', 'much', 'yang', 'dan', 'untuk', 'apa', 'berapa', 'bagaimana', 'saya', 'bisa', 'ada', 'itu', 'ini', 'dari', 'kapan', 'apakah', 'dengan'];

    /** @return Collection<int, PolicyChunk> */
    public function search(string $question, int $limit = 5): Collection
    {
        $words = collect(preg_split('/[^\p{L}\p{N}]+/u', mb_strtolower($question)) ?: [])
            ->filter(fn ($w) => mb_strlen($w) >= 3 && ! in_array($w, self::STOP, true))->unique()->values();
        if ($words->isEmpty()) {
            return collect();
        }
        $query = $words->map(fn ($w) => $w.':*')->implode(' | ');

        return PolicyChunk::with('document:id,title')
            ->whereRaw("tsv @@ to_tsquery('simple', ?)", [$query])
            ->orderByRaw("ts_rank(tsv, to_tsquery('simple', ?)) DESC", [$query])
            ->limit($limit)->get();
    }
}
