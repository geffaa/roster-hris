<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $company_id
 * @property int $policy_document_id
 * @property int $position
 * @property ?string $heading
 * @property string $text
 * @property-read PolicyDocument $document
 */
class PolicyChunk extends Model
{
    use BelongsToCompany;

    protected $fillable = ['company_id', 'policy_document_id', 'position', 'heading', 'text'];

    /** @return BelongsTo<PolicyDocument, $this> */
    public function document(): BelongsTo
    {
        return $this->belongsTo(PolicyDocument::class, 'policy_document_id');
    }
}
