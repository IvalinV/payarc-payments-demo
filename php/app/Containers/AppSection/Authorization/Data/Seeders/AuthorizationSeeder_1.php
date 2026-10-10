<?php

namespace App\Containers\AppSection\Authorization\Data\Seeders;

use App\Containers\AppSection\Authorization\Models\Role;
use App\Ship\Parents\Seeders\Seeder as ParentSeeder;

final class AuthorizationSeeder_1 extends ParentSeeder
{
    public function run(): void
    {
        Role::updateOrCreate(
            ['name' => 'admin', 'guard_name' => 'api'],
            ['display_name' => 'Super Admin', 'description' => 'Administrator'],
        );
    }
}
