<?php

declare(strict_types=1);

namespace Modules\Tenancy\Tests\Feature;

use Modules\Tenancy\Database\SqliteDatabaseManager;
use Modules\Tenancy\Models\School;
use Tests\TestCase;

/** A school's SQLite database goes together with its WAL sidecar files. */
class SqliteSidecarCleanupTest extends TestCase
{
    private string $database;

    protected function setUp(): void
    {
        parent::setUp();
        config(['tenancy.database.prefix' => '../storage/framework/testing/wal_', 'tenancy.database.suffix' => '.sqlite']);
        $this->database = database_path($this->school()->database()->getName());
    }

    protected function tearDown(): void
    {
        foreach (glob(base_path('storage/framework/testing/wal_*')) ?: [] as $file) {
            @unlink($file);
        }
        parent::tearDown();
    }

    private function school(): School
    {
        return new School(['id' => 'walcheck']);
    }

    public function test_schools_use_the_sidecar_aware_sqlite_manager(): void
    {
        $this->assertSame(SqliteDatabaseManager::class, config('tenancy.database.managers.sqlite'));
    }

    public function test_deleting_a_school_database_removes_its_wal_and_shm_files(): void
    {
        foreach (['', '-wal', '-shm'] as $suffix) {
            touch($this->database.$suffix);
        }

        $this->assertTrue(app(SqliteDatabaseManager::class)->deleteDatabase($this->school()));

        foreach (['', '-wal', '-shm'] as $suffix) {
            $this->assertFileDoesNotExist($this->database.$suffix);
        }
    }

    public function test_opening_a_school_clears_a_stale_log_left_by_an_earlier_one_of_the_same_name(): void
    {
        file_put_contents($this->database.'-wal', 'log from a school whose creation was rolled back');
        touch($this->database.'-shm');

        app(SqliteDatabaseManager::class)->createDatabase($this->school());

        $this->assertFileExists($this->database);
        $this->assertSame(0, filesize($this->database));
        $this->assertFileDoesNotExist($this->database.'-wal');
        $this->assertFileDoesNotExist($this->database.'-shm');
    }
}
