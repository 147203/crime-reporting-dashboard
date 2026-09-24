<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\Preflight;
use Illuminate\Config\Repository;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class PreflightTest extends TestCase
{
    /**
     * @param  array<string, mixed>  $overrides
     */
    private function preflight(array $overrides = []): Preflight
    {
        $config = new Repository([
            'session' => ['secure' => true, 'driver' => 'database'],
            'app' => ['env' => 'production', 'debug' => false, 'url' => 'https://crime.example.test', 'key' => 'base64:'.base64_encode(str_repeat('k', 32))],
            'admin' => ['initial_password' => 'admin-password-long-enough'],
            'database' => ['default' => 'mysql', 'connections' => [
                'mysql' => ['host' => '127.0.0.1', 'database' => 'crime_dashboard', 'username' => 'crime_app', 'password' => 'app-password-long-enough'],
                'mysql_testing' => ['database' => 'crime_dashboard_test', 'username' => 'crime_test', 'password' => 'test-password-long-enough'],
            ]],
        ]);

        foreach ($overrides as $key => $value) {
            $config->set($key, $value);
        }

        return new Preflight($config);
    }

    /** TC-UT-01 */
    #[Test]
    public function valid_configuration_passes_every_check(): void
    {
        $this->assertSame([], $this->preflight()->allFailures());
    }

    /** TC-UT-02 */
    #[Test]
    public function missing_app_key_fails(): void
    {
        $this->assertSame(['APP_KEY is not set (run php artisan key:generate).'], $this->preflight(['app.key' => null])->runtimeFailures());
    }

    /** TC-UT-03 */
    #[Test]
    public function app_key_that_is_short_or_not_base64_fails(): void
    {
        $short = $this->preflight(['app.key' => 'base64:'.base64_encode('too-short')])->runtimeFailures();
        $raw = $this->preflight(['app.key' => str_repeat('k', 32)])->runtimeFailures();
        $invalid = $this->preflight(['app.key' => 'base64:!!not-base64!!'])->runtimeFailures();

        $this->assertStringContainsString('APP_KEY does not decode to 32 bytes', $short[0]);
        $this->assertStringContainsString('APP_KEY must be a base64 key', $raw[0]);
        $this->assertStringContainsString('APP_KEY does not decode to 32 bytes', $invalid[0]);
    }

    /** TC-UT-04 */
    #[Test]
    public function missing_database_settings_fail_and_are_named(): void
    {
        $failures = $this->preflight([
            'database.connections.mysql.host' => '',
            'database.connections.mysql.database' => null,
            'database.connections.mysql.username' => null,
            'database.connections.mysql.password' => null,
        ])->runtimeFailures();

        $this->assertSame([
            'DB_HOST is not set.',
            'DB_DATABASE is not set.',
            'DB_USERNAME is not set.',
            'DB_PASSWORD is not set.',
        ], $failures);
    }

    /** TC-UT-05 */
    #[Test]
    public function database_password_that_is_short_or_a_placeholder_fails_without_revealing_it(): void
    {
        $short = $this->preflight(['database.connections.mysql.password' => 'Short-Secret-1'])->runtimeFailures();
        $placeholder = $this->preflight(['database.connections.mysql.password' => 'changeme-set-your-own'])->runtimeFailures();

        $this->assertSame(['DB_PASSWORD is shorter than 16 characters.'], $short);
        $this->assertSame(['DB_PASSWORD still holds the placeholder from .env.example.'], $placeholder);
        $this->assertStringNotContainsString('Short-Secret-1', implode(' ', $short));
    }

    /** TC-UT-06 */
    #[Test]
    public function test_database_must_not_reuse_application_credentials(): void
    {
        $failures = $this->preflight([
            'database.connections.mysql_testing.username' => 'crime_app',
            'database.connections.mysql_testing.database' => 'crime_dashboard',
            'database.connections.mysql_testing.password' => 'app-password-long-enough',
        ])->allFailures();

        $this->assertSame([
            'DB_TEST_USERNAME must differ from DB_USERNAME.',
            'DB_TEST_DATABASE must be set and end in _test.',
            'DB_TEST_PASSWORD must differ from DB_PASSWORD.',
        ], $failures);

        $sameSchema = $this->preflight([
            'database.connections.mysql.database' => 'crime_dashboard_test',
        ])->allFailures();

        $this->assertSame(['DB_TEST_DATABASE must differ from DB_DATABASE.'], $sameSchema);
    }

    /** TC-UT-07 */
    #[Test]
    public function admin_password_is_required_by_full_preflight_but_not_by_the_runtime_check(): void
    {
        $preflight = $this->preflight(['admin.initial_password' => 'short-admin']);

        $this->assertSame(['ADMIN_INITIAL_PASSWORD is shorter than 14 characters.'], $preflight->allFailures());
        $this->assertSame([], $preflight->runtimeFailures());
        $this->assertSame(['ADMIN_INITIAL_PASSWORD is not set.'], $this->preflight(['admin.initial_password' => null])->adminPasswordFailures());
    }

    /** TC-UT-09 */
    #[Test]
    public function a_connection_other_than_mysql_fails_so_the_checks_cannot_be_sidestepped(): void
    {
        $this->assertSame(['DB_CONNECTION must be mysql.'], $this->preflight(['database.default' => 'sqlite'])->runtimeFailures());
    }

    /** TC-UT-10 */
    #[Test]
    public function the_local_shortcut_accepts_root_with_a_blank_password_only_when_opted_in_on_loopback(): void
    {
        $local = $this->preflight([
            'app.env' => 'local',
            'app.url' => 'http://127.0.0.1:8000',
            'database.allow_local_shortcut' => true,
            'database.connections.mysql.username' => 'root',
            'database.connections.mysql.password' => '',
            'database.connections.mysql_testing.username' => 'root',
            'database.connections.mysql_testing.password' => '',
        ]);

        $this->assertSame([], $local->allFailures());
        $this->assertTrue($local->localDatabaseShortcut());

        $blank = ['database.connections.mysql.username' => 'root', 'database.connections.mysql.password' => ''];
        $staging = $this->preflight([...$blank, 'app.env' => 'staging', 'app.url' => 'http://127.0.0.1', 'database.allow_local_shortcut' => true]);
        $lanHost = $this->preflight([...$blank, 'app.env' => 'local', 'app.url' => 'http://192.168.1.20', 'database.allow_local_shortcut' => true]);
        $noOptIn = $this->preflight([...$blank, 'app.env' => 'local', 'app.url' => 'http://127.0.0.1', 'database.allow_local_shortcut' => false]);

        foreach ([$staging, $lanHost, $noOptIn] as $case) {
            $this->assertFalse($case->localDatabaseShortcut());
            $this->assertContains('DB_USERNAME must be a dedicated application user, not root, outside the local shortcut (D-25).', $case->runtimeFailures());
            $this->assertContains('DB_PASSWORD is not set.', $case->runtimeFailures());
        }
    }

    /** TC-UT-16: production refuses debug mode, insecure cookies and non-database sessions. */
    #[Test]
    public function production_requires_debug_off_secure_cookies_and_database_sessions(): void
    {
        $failures = $this->preflight(['app.debug' => true, 'session.secure' => false, 'session.driver' => 'file'])->runtimeFailures();

        $this->assertSame([
            'APP_DEBUG must be false in production.',
            'SESSION_SECURE_COOKIE must be true in production (serve over HTTPS).',
            'SESSION_DRIVER must be database in production, so sessions can be ended after a password change.',
        ], $failures);
    }

    /** TC-UT-11 */
    #[Test]
    public function outside_production_placeholders_and_a_shared_schema_are_still_rejected(): void
    {
        $failures = $this->preflight([
            'app.env' => 'local',
            'app.url' => 'http://localhost',
            'database.allow_local_shortcut' => true,
            'database.connections.mysql.password' => 'changeme-set-your-own',
            'database.connections.mysql_testing.database' => 'crime_dashboard',
        ])->allFailures();

        $this->assertSame([
            'DB_PASSWORD still holds the placeholder from .env.example.',
            'DB_TEST_DATABASE must be set and end in _test.',
        ], $failures);
    }

    /** TC-UT-08 */
    #[Test]
    public function absent_test_database_is_allowed_on_a_machine_that_does_not_run_tests(): void
    {
        $this->assertSame([], $this->preflight(['database.connections.mysql_testing.username' => null])->allFailures());
    }
}
