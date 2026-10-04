<?php

namespace App\Containers\AppSection\Payments\Tests\Unit\Data\Migrations;

use App\Ship\Parents\Tests\TestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\CoversNothing;

#[CoversNothing]
final class MigrationTest extends TestCase
{
    public function testUsersTableHasRoleFlags(): void
    {
        self::assertTrue(Schema::hasColumns('users', [
            'is_admin',
            'is_agent',
            'is_merchant',
        ]));
    }

    public function testPaymentTablesHaveExpectedColumns(): void
    {
        self::assertTrue(Schema::hasColumns('agents', [
            'id',
            'first_name',
            'last_name',
            'email',
            'company_legal_name',
            'company_dba',
            'type',
            'default_split',
            'parent_agent_id',
            'created_at',
            'updated_at',
        ]));
        self::assertTrue(Schema::hasColumns('accounts', [
            'id',
            'business_name',
            'dba_name',
            'legal_name',
            'email',
            'mid',
            'status',
            'active_yn',
            'created_by',
            'created_at',
            'updated_at',
        ]));
        self::assertTrue(Schema::hasColumns('charges', [
            'id',
            'account_id',
            'amount',
            'amount_captured',
            'amount_refunded',
            'amount_voided',
            'currency',
            'status',
            'external_transaction_id',
            'customer_email',
            'refunded',
            'disputed',
            'void_reason',
            'description',
            'idempotency_key',
            'created_by',
            'created_at',
            'updated_at',
        ]));
    }

    public function testChargeStatusIsTheDefinedMySqlEnum(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            self::markTestSkipped('Charge status type is MySQL-specific.');
        }

        $column = DB::selectOne("SHOW COLUMNS FROM charges WHERE Field = 'status'");

        self::assertSame(
            "enum('authorized','captured','refunded','voided','failed')",
            $column->Type,
        );
    }

    public function testChargeAmountDefaultsToZero(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            self::markTestSkipped('Charge amount metadata is MySQL-specific.');
        }

        $column = DB::selectOne("SHOW COLUMNS FROM charges WHERE Field = 'amount'");

        self::assertSame('0', (string) $column->Default);
    }

    public function testAllPivotTablesExist(): void
    {
        self::assertTrue(Schema::hasTable('agent_account'));
        self::assertTrue(Schema::hasTable('account_user'));
        self::assertTrue(Schema::hasTable('agent_user'));
    }
}
