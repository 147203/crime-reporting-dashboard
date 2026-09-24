<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\HttpFoundation\Response;
use Tests\Support\AccessFixture;
use Tests\Support\RouteAccessMatrix;
use Tests\TestCase;

/**
 * TC-SE-01: route x role authorization matrix, every route for guest, admin, officer and analyst.
 */
final class RouteAccessMatrixTest extends TestCase
{
    use RefreshDatabase;

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
     * @return iterable<string, array{string, string, string, string}>
     */
    public static function cases(): iterable
    {
        foreach (RouteAccessMatrix::routes() as $key => $expectations) {
            [$method, $name] = explode(' ', $key, 2);

            foreach ($expectations as $actor => $expected) {
                yield "{$actor} {$key}" => [$actor, $method, $name, $expected];
            }
        }
    }

    /** TC-SE-01b */
    #[Test]
    #[DataProvider('cases')]
    public function each_actor_gets_the_expected_outcome(string $actor, string $method, string $name, string $expected): void
    {
        $fixture = new AccessFixture;
        $user = $fixture->actor($actor);

        if ($user !== null) {
            $this->actingAs($user);
        }

        $this->assertOutcome($this->call($method, route($name, RouteAccessMatrix::parameters($name, $fixture))), $expected);
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
     * "Allowed" means the request got past authentication, authorization and throttling: no 401, 403, 404, 405, 419,
     * 429 or 5xx, and not bounced to the login page (a redirect to login counts only if it carries validation errors).
     *
     * @param  TestResponse<Response>  $response
     */
    private function assertReachedTheAction(TestResponse $response): void
    {
        $status = $response->getStatusCode();

        $this->assertNotContains($status, [401, 403, 404, 405, 419, 429], "Unexpected status {$status}.");
        $this->assertLessThan(500, $status, (string) $response->exception?->getMessage());

        if ($response->isRedirect(route('login'))) {
            $response->assertSessionHasErrors();
        }
    }
}
