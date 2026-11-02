<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $company_id
 * @property string $title
 * @property string $body
 */
class PolicyDocument extends Model
{
    use BelongsToCompany;

    protected $fillable = ['company_id', 'title', 'body', 'uploaded_by'];

    /** @return HasMany<PolicyChunk, $this> */
    public function chunks(): HasMany
    {
        return $this->hasMany(PolicyChunk::class)->orderBy('position');
    }
}
