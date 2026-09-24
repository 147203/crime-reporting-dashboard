<?php

declare(strict_types=1);

namespace Tests\Support;

/**
 * Expected outcome of every route for every actor (docs/02 section 2).
 * A route that is registered but missing here fails RouteAccessMatrixTest, so no route can ship without a decision on who may use it.
 */
final class RouteAccessMatrix
{
    public const ALLOW = 'allow';

    public const LOGIN = 'redirect-to-login';

    public const HOME = 'redirect-to-home';

    public const FORBIDDEN = 'forbidden';

    public const ACTORS = ['guest', 'admin', 'officer', 'analyst'];

    /**
     * Keyed "METHOD route.name".
     *
     * @return array<string, array{guest: string, admin: string, officer: string, analyst: string}>
     */
    public static function routes(): array
    {
        $signedIn = ['admin' => self::ALLOW, 'officer' => self::ALLOW, 'analyst' => self::ALLOW];
        $guestOnly = ['admin' => self::HOME, 'officer' => self::HOME, 'analyst' => self::HOME];

        return [
            'GET home' => ['guest' => self::LOGIN, ...$signedIn],
            'GET login' => ['guest' => self::ALLOW, ...$guestOnly],
            'POST login.store' => ['guest' => self::ALLOW, ...$guestOnly],
            'POST logout' => ['guest' => self::LOGIN, ...$signedIn],
            'GET password.confirm' => ['guest' => self::LOGIN, ...$signedIn],
            'POST password.confirm.store' => ['guest' => self::LOGIN, ...$signedIn],
            'GET password.confirmation' => ['guest' => self::LOGIN, ...$signedIn],
        ];
    }
}
