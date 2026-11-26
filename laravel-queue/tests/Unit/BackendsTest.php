<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\Backends;
use PHPUnit\Framework\TestCase;

class BackendsTest extends TestCase
{
    private const VARIABLES = ['REDIS_URL', 'DATABASE_URL', 'DB_URL', 'DB_HOST', 'DB_CONNECTION'];

    /** @var array<string, array{env: mixed, server: mixed, putenv: string|false}> */
    private array $saved = [];

    protected function setUp(): void
    {
        parent::setUp();

        foreach (self::VARIABLES as $variable) {
            $this->saved[$variable] = ['env' => $_ENV[$variable] ?? null, 'server' => $_SERVER[$variable] ?? null, 'putenv' => getenv($variable)];
            unset($_ENV[$variable], $_SERVER[$variable]);
            putenv($variable);
        }
    }

    protected function tearDown(): void
    {
        foreach ($this->saved as $variable => $values) {
            unset($_ENV[$variable], $_SERVER[$variable]);
            putenv($variable);
            if ($values['env'] !== null) {
                $_ENV[$variable] = $values['env'];
            }
            if ($values['server'] !== null) {
                $_SERVER[$variable] = $values['server'];
            }
            if ($values['putenv'] !== false) {
                putenv("{$variable}={$values['putenv']}");
            }
        }

        parent::tearDown();
    }

    private function set(string $variable, string $value): void
    {
        $_ENV[$variable] = $_SERVER[$variable] = $value;
    }

    public function test_valkey_moves_queue_cache_and_session_to_redis(): void
    {
        // What Sakala injects: no database number in the URL.
        $this->set('REDIS_URL', 'redis://:secret@10.213.0.2:6379');

        $this->assertSame(['redis', 'redis', 'redis'], [Backends::queue(), Backends::cache(), Backends::session()]);
    }

    public function test_a_database_without_valkey_queues_in_the_jobs_table(): void
    {
        // What an attached Sakala database injects.
        $this->set('DB_CONNECTION', 'pgsql');
        $this->set('DATABASE_URL', 'postgresql://user:secret@172.17.0.1:5432/app?sslmode=require');

        $this->assertSame(['database', 'file', 'cookie'], [Backends::queue(), Backends::cache(), Backends::session()]);
    }

    public function test_an_external_database_url_alone_also_queues_in_the_database(): void
    {
        $this->set('DATABASE_URL', 'postgres://user:secret@db.example.com:5432/app');

        $this->assertSame('database', Backends::queue());
    }

    public function test_without_database_or_valkey_the_queue_is_sync(): void
    {
        $this->assertSame(['sync', 'file', 'cookie'], [Backends::queue(), Backends::cache(), Backends::session()]);
    }

    public function test_an_empty_redis_url_does_not_count(): void
    {
        $this->set('REDIS_URL', '');

        $this->assertFalse(Backends::valkeyConfigured());
    }
}
