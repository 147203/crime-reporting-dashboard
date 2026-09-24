<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use Illuminate\Support\Facades\Route;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\HttpFoundation\Response;
use Tests\Support\RouteAccessMatrix;
use Tests\TestCase;

/**
 * Route x role authorization harness. The guest column runs now; the admin, officer and analyst columns run once
 * roles exist (Phase 3), using the same matrix and the same assertion.
 */
final class RouteAccessMatrixTest extends TestCase
{
    /** TC-SE-01a */
    #[Test]
    public function every_registered_route_is_named_and_listed_in_the_matrix_and_nothing_else_is(): void
    {
        $registered = [];

        foreach (Route::getRoutes()->getRoutes() as $route) {
            $this->assertNotNull($route->getName(), "Route {$route->uri()} has no name, so it cannot be listed in the access matrix.");

            foreach (array_diff($route->methods(), ['HEAD']) as $method) {
                $registered[] = "{$method} {$route->getName()}";
            }
        }

        $listed = array_keys(RouteAccessMatrix::routes());

        $this->assertSame([], array_values(array_diff($registered, $listed)), 'Registered routes missing from RouteAccessMatrix.');
        $this->assertSame([], array_values(array_diff($listed, $registered)), 'RouteAccessMatrix lists routes that are not registered.');
    }

    /**
     * @return iterable<string, array{string, string, string}>
     */
    public static function guestCases(): iterable
    {
        foreach (RouteAccessMatrix::routes() as $key => $expected) {
            [$method, $name] = explode(' ', $key, 2);

            yield $key => [$method, $name, $expected['guest']];
        }
    }

    /** TC-SE-01b */
    #[Test]
    #[DataProvider('guestCases')]
    public function a_guest_gets_the_expected_outcome(string $method, string $name, string $expected): void
    {
        $this->assertOutcome($this->call($method, route($name)), $expected);
    }

    /**
     * @param  TestResponse<Response>  $response
     */
    private function assertOutcome(TestResponse $response, string $expected): void
    {
        match ($expected) {
            RouteAccessMatrix::LOGIN => $response->assertRedirect(route('login'))->assertSessionHasNoErrors(),
            RouteAccessMatrix::HOME => $response->assertRedirect(route('home')),
            RouteAccessMatrix::FORBIDDEN => $response->assertForbidden(),
            RouteAccessMatrix::ALLOW => $this->assertReachedTheAction($response),
            default => $this->fail("Unknown expectation {$expected}."),
        };
    }

    /**
     * "Allowed" means the request got past authentication, authorization and throttling: no 401, 403, 404, 405, 419, 429
     * or 5xx. A redirect counts only if it is not to the login or home page, or if it carries validation errors (the
     * action ran and rejected the input).
     *
     * @param  TestResponse<Response>  $response
     */
    private function assertReachedTheAction(TestResponse $response): void
    {
        $status = $response->getStatusCode();

        $this->assertNotContains($status, [401, 403, 404, 405, 419, 429], "Unexpected status {$status}.");
        $this->assertLessThan(500, $status);

        if ($response->isRedirect(route('login')) || $response->isRedirect(route('home'))) {
            $response->assertSessionHasErrors();
        }
    }
}
