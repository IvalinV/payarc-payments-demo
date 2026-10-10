<?php

namespace App\Containers\AppSection\Authentication\Tests\Unit\Data\Migrations;

use App\Containers\AppSection\Authentication\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversNothing;

#[CoversNothing]
final class MigrationTest extends UnitTestCase
{
    public function testOAuthAuthCodesTableHasExpectedColumns(): void
    {
        $columns = [
            'id' => 'char',
            'user_id' => 'bigint',
            'client_id' => 'char',
            'scopes' => 'text',
            'revoked' => 'tinyint',
            'expires_at' => 'datetime',
        ];

        $this->assertDatabaseTable('oauth_auth_codes', $columns);
    }

    public function testOAuthAccessTokenTableHasExpectedColumns(): void
    {
        $columns = [
            'id' => 'char',
            'user_id' => 'bigint',
            'client_id' => 'char',
            'name' => 'varchar',
            'scopes' => 'text',
            'revoked' => 'tinyint',
            'created_at' => 'timestamp',
            'updated_at' => 'timestamp',
            'expires_at' => 'datetime',
        ];

        $this->assertDatabaseTable('oauth_access_tokens', $columns);
    }

    public function testOAuthRefreshTokenTableHasExpectedColumns(): void
    {
        $columns = [
            'id' => 'char',
            'access_token_id' => 'char',
            'revoked' => 'tinyint',
            'expires_at' => 'datetime',
        ];

        $this->assertDatabaseTable('oauth_refresh_tokens', $columns);
    }

    public function testOAuthClientsTableHasExpectedColumns(): void
    {
        $columns = [
            'id' => 'char',
            'owner_type' => 'varchar',
            'owner_id' => 'bigint',
            'name' => 'varchar',
            'secret' => 'varchar',
            'provider' => 'varchar',
            'redirect_uris' => 'text',
            'grant_types' => 'text',
            'revoked' => 'tinyint',
            'created_at' => 'timestamp',
            'updated_at' => 'timestamp',
        ];

        $this->assertDatabaseTable('oauth_clients', $columns);
    }

    public function testOAuthDeviceCodesTableHasExpectedColumns(): void
    {
        $columns = [
            'id' => 'char',
            'user_id' => 'bigint',
            'client_id' => 'char',
            'user_code' => 'char',
            'scopes' => 'text',
            'revoked' => 'tinyint',
            'user_approved_at' => 'datetime',
            'last_polled_at' => 'datetime',
            'expires_at' => 'datetime',
        ];

        $this->assertDatabaseTable('oauth_device_codes', $columns);
    }
}
