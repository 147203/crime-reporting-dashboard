<?php

declare(strict_types=1);

namespace App\Support;

use Random\Randomizer;

/**
 * Temporary passwords for new users and resets: cryptographically random, at least one character from each group,
 * and without look-alike characters (0/O, 1/l/I) so they can be read out or copied by hand.
 */
final class PasswordGenerator
{
    private const GROUPS = [
        'ABCDEFGHJKLMNPQRSTUVWXYZ',
        'abcdefghijkmnopqrstuvwxyz',
        '23456789',
        '!#%+-=?@_',
    ];

    public function generate(int $length = 16): string
    {
        $random = new Randomizer;
        $characters = array_map(fn (string $group): string => $random->getBytesFromString($group, 1), self::GROUPS);

        $all = implode('', self::GROUPS);
        for ($i = count($characters); $i < $length; $i++) {
            $characters[] = $random->getBytesFromString($all, 1);
        }

        return implode('', $random->shuffleArray($characters));
    }
}
