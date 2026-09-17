<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Versioned migration runner.
 *
 * Every file in database/migrations is named `YYYY_MM_DD_NNNNNN_name.php` and
 * returns an object (or anonymous class) exposing up() and, optionally,
 * down(). Applied migrations are recorded in the `migrations` table with a
 * batch number, so an update only ever runs what is new.
 */
final class Migrator
{
    /** @var array<int,string> */
    private array $notes = [];

    public function __construct(
        private readonly Database $db,
        private readonly string $path = ''
    ) {
    }

    private function dir(): string
    {
        return $this->path !== '' ? $this->path : DATABASE_PATH . '/migrations';
    }

    public function ensureRepository(): void
    {
        if ($this->db->tableExists('migrations')) {
            return;
        }
        Schema::use($this->db);
        Schema::create('migrations', static function (Blueprint $table): void {
            $table->id();
            $table->string('migration', 255)->unique();
            $table->integer('batch');
            $table->string('checksum', 64)->nullable();
            $table->integer('duration_ms')->default(0);
            $table->timestamp('ran_at')->nullable();
        });
        $this->note('Created migrations table.');
    }

    /** @return array<int,string> file names present on disk, in order */
    public function available(): array
    {
        $files = glob($this->dir() . '/*.php') ?: [];
        $names = array_map(static fn ($f) => basename($f, '.php'), $files);
        sort($names, SORT_STRING);
        return $names;
    }

    /** @return array<int,string> file names already applied */
    public function applied(): array
    {
        if (!$this->db->tableExists('migrations')) {
            return [];
        }
        return array_map(
            'strval',
            $this->db->column('SELECT migration FROM migrations ORDER BY id ASC')
        );
    }

    /** @return array<int,string> */
    public function pending(): array
    {
        return array_values(array_diff($this->available(), $this->applied()));
    }

    public function currentBatch(): int
    {
        if (!$this->db->tableExists('migrations')) {
            return 0;
        }
        return (int) $this->db->value('SELECT COALESCE(MAX(batch), 0) FROM migrations', [], 0);
    }

    /**
     * Run every pending migration.
     *
     * @return array{ran:array<int,string>,batch:int,notes:array<int,string>}
     */
    public function run(): array
    {
        $this->ensureRepository();
        Schema::use($this->db);

        $pending = $this->pending();
        if ($pending === []) {
            $this->note('Nothing to migrate.');
            return ['ran' => [], 'batch' => $this->currentBatch(), 'notes' => $this->notes];
        }

        $batch = $this->currentBatch() + 1;
        $ran = [];

        foreach ($pending as $name) {
            $file = $this->dir() . '/' . $name . '.php';
            if (!is_file($file)) {
                continue;
            }
            $started = microtime(true);
            $migration = require $file;
            if (!is_object($migration) || !method_exists($migration, 'up')) {
                throw new \RuntimeException("Migration {$name} does not return a migration object.");
            }

            // MySQL DDL is not transactional, so each migration is atomic on
            // its own and failures are surfaced (and rolled back by the
            // update system's database restore) rather than half applied.
            try {
                $migration->up($this->db);
            } catch (\Throwable $e) {
                Logger::error("Migration {$name} failed: " . $e->getMessage());
                throw new \RuntimeException("Migration {$name} failed: " . $e->getMessage(), 0, $e);
            }

            $this->db->insert('migrations', [
                'migration'   => $name,
                'batch'       => $batch,
                'checksum'    => hash_file('sha256', $file) ?: null,
                'duration_ms' => (int) round((microtime(true) - $started) * 1000),
                'ran_at'      => Database::now(),
            ]);

            $ran[] = $name;
            $this->note("Migrated: {$name}");
        }

        return ['ran' => $ran, 'batch' => $batch, 'notes' => $this->notes];
    }

    /** Roll back the most recent batch (used by update rollback / dev). */
    public function rollbackLastBatch(): array
    {
        if (!$this->db->tableExists('migrations')) {
            return [];
        }
        Schema::use($this->db);
        $batch = $this->currentBatch();
        if ($batch === 0) {
            return [];
        }
        $names = array_map('strval', $this->db->column(
            'SELECT migration FROM migrations WHERE batch = :batch ORDER BY id DESC',
            ['batch' => $batch]
        ));

        $rolled = [];
        foreach ($names as $name) {
            $file = $this->dir() . '/' . $name . '.php';
            if (is_file($file)) {
                $migration = require $file;
                if (is_object($migration) && method_exists($migration, 'down')) {
                    $migration->down($this->db);
                }
            }
            $this->db->delete('migrations', ['migration' => $name]);
            $rolled[] = $name;
            $this->note("Rolled back: {$name}");
        }
        return $rolled;
    }

    private function note(string $message): void
    {
        $this->notes[] = $message;
        Logger::info('[migrator] ' . $message);
    }

    /** @return array<int,string> */
    public function notes(): array
    {
        return $this->notes;
    }
}
