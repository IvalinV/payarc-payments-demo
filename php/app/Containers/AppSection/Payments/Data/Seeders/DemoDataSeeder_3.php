<?php

namespace App\Containers\AppSection\Payments\Data\Seeders;

use App\Ship\Parents\Seeders\Seeder as ParentSeeder;
use App\Ship\Seeders\InitialDeploymentDataSeeder;

final class DemoDataSeeder_3 extends ParentSeeder
{
    public function run(): void
    {
        $this->call(InitialDeploymentDataSeeder::class);
    }
}
