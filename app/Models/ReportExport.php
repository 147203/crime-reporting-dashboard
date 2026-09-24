<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReportExport extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['user_id', 'type', 'filters', 'row_count'];

    protected function casts(): array
    {
        return ['filters' => 'array', 'created_at' => 'datetime'];
    }
}
