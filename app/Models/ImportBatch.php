<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ImportBatch extends Model
{
    public const STATUS_DRY_RUN = 'dry_run';

    public const STATUS_COMMITTED = 'committed';

    public const STATUS_DISCARDED = 'discarded';

    protected $fillable = ['user_id', 'file_name', 'stored_path', 'status', 'rows_total', 'rows_accepted', 'rows_rejected', 'rows_flagged_duplicate', 'error_report'];

    protected function casts(): array
    {
        return ['error_report' => 'array'];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
