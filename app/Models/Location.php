<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $company_id
 * @property string $name
 */
class Location extends Model
{
    use BelongsToCompany, HasFactory;

    protected $fillable = ['company_id', 'name', 'address', 'lat', 'lng', 'radius_m', 'allow_remote'];

    protected function casts(): array
    {
        return ['lat' => 'decimal:6', 'lng' => 'decimal:6', 'allow_remote' => 'boolean'];
    }
}
