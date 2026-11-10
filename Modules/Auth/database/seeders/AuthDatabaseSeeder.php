<?php

namespace Modules\Auth\database\seeders;

use Database\Seeders\AuthFeatureFlagSeeder;
use Illuminate\Database\Seeder;

class AuthDatabaseSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->call([
            AuthFeatureFlagSeeder::class,
        ]);
    }
}
