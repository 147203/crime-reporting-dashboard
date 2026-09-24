<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int|null $user_id
 * @property string $file_name
 * @property string|null $stored_path
 * @property string $status
 * @property int $rows_total
 * @property int $rows_accepted
 * @property int $rows_rejected
 * @property int $rows_flagged_duplicate
 * @property array{total: int, truncated: bool, by_rule?: array<string, int>, errors: list<array{row: int, column: string, rule: string}>}|null $error_report
 */
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
