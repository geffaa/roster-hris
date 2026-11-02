<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $company_id
 * @property int $user_id
 * @property string $kind
 * @property string $question
 * @property array<string, mixed> $answer
 * @property string $mode
 * @property ?string $model
 */
class AssistantMessage extends Model
{
    use BelongsToCompany;

    protected $fillable = ['company_id', 'user_id', 'kind', 'question', 'answer', 'mode', 'model', 'input_tokens', 'output_tokens'];

    protected function casts(): array
    {
        return ['answer' => 'array'];
    }
}
