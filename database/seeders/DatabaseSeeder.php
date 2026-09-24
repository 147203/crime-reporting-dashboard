<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * No seeder may create an account with a known password.
     */
    public function run(): void
    {
        $this->call(ReferenceDataSeeder::class);
    }
}
