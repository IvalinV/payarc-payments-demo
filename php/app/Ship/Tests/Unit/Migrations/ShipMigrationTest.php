<?php

namespace App\Ship\Tests\Unit\Migrations;

use App\Ship\Tests\ShipTestCase;
use PHPUnit\Framework\Attributes\CoversNothing;

#[CoversNothing]
final class ShipMigrationTest extends ShipTestCase
{
    public function testCacheTableHasExpectedColumns(): void
    {
        $columns = [
            'key' => 'varchar',
            'value' => 'mediumtext',
            'expiration' => 'int',
        ];

        $this->assertDatabaseTable('cache', $columns);
    }

    public function testCacheLocksTableHasExpectedColumns(): void
    {
        $columns = [
            'key' => 'varchar',
            'owner' => 'varchar',
            'expiration' => 'int',
        ];

        $this->assertDatabaseTable('cache_locks', $columns);
    }

    public function testJobsTableHasExpectedColumns(): void
    {
        $columns = [
            'id' => 'bigint',
            'queue' => 'varchar',
            'payload' => 'longtext',
            'attempts' => 'tinyint',
            'reserved_at' => 'int',
            'available_at' => 'int',
            'created_at' => 'int',
        ];

        $this->assertDatabaseTable('jobs', $columns);
    }

    public function testJobBatchesTableHasExpectedColumns(): void
    {
        $columns = [
            'id' => 'varchar',
            'name' => 'varchar',
            'total_jobs' => 'int',
            'pending_jobs' => 'int',
            'failed_jobs' => 'int',
            'failed_job_ids' => 'longtext',
            'options' => 'mediumtext',
            'cancelled_at' => 'int',
            'created_at' => 'int',
            'finished_at' => 'int',
        ];

        $this->assertDatabaseTable('job_batches', $columns);
    }

    public function testFailedJobsTableHasExpectedColumns(): void
    {
        $columns = [
            'id' => 'bigint',
            'connection' => 'text',
            'queue' => 'text',
            'payload' => 'longtext',
            'exception' => 'longtext',
            'failed_at' => 'timestamp',
            'uuid' => 'varchar',
        ];

        $this->assertDatabaseTable('failed_jobs', $columns);
    }

    public function testNotificationsTableHasExpectedColumns(): void
    {
        $columns = [
            'id' => 'char',
            'type' => 'varchar',
            'notifiable_id' => 'bigint',
            'notifiable_type' => 'varchar',
            'data' => 'text',
            'read_at' => 'timestamp',
            'created_at' => 'timestamp',
            'updated_at' => 'timestamp',
        ];

        $this->assertDatabaseTable('notifications', $columns);
    }
}
