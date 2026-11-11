<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Sex of the main victim, as recorded by the officer.
 */
enum VictimSex: string
{
    case Male = 'male';
    case Female = 'female';
    case NotRecorded = 'not_recorded';

    public function label(): string
    {
        return match ($this) {
            self::Male => 'Male',
            self::Female => 'Female',
            self::NotRecorded => 'Not recorded',
        };
    }
}
