<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use App\Models\CrimeCategory;
use App\Models\Incident;
use App\Models\Station;
use App\Models\SubCounty;
use App\Models\User;
use App\Validation\IncidentRules;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\AccessFixture;
use Tests\TestCase;

/**
 * Regression tests for the Phase 3 independent review findings.
 */
final class ReviewRegressionTest extends TestCase
{
    use RefreshDatabase;

    /** TC-SE-12: a password change or admin reset ends other sessions and rotates the remember token. */
    #[Test]
    public function changing_a_password_ends_the_users_other_sessions(): void
    {
        $user = User::factory()->create(['password' => 'initial-password-1']);
        DB::table('sessions')->insert(['id' => 'other-device', 'user_id' => $user->id, 'payload' => '', 'last_activity' => time()]);
        $token = $user->getRememberToken();

        $this->actingAs($user)->put(route('password.update'), [
            'current_password' => 'initial-password-1', 'password' => 'my-own-password-2', 'password_confirmation' => 'my-own-password-2',
        ])->assertRedirect(route('home'));

        $this->assertSame(0, DB::table('sessions')->where('id', 'other-device')->count());
        $this->assertNotSame($token, $user->refresh()->getRememberToken());
    }

    /** TC-SE-13: a posted "remember" flag is ignored; no long-lived cookie is issued. */
    #[Test]
    public function remember_me_is_not_honoured(): void
    {
        $user = User::factory()->create(['password' => 'correct-horse-battery-1']);

        foreach (['/login', '/login?remember=1'] as $url) {
            $response = $this->post($url, ['email' => $user->email, 'password' => 'correct-horse-battery-1', 'remember' => '1']);

            $response->assertRedirect('/');
            foreach ($response->headers->getCookies() as $cookie) {
                $this->assertStringStartsNotWith('remember_web_', $cookie->getName(), $url);
            }
            $this->post('/logout');
        }
    }

    /** TC-FT-32: a ward that has incidents cannot be moved to another sub-county (the composite key would refuse it). */
    #[Test]
    public function a_ward_with_incidents_cannot_change_sub_county(): void
    {
        $admin = User::factory()->admin()->create();
        $incident = Incident::factory()->create();
        $other = SubCounty::factory()->create();

        $this->actingAs($admin)->put(route('admin.reference.update', ['type' => 'wards', 'item' => $incident->ward_id]), ['name' => 'Renamed', 'sub_county_id' => $other->id])
            ->assertSessionHasErrors('sub_county_id');
        $this->put(route('admin.reference.update', ['type' => 'wards', 'item' => $incident->ward_id]), ['name' => 'Renamed', 'sub_county_id' => $incident->sub_county_id])
            ->assertSessionHasNoErrors();
    }

    /** TC-UT-14: V5 strips tags but keeps a lone "<" and the text after it. */
    #[Test]
    public function tag_stripping_keeps_a_lone_less_than_sign(): void
    {
        $this->assertSame('a<b and fled', IncidentRules::clean('a<b and fled'));
        $this->assertSame('Near the market gate', IncidentRules::clean('Near the <b>market</b> gate'));
        $this->assertSame('alert(1)', IncidentRules::clean('<script>alert(1)</script>'));
        $this->assertNull(IncidentRules::clean('   '));
    }

    /** TC-FT-33: an existing record keeps a category that was deactivated later; new records cannot use it. */
    #[Test]
    public function editing_keeps_a_deactivated_category_but_creating_cannot_use_it(): void
    {
        $fixture = new AccessFixture;
        $category = CrimeCategory::query()->findOrFail($fixture->incident->category_id);
        $category->forceFill(['is_active' => false])->save();
        $payload = [
            'category_id' => $category->id,
            'occurred_at' => $fixture->incident->occurred_at->format('Y-m-d H:i'),
            'reported_at' => $fixture->incident->reported_at->format('Y-m-d H:i'),
            'sub_county_id' => $fixture->incident->sub_county_id,
            'ward_id' => $fixture->incident->ward_id,
            'station_id' => $fixture->incident->station_id,
        ];

        $this->actingAs($fixture->admin)->put(route('incidents.update', $fixture->incident), $payload)->assertSessionHasNoErrors();
        $this->post(route('incidents.store'), $payload)->assertSessionHasErrors('category_id');
    }

    /** TC-SE-14: an officer at a deactivated station cannot report incidents. */
    #[Test]
    public function an_officer_at_an_inactive_station_cannot_create(): void
    {
        $station = Station::factory()->create(['is_active' => false]);
        $this->actingAs(User::factory()->officer($station)->create())->get(route('incidents.create'))->assertForbidden();
    }

    /** TC-SE-02: injection strings in every list filter do not change results or cause errors. */
    #[Test]
    public function injection_strings_in_filters_are_rejected_or_treated_as_text(): void
    {
        $fixture = new AccessFixture;
        $this->actingAs($fixture->admin);
        $payloads = ["' OR '1'='1", '1; DROP TABLE incidents; --', '1 UNION SELECT password FROM users', '%', '_', '\\'];

        foreach (['q', 'from', 'to', 'category_id', 'sub_county_id', 'ward_id', 'station_id', 'status', 'duplicate_status', 'sort', 'direction'] as $field) {
            foreach ($payloads as $payload) {
                $response = $this->get(route('incidents.index', [$field => $payload]));
                $this->assertContains($response->getStatusCode(), [200, 302], "{$field}={$payload}");
                $this->assertStringNotContainsString('$2y$', (string) $response->getContent());
            }
        }

        $this->assertSame(1, Incident::query()->count());
        $this->get(route('incidents.index', ['q' => '%']))->assertOk()->assertSee('No incidents match');
    }

    /** TC-SE-10b: hardening headers are also sent on redirects and on refusals. */
    #[Test]
    public function headers_are_present_on_redirects_and_forbidden_responses(): void
    {
        $this->get('/')->assertRedirect()->assertHeader('X-Frame-Options', 'DENY');
        $this->actingAs(User::factory()->analyst()->create())->get(route('admin.users.index'))->assertForbidden()->assertHeader('X-Content-Type-Options', 'nosniff');
    }
}
