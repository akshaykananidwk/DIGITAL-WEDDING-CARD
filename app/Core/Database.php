<?php

declare(strict_types=1);

namespace App\Core;

use PDO;
use PDOException;
use PDOStatement;

/**
 * Thin PDO layer. Every query in the application goes through here and every
 * value is bound - there is no string interpolation of user input anywhere.
 *
 * MySQL/MariaDB is the production target. SQLite is supported so the test
 * suite and local development can run without a database server.
 */
final class Database
{
    private static ?self $instance = null;

    private ?PDO $pdo = null;
    private string $driver = 'mysql';
    private int $transactions = 0;
    private int $queryCount = 0;
    private float $queryTime = 0.0;
    /** @var array<int,array{sql:string,time:float}> */
    private array $log = [];
    private bool $logQueries = false;

    private function __construct(private array $config)
    {
        $this->driver = (string) ($config['driver'] ?? 'mysql');
        $this->logQueries = (bool) Config::get('app.debug', false);
    }

    public static function instance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self((array) Config::get('database', []));
        }
        return self::$instance;
    }

    /** Used by the installer to test credentials before they are saved. */
    public static function withConfig(array $config): self
    {
        return new self($config);
    }

    public static function reset(): void
    {
        self::$instance = null;
    }

    public function config(): array
    {
        return $this->config;
    }

    public function driver(): string
    {
        return $this->driver;
    }

    public function isMysql(): bool
    {
        return $this->driver === 'mysql';
    }

    public function isSqlite(): bool
    {
        return $this->driver === 'sqlite';
    }

    public function pdo(): PDO
    {
        if ($this->pdo instanceof PDO) {
            return $this->pdo;
        }

        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::ATTR_STRINGIFY_FETCHES  => false,
        ];

        try {
            if ($this->driver === 'sqlite') {
                $path = (string) ($this->config['database'] ?? ':memory:');
                $this->pdo = new PDO('sqlite:' . $path, null, null, $options);
                $this->pdo->exec('PRAGMA foreign_keys = ON');
                $this->pdo->exec('PRAGMA journal_mode = WAL');
                $this->pdo->exec('PRAGMA busy_timeout = 5000');
            } else {
                $this->pdo = new PDO(
                    $this->dsn(),
                    (string) ($this->config['username'] ?? ''),
                    (string) ($this->config['password'] ?? ''),
                    $options
                );
                $charset = (string) ($this->config['charset'] ?? 'utf8mb4');
                $collation = (string) ($this->config['collation'] ?? 'utf8mb4_unicode_ci');
                $this->pdo->exec("SET NAMES {$charset} COLLATE {$collation}");
                $this->pdo->exec("SET SESSION sql_mode = 'STRICT_TRANS_TABLES,NO_ENGINE_SUBSTITUTION'");
                $this->pdo->exec("SET SESSION time_zone = '+05:30'");
            }
        } catch (PDOException $e) {
            // The message can contain the password - never let it bubble up.
            Logger::critical('Database connection failed', ['code' => $e->getCode()]);
            throw new \RuntimeException('Database connection failed. Please check the configuration.', 0, $e);
        }

        return $this->pdo;
    }

    public function dsn(): string
    {
        if ($this->driver === 'sqlite') {
            return 'sqlite:' . (string) ($this->config['database'] ?? ':memory:');
        }
        $socket = $this->config['socket'] ?? null;
        $database = (string) ($this->config['database'] ?? '');
        $charset = (string) ($this->config['charset'] ?? 'utf8mb4');

        if (is_string($socket) && $socket !== '') {
            return "mysql:unix_socket={$socket};dbname={$database};charset={$charset}";
        }
        $host = (string) ($this->config['host'] ?? '127.0.0.1');
        $port = (int) ($this->config['port'] ?? 3306);
        return "mysql:host={$host};port={$port};dbname={$database};charset={$charset}";
    }

    /** Connect without selecting a database (installer: create if missing). */
    public function serverPdo(): PDO
    {
        if ($this->driver === 'sqlite') {
            return $this->pdo();
        }
        $host = (string) ($this->config['host'] ?? '127.0.0.1');
        $port = (int) ($this->config['port'] ?? 3306);
        $charset = (string) ($this->config['charset'] ?? 'utf8mb4');
        return new PDO(
            "mysql:host={$host};port={$port};charset={$charset}",
            (string) ($this->config['username'] ?? ''),
            (string) ($this->config['password'] ?? ''),
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
    }

    // ------------------------------------------------------------------
    //  Query execution
    // ------------------------------------------------------------------

    public function run(string $sql, array $bindings = []): PDOStatement
    {
        $started = microtime(true);
        try {
            $statement = $this->pdo()->prepare($sql);
            foreach ($bindings as $key => $value) {
                $param = is_int($key) ? $key + 1 : ':' . ltrim((string) $key, ':');
                $statement->bindValue($param, $value, $this->pdoType($value));
            }
            $statement->execute();
        } catch (PDOException $e) {
            Logger::error('Query failed: ' . $e->getMessage(), [
                'sql'      => $sql,
                'bindings' => Logger::scrub($bindings),
            ]);
            throw new \RuntimeException('A database error occurred.', 0, $e);
        }

        $elapsed = microtime(true) - $started;
        $this->queryCount++;
        $this->queryTime += $elapsed;
        if ($this->logQueries && count($this->log) < 200) {
            $this->log[] = ['sql' => $sql, 'time' => $elapsed];
        }

        return $statement;
    }

    private function pdoType(mixed $value): int
    {
        return match (true) {
            is_int($value)  => PDO::PARAM_INT,
            is_bool($value) => PDO::PARAM_INT,
            $value === null => PDO::PARAM_NULL,
            default         => PDO::PARAM_STR,
        };
    }

    /** @return array<int,array<string,mixed>> */
    public function select(string $sql, array $bindings = []): array
    {
        return $this->run($sql, $bindings)->fetchAll();
    }

    /** @return array<string,mixed>|null */
    public function first(string $sql, array $bindings = []): ?array
    {
        $row = $this->run($sql, $bindings)->fetch();
        return is_array($row) ? $row : null;
    }

    public function value(string $sql, array $bindings = [], mixed $default = null): mixed
    {
        $row = $this->run($sql, $bindings)->fetch(PDO::FETCH_NUM);
        return is_array($row) ? $row[0] : $default;
    }

    /** @return array<int,mixed> */
    public function column(string $sql, array $bindings = []): array
    {
        return $this->run($sql, $bindings)->fetchAll(PDO::FETCH_COLUMN);
    }

    /** Key/value pairs from a two column result. */
    public function pairs(string $sql, array $bindings = []): array
    {
        $out = [];
        foreach ($this->run($sql, $bindings)->fetchAll(PDO::FETCH_NUM) as $row) {
            $out[$row[0]] = $row[1] ?? null;
        }
        return $out;
    }

    public function execute(string $sql, array $bindings = []): int
    {
        return $this->run($sql, $bindings)->rowCount();
    }

    public function insert(string $table, array $data): int
    {
        $table = $this->table($table);
        $columns = array_keys($data);
        $placeholders = array_map(static fn ($c) => ':' . $c, $columns);
        $sql = 'INSERT INTO ' . $this->wrap($table)
            . ' (' . implode(', ', array_map([$this, 'wrap'], $columns)) . ')'
            . ' VALUES (' . implode(', ', $placeholders) . ')';
        $this->run($sql, $data);
        return (int) $this->pdo()->lastInsertId();
    }

    /** Multi-row insert in a single statement (used by the seeders). */
    public function insertMany(string $table, array $rows): int
    {
        if ($rows === []) {
            return 0;
        }
        $table = $this->table($table);
        $columns = array_keys($rows[0]);
        $chunks = array_chunk($rows, 200);
        $total = 0;
        foreach ($chunks as $chunk) {
            $bindings = [];
            $groups = [];
            foreach ($chunk as $i => $row) {
                $names = [];
                foreach ($columns as $column) {
                    $key = $column . '_' . $i;
                    $names[] = ':' . $key;
                    $bindings[$key] = $row[$column] ?? null;
                }
                $groups[] = '(' . implode(', ', $names) . ')';
            }
            $sql = 'INSERT INTO ' . $this->wrap($table)
                . ' (' . implode(', ', array_map([$this, 'wrap'], $columns)) . ') VALUES '
                . implode(', ', $groups);
            $total += $this->execute($sql, $bindings);
        }
        return $total;
    }

    public function update(string $table, array $data, array $where): int
    {
        if ($data === []) {
            return 0;
        }
        $table = $this->table($table);
        $sets = [];
        $bindings = [];
        foreach ($data as $column => $value) {
            $sets[] = $this->wrap($column) . ' = :set_' . $column;
            $bindings['set_' . $column] = $value;
        }
        [$clause, $whereBindings] = $this->whereClause($where);
        $sql = 'UPDATE ' . $this->wrap($table) . ' SET ' . implode(', ', $sets) . $clause;
        return $this->execute($sql, array_merge($bindings, $whereBindings));
    }

    public function delete(string $table, array $where): int
    {
        [$clause, $bindings] = $this->whereClause($where);
        return $this->execute('DELETE FROM ' . $this->wrap($this->table($table)) . $clause, $bindings);
    }

    /**
     * Insert, or update the listed columns when the unique key already exists.
     * Portable across MySQL and SQLite.
     */
    public function upsert(string $table, array $data, array $uniqueBy, array $updateColumns = []): void
    {
        $table = $this->table($table);
        $columns = array_keys($data);
        $placeholders = array_map(static fn ($c) => ':' . $c, $columns);
        $updateColumns = $updateColumns !== [] ? $updateColumns : array_diff($columns, $uniqueBy);

        $sql = 'INSERT INTO ' . $this->wrap($table)
            . ' (' . implode(', ', array_map([$this, 'wrap'], $columns)) . ')'
            . ' VALUES (' . implode(', ', $placeholders) . ')';

        if ($updateColumns === []) {
            $sql .= $this->isSqlite()
                ? ' ON CONFLICT (' . implode(', ', array_map([$this, 'wrap'], $uniqueBy)) . ') DO NOTHING'
                : ' ON DUPLICATE KEY UPDATE ' . $this->wrap($uniqueBy[0]) . ' = ' . $this->wrap($uniqueBy[0]);
            $this->run($sql, $data);
            return;
        }

        if ($this->isSqlite()) {
            $sets = array_map(
                fn ($c) => $this->wrap($c) . ' = excluded.' . $this->wrap($c),
                $updateColumns
            );
            $sql .= ' ON CONFLICT (' . implode(', ', array_map([$this, 'wrap'], $uniqueBy)) . ')'
                . ' DO UPDATE SET ' . implode(', ', $sets);
        } else {
            $sets = array_map(
                fn ($c) => $this->wrap($c) . ' = VALUES(' . $this->wrap($c) . ')',
                $updateColumns
            );
            $sql .= ' ON DUPLICATE KEY UPDATE ' . implode(', ', $sets);
        }

        $this->run($sql, $data);
    }

    /** Atomic counter bump (analytics hot path). */
    public function increment(string $table, string $column, array $where, int $amount = 1): int
    {
        [$clause, $bindings] = $this->whereClause($where);
        $sql = 'UPDATE ' . $this->wrap($this->table($table))
            . ' SET ' . $this->wrap($column) . ' = ' . $this->wrap($column) . ' + :__amount'
            . $clause;
        return $this->execute($sql, array_merge(['__amount' => $amount], $bindings));
    }

    /** @return array{0:string,1:array<string,mixed>} */
    private function whereClause(array $where): array
    {
        if ($where === []) {
            throw new \InvalidArgumentException('Refusing to run an unbounded write query.');
        }
        $parts = [];
        $bindings = [];
        foreach ($where as $column => $value) {
            if ($value === null) {
                $parts[] = $this->wrap($column) . ' IS NULL';
                continue;
            }
            if (is_array($value)) {
                $names = [];
                foreach (array_values($value) as $i => $item) {
                    $key = 'w_' . $column . '_' . $i;
                    $names[] = ':' . $key;
                    $bindings[$key] = $item;
                }
                $parts[] = $this->wrap($column) . ' IN (' . implode(', ', $names ?: [':__empty']) . ')';
                if ($names === []) {
                    $bindings['__empty'] = null;
                }
                continue;
            }
            $key = 'w_' . $column;
            $parts[] = $this->wrap($column) . ' = :' . $key;
            $bindings[$key] = $value;
        }
        return [' WHERE ' . implode(' AND ', $parts), $bindings];
    }

    // ------------------------------------------------------------------
    //  Transactions
    // ------------------------------------------------------------------

    public function beginTransaction(): void
    {
        if ($this->transactions === 0) {
            $this->pdo()->beginTransaction();
        } else {
            $this->pdo()->exec('SAVEPOINT trans' . ($this->transactions + 1));
        }
        $this->transactions++;
    }

    public function commit(): void
    {
        if ($this->transactions === 1) {
            $this->pdo()->commit();
        } elseif ($this->transactions > 1) {
            $this->pdo()->exec('RELEASE SAVEPOINT trans' . $this->transactions);
        }
        $this->transactions = max(0, $this->transactions - 1);
    }

    public function rollBack(): void
    {
        if ($this->transactions === 1) {
            if ($this->pdo()->inTransaction()) {
                $this->pdo()->rollBack();
            }
        } elseif ($this->transactions > 1) {
            $this->pdo()->exec('ROLLBACK TO SAVEPOINT trans' . $this->transactions);
        }
        $this->transactions = max(0, $this->transactions - 1);
    }

    /** Run a closure inside a transaction, rolling back on any exception. */
    public function transaction(callable $callback): mixed
    {
        $this->beginTransaction();
        try {
            $result = $callback($this);
            $this->commit();
            return $result;
        } catch (\Throwable $e) {
            $this->rollBack();
            throw $e;
        }
    }

    // ------------------------------------------------------------------
    //  Introspection
    // ------------------------------------------------------------------

    public function tableExists(string $table): bool
    {
        $table = $this->table($table);
        try {
            if ($this->isSqlite()) {
                return $this->value(
                    "SELECT COUNT(*) FROM sqlite_master WHERE type='table' AND name = :name",
                    ['name' => $table]
                ) > 0;
            }
            return $this->value(
                'SELECT COUNT(*) FROM information_schema.tables
                 WHERE table_schema = DATABASE() AND table_name = :name',
                ['name' => $table]
            ) > 0;
        } catch (\Throwable) {
            return false;
        }
    }

    public function columnExists(string $table, string $column): bool
    {
        foreach ($this->columns($table) as $existing) {
            if (strcasecmp($existing, $column) === 0) {
                return true;
            }
        }
        return false;
    }

    /** @return array<int,string> */
    public function columns(string $table): array
    {
        $table = $this->table($table);
        try {
            if ($this->isSqlite()) {
                return array_map(
                    static fn ($row) => (string) $row['name'],
                    $this->select('PRAGMA table_info(' . $this->wrap($table) . ')')
                );
            }
            return $this->column(
                'SELECT column_name FROM information_schema.columns
                 WHERE table_schema = DATABASE() AND table_name = :name ORDER BY ordinal_position',
                ['name' => $table]
            );
        } catch (\Throwable) {
            return [];
        }
    }

    /** @return array<int,string> */
    public function tables(): array
    {
        if ($this->isSqlite()) {
            return $this->column(
                "SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%' ORDER BY name"
            );
        }
        return $this->column(
            'SELECT table_name FROM information_schema.tables
             WHERE table_schema = DATABASE() AND table_type = "BASE TABLE" ORDER BY table_name'
        );
    }

    public function version(): string
    {
        try {
            if ($this->isSqlite()) {
                return 'SQLite ' . (string) $this->value('SELECT sqlite_version()');
            }
            return (string) $this->value('SELECT VERSION()');
        } catch (\Throwable) {
            return 'unknown';
        }
    }

    /** Approximate size of the schema in bytes (used by System → Health). */
    public function sizeBytes(): int
    {
        try {
            if ($this->isSqlite()) {
                $path = (string) ($this->config['database'] ?? '');
                return is_file($path) ? (int) filesize($path) : 0;
            }
            return (int) $this->value(
                'SELECT COALESCE(SUM(data_length + index_length), 0)
                 FROM information_schema.tables WHERE table_schema = DATABASE()'
            );
        } catch (\Throwable) {
            return 0;
        }
    }

    // ------------------------------------------------------------------
    //  Helpers
    // ------------------------------------------------------------------

    /** Apply the configured table prefix once. */
    public function table(string $table): string
    {
        $prefix = (string) ($this->config['prefix'] ?? '');
        if ($prefix === '' || str_starts_with($table, $prefix)) {
            return $table;
        }
        return $prefix . $table;
    }

    /** Quote an identifier. Only [A-Za-z0-9_.] is ever allowed through. */
    public function wrap(string $identifier): string
    {
        if ($identifier === '*') {
            return '*';
        }
        $clean = preg_replace('/[^A-Za-z0-9_\.]/', '', $identifier) ?? '';
        if ($clean === '') {
            throw new \InvalidArgumentException('Invalid SQL identifier.');
        }
        $quote = $this->isSqlite() ? '"' : '`';
        return implode('.', array_map(
            static fn ($p) => $quote . $p . $quote,
            explode('.', $clean)
        ));
    }

    /** Current timestamp in the application timezone, MySQL DATETIME format. */
    public static function now(): string
    {
        return date('Y-m-d H:i:s');
    }

    public function queryCount(): int
    {
        return $this->queryCount;
    }

    public function queryTime(): float
    {
        return $this->queryTime;
    }

    public function queryLog(): array
    {
        return $this->log;
    }

    /**
     * Paginate any SELECT. The caller supplies the body of the query after
     * FROM so the count query can reuse it - no N+1, one count + one page.
     *
     * @return array{rows:array<int,array<string,mixed>>,total:int,page:int,per_page:int,pages:int}
     */
    public function paginate(
        string $selectColumns,
        string $fromAndWhere,
        array $bindings,
        int $page,
        int $perPage,
        string $orderBy = ''
    ): array {
        $page = max(1, $page);
        $perPage = max(1, min(200, $perPage));
        $total = (int) $this->value("SELECT COUNT(*) FROM {$fromAndWhere}", $bindings, 0);
        $pages = (int) max(1, (int) ceil($total / $perPage));
        $offset = ($page - 1) * $perPage;

        $sql = "SELECT {$selectColumns} FROM {$fromAndWhere}";
        if ($orderBy !== '') {
            $sql .= ' ORDER BY ' . $orderBy;
        }
        $sql .= ' LIMIT ' . $perPage . ' OFFSET ' . $offset;

        return [
            'rows'     => $total === 0 ? [] : $this->select($sql, $bindings),
            'total'    => $total,
            'page'     => $page,
            'per_page' => $perPage,
            'pages'    => $pages,
        ];
    }
}
