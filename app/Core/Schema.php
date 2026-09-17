<?php

declare(strict_types=1);

namespace App\Core;

/** Migration facade: create/alter/drop tables portably. */
final class Schema
{
    private static ?Database $db = null;

    public static function use(Database $db): void
    {
        self::$db = $db;
    }

    private static function db(): Database
    {
        return self::$db ?? Database::instance();
    }

    public static function create(string $table, callable $definition): void
    {
        $db = self::db();
        $blueprint = new Blueprint($table, $db);
        $definition($blueprint);
        foreach ($blueprint->toSql() as $sql) {
            $db->pdo()->exec($sql);
        }
    }

    public static function hasTable(string $table): bool
    {
        return self::db()->tableExists($table);
    }

    public static function hasColumn(string $table, string $column): bool
    {
        return self::db()->columnExists($table, $column);
    }

    public static function drop(string $table): void
    {
        $db = self::db();
        $db->pdo()->exec('DROP TABLE IF EXISTS ' . $db->wrap($db->table($table)));
    }

    /** Add a single column to an existing table (used by upgrade migrations). */
    public static function addColumn(string $table, string $column, callable $definition): void
    {
        $db = self::db();
        if ($db->columnExists($table, $column)) {
            return;
        }
        // Build a throwaway table definition to reuse the column SQL generator.
        $blueprint = new Blueprint('__tmp_' . $table, $db);
        $definition($blueprint);
        $sqlParts = self::extractColumnDefinitions($blueprint->toSql()[0] ?? '');
        foreach ($sqlParts as $part) {
            if (str_contains($part, $db->wrap($column))) {
                $db->pdo()->exec(
                    'ALTER TABLE ' . $db->wrap($db->table($table)) . ' ADD COLUMN ' . trim($part)
                );
                return;
            }
        }
        throw new \RuntimeException("Could not derive DDL for column {$table}.{$column}");
    }

    public static function addIndex(string $table, array $columns, bool $unique = false, ?string $name = null): void
    {
        $db = self::db();
        $name ??= ($unique ? 'unq_' : 'idx_') . $table . '_' . implode('_', $columns);
        $name = substr(preg_replace('/[^A-Za-z0-9_]/', '', $name) ?? $name, 0, 64);
        $cols = implode(', ', array_map([$db, 'wrap'], $columns));
        try {
            if ($db->isSqlite()) {
                $db->pdo()->exec(
                    'CREATE ' . ($unique ? 'UNIQUE ' : '') . 'INDEX IF NOT EXISTS ' . $db->wrap($name)
                    . ' ON ' . $db->wrap($db->table($table)) . ' (' . $cols . ')'
                );
                return;
            }
            $db->pdo()->exec(
                'ALTER TABLE ' . $db->wrap($db->table($table))
                . ' ADD ' . ($unique ? 'UNIQUE ' : '') . 'INDEX ' . $db->wrap($name) . ' (' . $cols . ')'
            );
        } catch (\Throwable $e) {
            // An index that already exists is not an error for idempotent migrations.
            if (!str_contains(strtolower($e->getMessage()), 'duplicate')) {
                throw $e;
            }
        }
    }

    public static function raw(string $mysqlSql, ?string $sqliteSql = null): void
    {
        $db = self::db();
        $sql = $db->isSqlite() ? ($sqliteSql ?? $mysqlSql) : $mysqlSql;
        if (trim($sql) === '') {
            return;
        }
        $db->pdo()->exec($sql);
    }

    /** Split the body of a CREATE TABLE into its top-level definitions. */
    private static function extractColumnDefinitions(string $createSql): array
    {
        $start = strpos($createSql, '(');
        $end = strrpos($createSql, ')');
        if ($start === false || $end === false || $end <= $start) {
            return [];
        }
        $body = substr($createSql, $start + 1, $end - $start - 1);
        $parts = [];
        $depth = 0;
        $buffer = '';
        for ($i = 0, $len = strlen($body); $i < $len; $i++) {
            $char = $body[$i];
            if ($char === '(') {
                $depth++;
            } elseif ($char === ')') {
                $depth--;
            }
            if ($char === ',' && $depth === 0) {
                $parts[] = trim($buffer);
                $buffer = '';
                continue;
            }
            $buffer .= $char;
        }
        if (trim($buffer) !== '') {
            $parts[] = trim($buffer);
        }
        return $parts;
    }
}
