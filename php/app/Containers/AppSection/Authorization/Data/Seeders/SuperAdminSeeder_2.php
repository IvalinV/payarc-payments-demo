<?php

namespace App\Containers\AppSection\Authorization\Data\Seeders;

use App\Containers\AppSection\Authorization\Models\Role;
use App\Containers\AppSection\User\Models\User;
use App\Ship\Parents\Seeders\Seeder as ParentSeeder;
use Illuminate\Support\Facades\Hash;

final class SuperAdminSeeder_2 extends ParentSeeder
{
    public function run(): void
    {
        $user = User::query()->updateOrCreate(
            ['email' => 'admin@payarc.test'],
            [
                'password' => Hash::make('Demo#12345'),
                'name' => 'Demo Admin',
                'is_admin' => true,
                'email_verified_at' => now(),
            ],
        );

        $user->syncRoles([Role::query()->where('name', 'admin')->where('guard_name', 'api')->firstOrFail()]);
    }
}
