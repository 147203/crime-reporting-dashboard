<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Age band of the main victim. Derived from the exact age when one is recorded.
 */
enum AgeBand: string
{
    case Under18 = 'under_18';
    case From18To24 = '18_24';
    case From25To34 = '25_34';
    case From35To44 = '35_44';
    case From45To54 = '45_54';
    case From55To64 = '55_64';
    case From65 = '65_plus';
    case NotRecorded = 'not_recorded';

    public function label(): string
    {
        return match ($this) {
            self::Under18 => 'Under 18',
            self::From18To24 => '18 to 24',
            self::From25To34 => '25 to 34',
            self::From35To44 => '35 to 44',
            self::From45To54 => '45 to 54',
            self::From55To64 => '55 to 64',
            self::From65 => '65 and over',
            self::NotRecorded => 'Not recorded',
        };
    }

    public static function fromAge(int $age): self
    {
        return match (true) {
            $age < 18 => self::Under18,
            $age <= 24 => self::From18To24,
            $age <= 34 => self::From25To34,
            $age <= 44 => self::From35To44,
            $age <= 54 => self::From45To54,
            $age <= 64 => self::From55To64,
            default => self::From65,
        };
    }
}
