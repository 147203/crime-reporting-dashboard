<?php

declare(strict_types=1);

namespace App\Enums;

enum DuplicateStatus: string
{
    case None = 'none';
    case Suspected = 'suspected';
    case Confirmed = 'confirmed';
    case Dismissed = 'dismissed';

    public function label(): string
    {
        return ucfirst($this->value);
    }
}
