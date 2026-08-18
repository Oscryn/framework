<?php

declare(strict_types=1);

namespace Tests\Database;

use Tests\TestCase;
use Oscryn\Database\DBConnector;
use PDO;
use RuntimeException;
use Tests\Fixtures\Todo;

final class DBConnectorTest extends TestCase
{
    public function test_driver_is_sqlite(): void
    {
        $this->assertSame('sqlite', DBConnector::driver());
    }

    public function test_connection_returns_pdo(): void
    {
        $this->assertInstanceOf(PDO::class, DBConnector::connection());
    }

    public function test_purge_forces_new_connection(): void
    {
        $first = DBConnector::connection();

        DBConnector::purge();

        $this->assertNotSame($first, DBConnector::connection());
    }

    public function test_transaction_commits(): void
    {
        DBConnector::transaction(static function (): void {
            Todo::create(['title' => 'inside transaction']);
        });

        $this->assertCount(1, Todo::all());
    }

    public function test_transaction_rolls_back_on_exception(): void
    {
        try {
            DBConnector::transaction(static function (): void {
                Todo::create(['title' => 'will rollback']);

                throw new RuntimeException('boom');
            });
            $this->fail('Expected the exception to be rethrown');
        } catch (RuntimeException $e) {
            $this->assertSame('boom', $e->getMessage());
        }

        $this->assertCount(0, Todo::all());
    }

    public function test_ensure_database_exists_is_noop_for_sqlite(): void
    {
        $this->assertFalse(DBConnector::ensureDatabaseExists());
    }
}
