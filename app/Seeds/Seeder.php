<?php

declare(strict_types=1);

namespace App\Seeds;

use App\Core\Database;

/** Base class for the idempotent seeders the installer and updates run. */
abstract class Seeder
{
    protected Database $db;
    /** @var array<int,string> */
    protected array $notes = [];

    public function __construct(?Database $db = null)
    {
        $this->db = $db ?? Database::instance();
    }

    abstract public function run(): void;

    protected function note(string $message): void
    {
        $this->notes[] = $message;
    }

    /** @return array<int,string> */
    public function notes(): array
    {
        return $this->notes;
    }

    /** Has this table already been seeded? */
    protected function isEmpty(string $table): bool
    {
        return (int) $this->db->value(
            'SELECT COUNT(*) FROM ' . $this->db->wrap($this->db->table($table)),
            [],
            0
        ) === 0;
    }

    protected function now(): string
    {
        return Database::now();
    }
}
