<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Support\Facades\Artisan;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class PreflightCommandTest extends TestCase
{
    private function useValidSecrets(): void
    {
        config([
            'app.env' => 'production',
            'app.key' => 'base64:'.base64_encode(str_repeat('k', 32)),
            'admin.initial_password' => 'admin-password-long-enough',
            'database.connections.mysql.host' => '127.0.0.1',
            'database.connections.mysql.database' => 'crime_dashboard',
            'database.connections.mysql.username' => 'crime_app',
            'database.connections.mysql.password' => 'app-password-long-enough',
            'database.connections.mysql_testing.username' => 'crime_test',
            'database.connections.mysql_testing.password' => 'test-password-long-enough',
        ]);
    }

    /** TC-FT-01 */
    #[Test]
    public function it_exits_non_zero_and_names_the_variable_without_printing_its_value(): void
    {
        $this->useValidSecrets();
        config(['database.connections.mysql.password' => 'Xq9-too-short']);

        $exitCode = Artisan::call('app:preflight');
        $output = Artisan::output();

        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('DB_PASSWORD is shorter than 16 characters.', $output);
        $this->assertStringNotContainsString('Xq9-too-short', $output);
    }

    /** TC-FT-02 */
    #[Test]
    public function it_exits_zero_when_every_check_passes(): void
    {
        $this->useValidSecrets();

        $exitCode = Artisan::call('app:preflight');

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('Preflight passed.', Artisan::output());
    }
}
