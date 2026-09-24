<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\StationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Station extends Model
{
    /** @use HasFactory<StationFactory> */
    use HasFactory;

    protected $fillable = ['sub_county_id', 'name', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    /**
     * @return BelongsTo<SubCounty, $this>
     */
    public function subCounty(): BelongsTo
    {
        return $this->belongsTo(SubCounty::class);
    }
}
