<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Most serious injury suffered by any victim.
 */
enum InjuryLevel: string
{
    case None = 'none';
    case Minor = 'minor';
    case Serious = 'serious';
    case Fatal = 'fatal';
    case NotRecorded = 'not_recorded';

    public function label(): string
    {
        return match ($this) {
            self::None => 'No injury',
            self::Minor => 'Minor injury',
            self::Serious => 'Serious injury',
            self::Fatal => 'Fatal',
            self::NotRecorded => 'Not recorded',
        };
    }
}
