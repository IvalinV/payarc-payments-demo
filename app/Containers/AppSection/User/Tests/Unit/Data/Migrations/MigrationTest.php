<?php

namespace App\Containers\AppSection\User\Tests\Unit\Data\Migrations;

use App\Containers\AppSection\User\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversNothing;

#[CoversNothing]
final class MigrationTest extends UnitTestCase
{
    public function testUsersTableHasExpectedColumns(): void
    {
        $columns = [
            'id' => 'bigint',
            'name' => 'varchar',
            'email' => 'varchar',
            'email_verified_at' => 'timestamp',
            'password' => 'varchar',
            'is_admin' => 'tinyint',
            'is_agent' => 'tinyint',
            'is_merchant' => 'tinyint',
            'gender' => 'varchar',
            'birth' => 'date',
            'remember_token' => 'varchar',
            'created_at' => 'timestamp',
            'updated_at' => 'timestamp',
        ];

        $this->assertDatabaseTable('users', $columns);
    }

    public function testPasswordResetsTableHasExpectedColumns(): void
    {
        $columns = [
            'email' => 'varchar',
            'token' => 'varchar',
            'created_at' => 'timestamp',
        ];

        $this->assertDatabaseTable('password_reset_tokens', $columns);
    }

    public function testSessionsTableHasExpectedColumns(): void
    {
        $columns = [
            'id' => 'varchar',
            'user_id' => 'bigint',
            'ip_address' => 'varchar',
            'user_agent' => 'text',
            'payload' => 'longtext',
            'last_activity' => 'int',
        ];

        $this->assertDatabaseTable('sessions', $columns);
    }
}
