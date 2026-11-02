<?php

namespace App\Services\Assistant;

use App\Models\PolicyChunk;
use App\Models\PolicyDocument;
use Illuminate\Support\Facades\DB;

/** Splits a policy into passages by heading, then by paragraph, so answers can quote a precise section. */
class PolicyIndex
{
    public const MAX = 900;

    public function index(PolicyDocument $doc): int
    {
        return DB::transaction(function () use ($doc) {
            PolicyChunk::where('policy_document_id', $doc->id)->delete();
            $n = 0;
            foreach ($this->split($doc->body) as [$heading, $text]) {
                PolicyChunk::create(['company_id' => $doc->company_id, 'policy_document_id' => $doc->id, 'position' => $n++, 'heading' => $heading, 'text' => $text]);
            }

            return $n;
        });
    }

    /** @return list<array{0: ?string, 1: string}> */
    public function split(string $body): array
    {
        $out = [];
        $heading = null;
        $buffer = '';
        $flush = function () use (&$out, &$heading, &$buffer) {
            if (trim($buffer) !== '') {
                $out[] = [$heading, trim($buffer)];
            }
            $buffer = '';
        };
        foreach (preg_split('/\n{2,}/', str_replace("\r\n", "\n", $body)) ?: [] as $para) {
            $para = trim($para);
            if ($para === '') {
                continue;
            }
            if (preg_match('/^#{1,3}\s+(.+)$/m', $para, $m) && str_starts_with($para, '#')) {
                $flush();
                $heading = trim($m[1]);
                $para = trim(preg_replace('/^#{1,3}\s+.+$/m', '', $para, 1) ?? '');
                if ($para === '') {
                    continue;
                }
            }
            if (mb_strlen($buffer) + mb_strlen($para) > self::MAX) {
                $flush();
            }
            $buffer .= ($buffer === '' ? '' : "\n\n").$para;
        }
        $flush();

        return $out;
    }
}
