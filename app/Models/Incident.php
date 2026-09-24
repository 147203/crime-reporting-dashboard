<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\DuplicateStatus;
use App\Enums\IncidentStatus;
use Database\Factories\IncidentFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string|null $reference_no
 * @property int $category_id
 * @property Carbon $occurred_at
 * @property Carbon $reported_at
 * @property int $sub_county_id
 * @property int $ward_id
 * @property int $station_id
 * @property string|null $location_description
 * @property string|null $description
 * @property IncidentStatus $status
 * @property Carbon|null $closed_at
 * @property DuplicateStatus $duplicate_status
 * @property int|null $duplicate_of_id
 * @property bool $is_synthetic
 * @property int|null $import_batch_id
 * @property int|null $created_by
 * @property int|null $updated_by
 */
class Incident extends Model
{
    /** @use HasFactory<IncidentFactory> */
    use HasFactory, SoftDeletes;

    /**
     * Station, status, duplicate status, reference number, synthetic label and audit columns are set by services, never
     * mass-assigned from input.
     */
    protected $fillable = [
        'category_id',
        'occurred_at',
        'reported_at',
        'sub_county_id',
        'ward_id',
        'location_description',
        'description',
    ];

    protected function casts(): array
    {
        return [
            'occurred_at' => 'datetime',
            'reported_at' => 'datetime',
            'closed_at' => 'datetime',
            'status' => IncidentStatus::class,
            'duplicate_status' => DuplicateStatus::class,
            'is_synthetic' => 'boolean',
        ];
    }

    /**
     * Rows the given user may see: officers only their own station (no station, no rows); everyone else all rows.
     *
     * @param  Builder<self>  $query
     */
    public function scopeVisibleTo(Builder $query, User $user): void
    {
        if ($user->role->isStationScoped()) {
            $query->where($query->qualifyColumn('station_id'), $user->station_id ?? 0);
        }
    }

    /**
     * A countable incident is not soft-deleted and not a confirmed duplicate (docs/02 section 4).
     *
     * @param  Builder<self>  $query
     */
    public function scopeCountable(Builder $query): void
    {
        $query->whereNull($query->qualifyColumn('deleted_at'))->where($query->qualifyColumn('duplicate_status'), '!=', DuplicateStatus::Confirmed->value);
    }

    /**
     * Officers see a duplicate flag only when the earlier record is at their own station; otherwise the flag would reveal
     * that another station holds a record at that place and time.
     */
    public function duplicateFlagVisibleTo(User $user): bool
    {
        if (! $user->role->isStationScoped() || $this->duplicate_of_id === null) {
            return true;
        }

        return $this->duplicateOf()->withTrashed()->value('station_id') === $user->station_id;
    }

    /**
     * @return BelongsTo<CrimeCategory, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(CrimeCategory::class);
    }

    /**
     * @return BelongsTo<SubCounty, $this>
     */
    public function subCounty(): BelongsTo
    {
        return $this->belongsTo(SubCounty::class);
    }

    /**
     * @return BelongsTo<Ward, $this>
     */
    public function ward(): BelongsTo
    {
        return $this->belongsTo(Ward::class);
    }

    /**
     * @return BelongsTo<Station, $this>
     */
    public function station(): BelongsTo
    {
        return $this->belongsTo(Station::class);
    }

    /**
     * @return BelongsTo<self, $this>
     */
    public function duplicateOf(): BelongsTo
    {
        return $this->belongsTo(self::class, 'duplicate_of_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
