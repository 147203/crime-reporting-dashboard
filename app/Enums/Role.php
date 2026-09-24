<?php

declare(strict_types=1);

namespace App\Enums;

enum Role: string
{
    case Admin = 'admin';
    case Officer = 'officer';
    case Analyst = 'analyst';

    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Administrator',
            self::Officer => 'Reporting Officer',
            self::Analyst => 'Analyst',
        };
    }

    /**
     * Roles whose data access is limited to their own station (docs/02 section 2).
     */
    public function isStationScoped(): bool
    {
        return $this === self::Officer;
    }
}
