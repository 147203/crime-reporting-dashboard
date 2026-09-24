<?php

declare(strict_types=1);

namespace App\Enums;

enum IncidentStatus: string
{
    case Reported = 'reported';
    case UnderInvestigation = 'under_investigation';
    case Closed = 'closed';

    public function label(): string
    {
        return match ($this) {
            self::Reported => 'Reported',
            self::UnderInvestigation => 'Under investigation',
            self::Closed => 'Closed',
        };
    }

    /**
     * Allowed forward transitions; Closed is terminal.
     *
     * @return list<self>
     */
    public function next(): array
    {
        return match ($this) {
            self::Reported => [self::UnderInvestigation, self::Closed],
            self::UnderInvestigation => [self::Closed],
            self::Closed => [],
        };
    }

    public function canMoveTo(self $target): bool
    {
        return in_array($target, $this->next(), true);
    }
}
