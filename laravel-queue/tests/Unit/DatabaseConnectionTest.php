<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\DatabaseConnection;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class DatabaseConnectionTest extends TestCase
{
    private const VARIABLES = ['DATABASE_URL', 'DB_URL', 'DB_HOST'];

    /** @var array<string, array{env: mixed, server: mixed}> */
    private array $saved = [];

    protected function setUp(): void
    {
        parent::setUp();

        foreach (self::VARIABLES as $variable) {
            $this->saved[$variable] = ['env' => $_ENV[$variable] ?? null, 'server' => $_SERVER[$variable] ?? null];
            unset($_ENV[$variable], $_SERVER[$variable]);
        }
    }

    protected function tearDown(): void
    {
        foreach ($this->saved as $variable => $values) {
            unset($_ENV[$variable], $_SERVER[$variable]);
            if ($values['env'] !== null) {
                $_ENV[$variable] = $values['env'];
            }
            if ($values['server'] !== null) {
                $_SERVER[$variable] = $values['server'];
            }
        }

        parent::tearDown();
    }

    public function test_no_database_variables_select_the_placeholder(): void
    {
        $this->assertSame(DatabaseConnection::PLACEHOLDER, DatabaseConnection::fallback());
    }

    #[DataProvider('databaseVariables')]
    public function test_any_database_variable_selects_postgres(string $variable, string $value): void
    {
        $_ENV[$variable] = $_SERVER[$variable] = $value;

        $this->assertSame(DatabaseConnection::CONFIGURED_DEFAULT, DatabaseConnection::fallback());
    }

    /** @return array<string, array{string, string}> */
    public static function databaseVariables(): array
    {
        return [
            'DATABASE_URL' => ['DATABASE_URL', 'postgres://user:secret@db.example.com:5432/app'],
            'DB_URL' => ['DB_URL', 'postgres://user:secret@db.example.com:5432/app'],
            'DB_HOST' => ['DB_HOST', 'db.example.com'],
        ];
    }
}
