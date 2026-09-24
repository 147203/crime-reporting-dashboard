<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\SubCountyFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SubCounty extends Model
{
    /** @use HasFactory<SubCountyFactory> */
    use HasFactory;

    protected $fillable = ['name'];

    /**
     * @return HasMany<Ward, $this>
     */
    public function wards(): HasMany
    {
        return $this->hasMany(Ward::class);
    }

    /**
     * @return HasMany<Station, $this>
     */
    public function stations(): HasMany
    {
        return $this->hasMany(Station::class);
    }
}
