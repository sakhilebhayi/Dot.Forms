<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Production is one SQLite file shared by the web requests, the 30-second
 * builder autosave and the cron queue worker, which are all writers. These
 * tests open a real file database with the app's own sqlite connection config
 * and check the settings that make "database is locked" unlikely.
 */
class SqliteConfigurationTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->dir = sys_get_temp_dir().'/forms-sqlite-config-'.uniqid();
        mkdir($this->dir);
        touch($this->dir.'/database.sqlite');

        config()->set('database.connections.sqlite_file', array_merge(
            config('database.connections.sqlite'),
            ['database' => $this->dir.'/database.sqlite'],
        ));
    }

    protected function tearDown(): void
    {
        DB::purge('sqlite_file');

        foreach (glob($this->dir.'/*') ?: [] as $file) {
            @unlink($file);
        }
        @rmdir($this->dir);

        parent::tearDown();
    }

    public function test_sqlite_is_the_default_connection_unless_the_environment_says_otherwise(): void
    {
        $config = file_get_contents(config_path('database.php'));

        $this->assertStringContainsString("env('DB_CONNECTION', 'sqlite')", $config);
        $this->assertSame('sqlite', config('database.connections.sqlite.driver'));
    }

    public function test_the_connection_enables_wal_a_busy_timeout_and_foreign_keys(): void
    {
        $pdo = DB::connection('sqlite_file')->getPdo();

        $this->assertSame('wal', strtolower((string) $pdo->query('pragma journal_mode')->fetchColumn()));
        $this->assertSame(5000, (int) $pdo->query('pragma busy_timeout')->fetchColumn());
        $this->assertSame(1, (int) $pdo->query('pragma synchronous')->fetchColumn(), 'synchronous should be NORMAL (1)');
        $this->assertSame(1, (int) $pdo->query('pragma foreign_keys')->fetchColumn());
    }

    public function test_the_settings_can_be_overridden_per_connection(): void
    {
        config()->set('database.connections.sqlite_file.busy_timeout', 1234);
        config()->set('database.connections.sqlite_file.journal_mode', 'delete');

        $pdo = DB::connection('sqlite_file')->getPdo();

        $this->assertSame('delete', strtolower((string) $pdo->query('pragma journal_mode')->fetchColumn()));
        $this->assertSame(1234, (int) $pdo->query('pragma busy_timeout')->fetchColumn());
    }

    public function test_a_transaction_takes_the_write_lock_when_it_begins(): void
    {
        $this->assertSame('IMMEDIATE', config('database.connections.sqlite.transaction_mode'));

        $connection = DB::connection('sqlite_file');
        $connection->statement('create table t (id integer primary key)');
        $connection->beginTransaction();

        try {
            // Another writer is refused straight away. With the default
            // DEFERRED mode this BEGIN would succeed, and a transaction that
            // reads first and writes later (the builder save does) would then
            // fail with SQLITE_BUSY that no busy_timeout can wait out.
            $other = new \PDO('sqlite:'.$this->dir.'/database.sqlite');
            $other->exec('pragma busy_timeout = 0');

            $this->expectException(\PDOException::class);
            $this->expectExceptionMessageMatches('/locked|busy/i');
            $other->exec('BEGIN IMMEDIATE TRANSACTION');
        } finally {
            $connection->rollBack();
        }
    }
}
