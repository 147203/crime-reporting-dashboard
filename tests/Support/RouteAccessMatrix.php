<?php

declare(strict_types=1);

namespace Tests\Support;

/**
 * Expected outcome of every route for every actor (docs/02 section 2).
 * A route that is registered but missing here fails RouteAccessMatrixTest, so no route can ship without a decision on
 * who may use it. Route parameters come from the fixture (an incident at the officer's own station, created by them).
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
        $everyone = ['admin' => self::ALLOW, 'officer' => self::ALLOW, 'analyst' => self::ALLOW];
        $guestOnly = ['admin' => self::HOME, 'officer' => self::HOME, 'analyst' => self::HOME];
        $writers = ['admin' => self::ALLOW, 'officer' => self::ALLOW, 'analyst' => self::FORBIDDEN];
        $adminOnly = ['admin' => self::ALLOW, 'officer' => self::FORBIDDEN, 'analyst' => self::FORBIDDEN];
        $reviewers = ['admin' => self::ALLOW, 'officer' => self::FORBIDDEN, 'analyst' => self::ALLOW];

        return [
            'GET login' => ['guest' => self::ALLOW, ...$guestOnly],
            'POST login.store' => ['guest' => self::ALLOW, ...$guestOnly],
            'POST logout' => self::signedIn($everyone),
            'GET password.confirm' => self::signedIn($everyone),
            'POST password.confirm.store' => self::signedIn($everyone),
            'GET password.confirmation' => self::signedIn($everyone),
            'GET password.edit' => self::signedIn($everyone),
            'PUT password.update' => self::signedIn($everyone),

            'GET home' => self::signedIn($everyone),

            'GET incidents.index' => self::signedIn($everyone),
            'GET incidents.create' => self::signedIn($writers),
            'POST incidents.store' => self::signedIn($writers),
            'GET incidents.show' => self::signedIn($everyone),
            'GET incidents.edit' => self::signedIn($writers),
            'PUT incidents.update' => self::signedIn($writers),
            'PUT incidents.status' => self::signedIn($writers),
            'DELETE incidents.destroy' => self::signedIn($adminOnly),

            'GET duplicates.index' => self::signedIn($reviewers),
            'PUT duplicates.resolve' => self::signedIn($reviewers),
            'GET quality.index' => self::signedIn($reviewers),
            'GET imports.create' => self::signedIn($adminOnly),
            'POST imports.store' => self::signedIn($adminOnly),
            'GET imports.show' => self::signedIn($adminOnly),
            'POST imports.commit' => self::signedIn($adminOnly),
            'POST imports.discard' => self::signedIn($adminOnly),
            'GET imports.errors' => self::signedIn($adminOnly),

            'GET admin.users.index' => self::signedIn($adminOnly),
            'GET admin.users.create' => self::signedIn($adminOnly),
            'POST admin.users.store' => self::signedIn($adminOnly),
            'GET admin.users.edit' => self::signedIn($adminOnly),
            'PUT admin.users.update' => self::signedIn($adminOnly),
            'GET admin.reference.index' => self::signedIn($adminOnly),
            'POST admin.reference.store' => self::signedIn($adminOnly),
            'PUT admin.reference.update' => self::signedIn($adminOnly),
            'GET admin.audit.index' => self::signedIn($adminOnly),
        ];
    }

    /**
     * @param  array{admin: string, officer: string, analyst: string}  $roles
     * @return array{guest: string, admin: string, officer: string, analyst: string}
     */
    private static function signedIn(array $roles): array
    {
        return ['guest' => self::LOGIN, 'admin' => $roles['admin'], 'officer' => $roles['officer'], 'analyst' => $roles['analyst']];
    }

    /**
     * @return array<string, int|string>
     */
    public static function parameters(string $routeName, AccessFixture $fixture): array
    {
        return match ($routeName) {
            'incidents.show', 'incidents.edit', 'incidents.update', 'incidents.status', 'incidents.destroy', 'duplicates.resolve' => ['incident' => $fixture->incident->id],
            'imports.show', 'imports.commit', 'imports.discard', 'imports.errors' => ['batch' => $fixture->batch->id],
            'admin.users.edit', 'admin.users.update' => ['user' => $fixture->officer->id],
            'admin.reference.store' => ['type' => 'categories'],
            'admin.reference.update' => ['type' => 'categories', 'item' => $fixture->incident->category_id],
            default => [],
        };
    }
}
