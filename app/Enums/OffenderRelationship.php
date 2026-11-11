<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * The main victim's relationship to the suspect.
 */
enum OffenderRelationship: string
{
    case Stranger = 'stranger';
    case Acquaintance = 'acquaintance';
    case FamilyOrPartner = 'family_or_partner';
    case NotKnown = 'not_known';

    public function label(): string
    {
        return match ($this) {
            self::Stranger => 'Stranger',
            self::Acquaintance => 'Acquaintance',
            self::FamilyOrPartner => 'Family member or partner',
            self::NotKnown => 'Not known',
        };
    }
}
