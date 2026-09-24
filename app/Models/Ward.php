<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\WardFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Ward extends Model
{
    /** @use HasFactory<WardFactory> */
    use HasFactory;

    protected $fillable = ['sub_county_id', 'name'];

    /**
     * @return BelongsTo<SubCounty, $this>
     */
    public function subCounty(): BelongsTo
    {
        return $this->belongsTo(SubCounty::class);
    }
}
