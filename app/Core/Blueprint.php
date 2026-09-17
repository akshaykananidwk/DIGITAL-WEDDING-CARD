<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Table definition used by migrations.
 *
 * Produces MySQL/MariaDB DDL for production and SQLite DDL for the test
 * suite, so one migration file covers both.
 */
final class Blueprint
{
    /** @var array<int,array<string,mixed>> */
    private array $columns = [];
    /** @var array<int,array{type:string,columns:array<int,string>,name:string}> */
    private array $indexes = [];
    /** @var array<int,array<string,mixed>> */
    private array $foreignKeys = [];
    /** @var array<int,string> */
    private array $primary = [];
    private ?int $lastColumn = null;
    private string $engine = 'InnoDB';
    private string $comment = '';

    public function __construct(private readonly string $table, private readonly Database $db)
    {
    }

    public function table(): string
    {
        return $this->table;
    }

    // ----------------------------------------------------------------
    //  Column types
    // ----------------------------------------------------------------

    public function id(string $name = 'id'): self
    {
        return $this->add($name, 'bigint', ['unsigned' => true, 'autoIncrement' => true, 'primary' => true]);
    }

    public function increments(string $name = 'id'): self
    {
        return $this->add($name, 'int', ['unsigned' => true, 'autoIncrement' => true, 'primary' => true]);
    }

    public function string(string $name, int $length = 255): self
    {
        return $this->add($name, 'varchar', ['length' => $length]);
    }

    public function char(string $name, int $length = 36): self
    {
        return $this->add($name, 'char', ['length' => $length]);
    }

    public function text(string $name): self
    {
        return $this->add($name, 'text');
    }

    public function mediumText(string $name): self
    {
        return $this->add($name, 'mediumtext');
    }

    public function longText(string $name): self
    {
        return $this->add($name, 'longtext');
    }

    /** JSON column - stored as JSON on MySQL 5.7+/MariaDB, TEXT on SQLite. */
    public function json(string $name): self
    {
        return $this->add($name, 'json');
    }

    public function integer(string $name): self
    {
        return $this->add($name, 'int');
    }

    public function bigInteger(string $name): self
    {
        return $this->add($name, 'bigint');
    }

    public function smallInteger(string $name): self
    {
        return $this->add($name, 'smallint');
    }

    public function tinyInteger(string $name): self
    {
        return $this->add($name, 'tinyint');
    }

    public function unsignedBigInteger(string $name): self
    {
        return $this->add($name, 'bigint', ['unsigned' => true]);
    }

    public function unsignedInteger(string $name): self
    {
        return $this->add($name, 'int', ['unsigned' => true]);
    }

    public function boolean(string $name): self
    {
        return $this->add($name, 'tinyint', ['length' => 1]);
    }

    public function decimal(string $name, int $precision = 10, int $scale = 2): self
    {
        return $this->add($name, 'decimal', ['precision' => $precision, 'scale' => $scale]);
    }

    public function float(string $name): self
    {
        return $this->add($name, 'double');
    }

    public function date(string $name): self
    {
        return $this->add($name, 'date');
    }

    public function time(string $name): self
    {
        return $this->add($name, 'time');
    }

    public function dateTime(string $name): self
    {
        return $this->add($name, 'datetime');
    }

    public function timestamp(string $name): self
    {
        return $this->add($name, 'datetime');
    }

    /** Short enum stored as VARCHAR + CHECK so both engines behave alike. */
    public function enum(string $name, array $values): self
    {
        return $this->add($name, 'enum', ['values' => array_values($values), 'length' => 40]);
    }

    public function ipAddress(string $name): self
    {
        return $this->add($name, 'varchar', ['length' => 45]);
    }

    /** created_at / updated_at pair. */
    public function timestamps(): self
    {
        $this->add('created_at', 'datetime', ['nullable' => true]);
        $this->add('updated_at', 'datetime', ['nullable' => true]);
        $this->lastColumn = null;
        return $this;
    }

    /** Soft delete marker. */
    public function softDeletes(string $name = 'deleted_at'): self
    {
        $this->add($name, 'datetime', ['nullable' => true]);
        $this->index([$name]);
        $this->lastColumn = null;
        return $this;
    }

    /** Foreign key column following the *_id convention. */
    public function foreignId(string $name): self
    {
        return $this->add($name, 'bigint', ['unsigned' => true]);
    }

    // ----------------------------------------------------------------
    //  Column modifiers (fluent, applied to the last added column)
    // ----------------------------------------------------------------

    public function nullable(bool $value = true): self
    {
        return $this->modify('nullable', $value);
    }

    public function default(mixed $value): self
    {
        return $this->modify('default', $value);
    }

    public function unsigned(): self
    {
        return $this->modify('unsigned', true);
    }

    public function comment(string $text): self
    {
        return $this->modify('comment', $text);
    }

    public function unique(array|string|null $columns = null, ?string $name = null): self
    {
        if ($columns === null) {
            $column = $this->columns[$this->lastColumn]['name'] ?? null;
            if ($column === null) {
                throw new \LogicException('unique() called without a column.');
            }
            $columns = [$column];
        }
        $columns = (array) $columns;
        $this->indexes[] = [
            'type'    => 'unique',
            'columns' => $columns,
            'name'    => $name ?? $this->indexName('unq', $columns),
        ];
        return $this;
    }

    public function index(array|string|null $columns = null, ?string $name = null): self
    {
        if ($columns === null) {
            $column = $this->columns[$this->lastColumn]['name'] ?? null;
            if ($column === null) {
                throw new \LogicException('index() called without a column.');
            }
            $columns = [$column];
        }
        $columns = (array) $columns;
        $this->indexes[] = [
            'type'    => 'index',
            'columns' => $columns,
            'name'    => $name ?? $this->indexName('idx', $columns),
        ];
        return $this;
    }

    /** Full text index - MySQL only, silently skipped on SQLite. */
    public function fullText(array|string $columns, ?string $name = null): self
    {
        $columns = (array) $columns;
        $this->indexes[] = [
            'type'    => 'fulltext',
            'columns' => $columns,
            'name'    => $name ?? $this->indexName('ft', $columns),
        ];
        return $this;
    }

    public function primaryKey(array $columns): self
    {
        $this->primary = $columns;
        return $this;
    }

    /**
     * Declare a foreign key for the most recently added column.
     *
     * @param string $onDelete cascade | set null | restrict
     */
    public function references(string $table, string $column = 'id', string $onDelete = 'cascade'): self
    {
        $local = $this->columns[$this->lastColumn]['name'] ?? null;
        if ($local === null) {
            throw new \LogicException('references() called without a column.');
        }
        $this->foreignKeys[] = [
            'local'     => $local,
            'table'     => $table,
            'column'    => $column,
            'on_delete' => strtoupper($onDelete),
            'name'      => $this->indexName('fk', [$local]),
        ];
        // A FK column is queried constantly - index it unless already indexed.
        $this->index([$local]);
        return $this;
    }

    public function engine(string $engine): self
    {
        $this->engine = $engine;
        return $this;
    }

    public function tableComment(string $comment): self
    {
        $this->comment = $comment;
        return $this;
    }

    // ----------------------------------------------------------------
    //  Internals
    // ----------------------------------------------------------------

    private function add(string $name, string $type, array $options = []): self
    {
        $this->columns[] = array_merge([
            'name'          => $name,
            'type'          => $type,
            'nullable'      => false,
            'default'       => '__none__',
            'unsigned'      => false,
            'autoIncrement' => false,
            'primary'       => false,
            'comment'       => '',
        ], $options);
        $this->lastColumn = count($this->columns) - 1;
        return $this;
    }

    private function modify(string $key, mixed $value): self
    {
        if ($this->lastColumn === null) {
            throw new \LogicException("Modifier {$key}() called without a column.");
        }
        $this->columns[$this->lastColumn][$key] = $value;
        return $this;
    }

    private function indexName(string $prefix, array $columns): string
    {
        $name = $prefix . '_' . $this->table . '_' . implode('_', $columns);
        $name = preg_replace('/[^A-Za-z0-9_]/', '', $name) ?? $name;
        // MySQL identifiers are capped at 64 characters.
        return strlen($name) <= 64 ? $name : substr($name, 0, 50) . '_' . substr(md5($name), 0, 12);
    }

    /** @return array<int,string> The statements that build this table. */
    public function toSql(): array
    {
        return $this->db->isSqlite() ? $this->toSqliteSql() : $this->toMysqlSql();
    }

    /** @return array<int,string> */
    private function toMysqlSql(): array
    {
        $table = $this->db->table($this->table);
        $lines = [];
        $primary = $this->primary;

        foreach ($this->columns as $column) {
            $lines[] = '  ' . $this->mysqlColumn($column);
            if ($column['primary']) {
                $primary[] = $column['name'];
            }
        }

        if ($primary !== []) {
            $lines[] = '  PRIMARY KEY (' . implode(', ', array_map([$this->db, 'wrap'], array_unique($primary))) . ')';
        }

        foreach ($this->uniqueIndexes() as $index) {
            $lines[] = '  UNIQUE KEY ' . $this->db->wrap($index['name'])
                . ' (' . implode(', ', array_map([$this->db, 'wrap'], $index['columns'])) . ')';
        }
        foreach ($this->plainIndexes() as $index) {
            $lines[] = '  KEY ' . $this->db->wrap($index['name'])
                . ' (' . implode(', ', array_map([$this->db, 'wrap'], $index['columns'])) . ')';
        }
        foreach ($this->fullTextIndexes() as $index) {
            $lines[] = '  FULLTEXT KEY ' . $this->db->wrap($index['name'])
                . ' (' . implode(', ', array_map([$this->db, 'wrap'], $index['columns'])) . ')';
        }
        foreach ($this->foreignKeys as $fk) {
            $lines[] = '  CONSTRAINT ' . $this->db->wrap($fk['name'])
                . ' FOREIGN KEY (' . $this->db->wrap($fk['local']) . ')'
                . ' REFERENCES ' . $this->db->wrap($this->db->table($fk['table']))
                . ' (' . $this->db->wrap($fk['column']) . ')'
                . ' ON DELETE ' . $fk['on_delete'] . ' ON UPDATE CASCADE';
        }

        $sql = 'CREATE TABLE IF NOT EXISTS ' . $this->db->wrap($table) . " (\n"
            . implode(",\n", $lines) . "\n) ENGINE=" . $this->engine
            . ' DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';
        if ($this->comment !== '') {
            $sql .= " COMMENT='" . str_replace("'", "''", $this->comment) . "'";
        }

        return [$sql];
    }

    private function mysqlColumn(array $c): string
    {
        $sql = $this->db->wrap($c['name']) . ' ' . $this->mysqlType($c);
        if ($c['unsigned'] && in_array($c['type'], ['int', 'bigint', 'smallint', 'tinyint', 'decimal', 'double'], true)) {
            $sql .= ' UNSIGNED';
        }
        $sql .= $c['nullable'] ? ' NULL' : ' NOT NULL';
        if ($c['default'] !== '__none__') {
            $sql .= ' DEFAULT ' . $this->defaultLiteral($c['default']);
        }
        if ($c['autoIncrement']) {
            $sql .= ' AUTO_INCREMENT';
        }
        if (($c['comment'] ?? '') !== '') {
            $sql .= " COMMENT '" . str_replace("'", "''", (string) $c['comment']) . "'";
        }
        return $sql;
    }

    private function mysqlType(array $c): string
    {
        return match ($c['type']) {
            'varchar'   => 'VARCHAR(' . (int) ($c['length'] ?? 255) . ')',
            'char'      => 'CHAR(' . (int) ($c['length'] ?? 36) . ')',
            'enum'      => 'VARCHAR(' . (int) ($c['length'] ?? 40) . ')',
            'decimal'   => 'DECIMAL(' . (int) ($c['precision'] ?? 10) . ',' . (int) ($c['scale'] ?? 2) . ')',
            'tinyint'   => 'TINYINT(' . (int) ($c['length'] ?? 4) . ')',
            'json'      => 'JSON',
            default     => strtoupper((string) $c['type']),
        };
    }

    /** @return array<int,string> */
    private function toSqliteSql(): array
    {
        $table = $this->db->table($this->table);
        $lines = [];
        $primary = $this->primary;
        $autoPrimary = null;

        foreach ($this->columns as $column) {
            if ($column['autoIncrement']) {
                $autoPrimary = $column['name'];
                $lines[] = '  ' . $this->db->wrap($column['name']) . ' INTEGER PRIMARY KEY AUTOINCREMENT';
                continue;
            }
            if ($column['primary']) {
                $primary[] = $column['name'];
            }
            $lines[] = '  ' . $this->sqliteColumn($column);
        }

        if ($autoPrimary === null && $primary !== []) {
            $lines[] = '  PRIMARY KEY (' . implode(', ', array_map([$this->db, 'wrap'], array_unique($primary))) . ')';
        }

        foreach ($this->uniqueIndexes() as $index) {
            $lines[] = '  UNIQUE (' . implode(', ', array_map([$this->db, 'wrap'], $index['columns'])) . ')';
        }
        foreach ($this->foreignKeys as $fk) {
            $lines[] = '  FOREIGN KEY (' . $this->db->wrap($fk['local']) . ')'
                . ' REFERENCES ' . $this->db->wrap($this->db->table($fk['table']))
                . ' (' . $this->db->wrap($fk['column']) . ')'
                . ' ON DELETE ' . ($fk['on_delete'] === 'SET NULL' ? 'SET NULL' : $fk['on_delete']);
        }

        $statements = [
            'CREATE TABLE IF NOT EXISTS ' . $this->db->wrap($table) . " (\n" . implode(",\n", $lines) . "\n)",
        ];

        // SQLite creates non-unique indexes separately.
        foreach ($this->plainIndexes() as $index) {
            $statements[] = 'CREATE INDEX IF NOT EXISTS ' . $this->db->wrap($index['name'])
                . ' ON ' . $this->db->wrap($table)
                . ' (' . implode(', ', array_map([$this->db, 'wrap'], $index['columns'])) . ')';
        }
        // FULLTEXT has no SQLite equivalent; LIKE search is used instead.

        return $statements;
    }

    private function sqliteColumn(array $c): string
    {
        $sql = $this->db->wrap($c['name']) . ' ' . $this->sqliteType($c);
        $sql .= $c['nullable'] ? ' NULL' : ' NOT NULL';
        if ($c['default'] !== '__none__') {
            $sql .= ' DEFAULT ' . $this->defaultLiteral($c['default']);
        }
        return $sql;
    }

    private function sqliteType(array $c): string
    {
        return match ($c['type']) {
            'varchar', 'char', 'enum'            => 'TEXT',
            'text', 'mediumtext', 'longtext'     => 'TEXT',
            'json'                               => 'TEXT',
            'int', 'bigint', 'smallint', 'tinyint' => 'INTEGER',
            'decimal', 'double'                  => 'REAL',
            'date', 'time', 'datetime'           => 'TEXT',
            default                              => 'TEXT',
        };
    }

    private function defaultLiteral(mixed $value): string
    {
        if ($value === null) {
            return 'NULL';
        }
        if (is_bool($value)) {
            return $value ? '1' : '0';
        }
        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }
        if ($value === 'CURRENT_TIMESTAMP') {
            return 'CURRENT_TIMESTAMP';
        }
        return "'" . str_replace("'", "''", (string) $value) . "'";
    }

    /**
     * Deduplicated index list.
     *
     * references() and the fluent index() modifier can both request an index
     * for the same column, and an explicit composite index often already
     * covers a single-column one as its leading prefix. Both cases would make
     * MySQL reject the CREATE TABLE ("duplicate key name"), so they are
     * resolved here and index names are forced unique.
     *
     * @return array<int,array{type:string,columns:array<int,string>,name:string}>
     */
    private function filterIndexes(string $type): array
    {
        $candidates = array_values(array_filter(
            $this->indexes,
            static fn (array $index): bool => $index['type'] === $type
        ));

        // Longest first so a composite survives and its prefix is dropped.
        usort($candidates, static fn ($a, $b) => count($b['columns']) <=> count($a['columns']));

        $kept = [];
        foreach ($candidates as $index) {
            if ($this->isCoveredBy($index, $kept)) {
                continue;
            }
            // A plain index that duplicates a unique index is pointless.
            if ($type === 'index' && $this->isCoveredBy($index, $this->rawIndexesOfType('unique'))) {
                continue;
            }
            $kept[] = $index;
        }

        return $this->uniquifyNames($kept, $type);
    }

    /** @return array<int,array{type:string,columns:array<int,string>,name:string}> */
    private function rawIndexesOfType(string $type): array
    {
        return array_values(array_filter(
            $this->indexes,
            static fn (array $index): bool => $index['type'] === $type
        ));
    }

    /** True when an existing index already starts with these columns. */
    private function isCoveredBy(array $index, array $existing): bool
    {
        foreach ($existing as $other) {
            if ($other['columns'] === $index['columns']) {
                return true;
            }
            if (count($other['columns']) > count($index['columns'])
                && array_slice($other['columns'], 0, count($index['columns'])) === $index['columns']) {
                return true;
            }
        }
        return false;
    }

    /**
     * Index names must be unique per table (and, on SQLite, per database), so
     * a collision gets a numeric suffix rather than breaking the migration.
     */
    private function uniquifyNames(array $indexes, string $type): array
    {
        $used = [];
        foreach ($indexes as $i => $index) {
            $name = $index['name'];
            if (isset($used[$name])) {
                $suffix = 2;
                while (isset($used[$name . '_' . $suffix])) {
                    $suffix++;
                }
                $name = $name . '_' . $suffix;
            }
            $used[$name] = true;
            $indexes[$i]['name'] = $name;
        }
        // Keep the declaration order stable for readable DDL.
        usort($indexes, static fn ($a, $b) => strcmp($a['name'], $b['name']));
        return $indexes;
    }

    private function uniqueIndexes(): array
    {
        return $this->filterIndexes('unique');
    }

    private function plainIndexes(): array
    {
        return $this->filterIndexes('index');
    }

    private function fullTextIndexes(): array
    {
        return $this->filterIndexes('fulltext');
    }
}
