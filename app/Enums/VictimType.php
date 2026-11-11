<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Who or what the offence was committed against.
 */
enum VictimType: string
{
    case Individual = 'individual';
    case Business = 'business';
    case PublicProperty = 'public_property';
    case NotRecorded = 'not_recorded';

    public function label(): string
    {
        return match ($this) {
            self::Individual => 'Individual person',
            self::Business => 'Business or organisation',
            self::PublicProperty => 'Public property',
            self::NotRecorded => 'Not recorded',
        };
    }
}
