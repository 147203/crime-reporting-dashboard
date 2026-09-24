<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class LoginTest extends TestCase
{
    use RefreshDatabase;

    /** TC-FT-00: evidence that the suite runs on the XAMPP MariaDB test schema, not SQLite (D-04). */
    #[Test]
    public function the_suite_runs_on_the_mariadb_test_database(): void
    {
        $connection = DB::connection();

        $this->assertSame('mysql_testing', $connection->getName());
        $this->assertSame('mariadb', $connection->getDriverName());
        $this->assertStringContainsString('MariaDB', (string) $connection->scalar('select version()'));
        $this->assertStringEndsWith('_test', $connection->getDatabaseName());
    }

    /** TC-FT-03 */
    #[Test]
    public function a_guest_is_redirected_from_home_to_login(): void
    {
        $this->get('/')->assertRedirect(route('login'));
    }

    /** TC-FT-04 */
    #[Test]
    public function the_login_page_renders_with_a_csrf_field_and_no_registration_link(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertSee('name="_token"', false)
            ->assertDontSee('Register');
    }

    /** TC-FT-05 */
    #[Test]
    public function a_user_with_the_right_password_is_signed_in(): void
    {
        $user = User::factory()->create(['password' => 'correct-horse-battery']);

        $this->post('/login', ['email' => $user->email, 'password' => 'correct-horse-battery'])
            ->assertRedirect('/');

        $this->assertAuthenticatedAs($user);
    }

    /** TC-FT-06 */
    #[Test]
    public function a_wrong_password_is_rejected(): void
    {
        $user = User::factory()->create(['password' => 'correct-horse-battery']);

        $this->post('/login', ['email' => $user->email, 'password' => 'wrong-password-here'])
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    /** TC-FT-07 */
    #[Test]
    public function registration_routes_do_not_exist(): void
    {
        $this->get('/register')->assertNotFound();
        $this->post('/register')->assertNotFound();
    }
}
