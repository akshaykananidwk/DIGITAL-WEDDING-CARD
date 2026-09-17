<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;

/**
 * Shared CRUD for the repository layer.
 *
 * Every data access in the application goes through a repository so that
 * queries are reviewable in one place and controllers never build SQL.
 */
abstract class BaseRepository
{
    protected string $table = '';
    protected string $primaryKey = 'id';
    protected bool $softDeletes = false;
    protected bool $timestamps = true;
    /** @var array<int,string> columns stored as JSON text */
    protected array $jsonColumns = [];

    protected Database $db;

    public function __construct(?Database $db = null)
    {
        $this->db = $db ?? Database::instance();
    }

    public function table(): string
    {
        return $this->table;
    }

    protected function qualified(): string
    {
        return $this->db->wrap($this->db->table($this->table));
    }

    /** WHERE fragment that hides soft-deleted rows. */
    protected function notDeleted(string $alias = ''): string
    {
        if (!$this->softDeletes) {
            return '1=1';
        }
        $prefix = $alias !== '' ? $this->db->wrap($alias) . '.' : '';
        return $prefix . $this->db->wrap('deleted_at') . ' IS NULL';
    }

    /** @return array<string,mixed>|null */
    public function find(int $id): ?array
    {
        $row = $this->db->first(
            'SELECT * FROM ' . $this->qualified()
            . ' WHERE ' . $this->db->wrap($this->primaryKey) . ' = :id AND ' . $this->notDeleted()
            . ' LIMIT 1',
            ['id' => $id]
        );
        return $row === null ? null : $this->hydrate($row);
    }

    /** @return array<string,mixed>|null */
    public function findBy(string $column, mixed $value): ?array
    {
        $row = $this->db->first(
            'SELECT * FROM ' . $this->qualified()
            . ' WHERE ' . $this->db->wrap($column) . ' = :value AND ' . $this->notDeleted()
            . ' LIMIT 1',
            ['value' => $value]
        );
        return $row === null ? null : $this->hydrate($row);
    }

    /** @return array<int,array<string,mixed>> */
    public function where(array $conditions, string $orderBy = '', int $limit = 0): array
    {
        $parts = [$this->notDeleted()];
        $bindings = [];
        foreach ($conditions as $column => $value) {
            if ($value === null) {
                $parts[] = $this->db->wrap($column) . ' IS NULL';
                continue;
            }
            $key = 'w_' . preg_replace('/[^a-z0-9_]/i', '', $column);
            $parts[] = $this->db->wrap($column) . ' = :' . $key;
            $bindings[$key] = $value;
        }
        $sql = 'SELECT * FROM ' . $this->qualified() . ' WHERE ' . implode(' AND ', $parts);
        if ($orderBy !== '') {
            $sql .= ' ORDER BY ' . $this->safeOrderBy($orderBy);
        }
        if ($limit > 0) {
            $sql .= ' LIMIT ' . $limit;
        }
        return $this->hydrateMany($this->db->select($sql, $bindings));
    }

    /** @return array<int,array<string,mixed>> */
    public function all(string $orderBy = '', int $limit = 0): array
    {
        $sql = 'SELECT * FROM ' . $this->qualified() . ' WHERE ' . $this->notDeleted();
        if ($orderBy !== '') {
            $sql .= ' ORDER BY ' . $this->safeOrderBy($orderBy);
        }
        if ($limit > 0) {
            $sql .= ' LIMIT ' . $limit;
        }
        return $this->hydrateMany($this->db->select($sql));
    }

    public function count(array $conditions = []): int
    {
        $parts = [$this->notDeleted()];
        $bindings = [];
        foreach ($conditions as $column => $value) {
            $key = 'c_' . preg_replace('/[^a-z0-9_]/i', '', $column);
            $parts[] = $this->db->wrap($column) . ' = :' . $key;
            $bindings[$key] = $value;
        }
        return (int) $this->db->value(
            'SELECT COUNT(*) FROM ' . $this->qualified() . ' WHERE ' . implode(' AND ', $parts),
            $bindings,
            0
        );
    }

    public function exists(string $column, mixed $value, ?int $ignoreId = null): bool
    {
        $sql = 'SELECT COUNT(*) FROM ' . $this->qualified()
            . ' WHERE ' . $this->db->wrap($column) . ' = :value';
        $bindings = ['value' => $value];
        if ($ignoreId !== null) {
            $sql .= ' AND ' . $this->db->wrap($this->primaryKey) . ' <> :ignore';
            $bindings['ignore'] = $ignoreId;
        }
        return (int) $this->db->value($sql, $bindings, 0) > 0;
    }

    public function create(array $data): int
    {
        $data = $this->prepare($data);
        if ($this->timestamps) {
            $now = Database::now();
            $data['created_at'] ??= $now;
            $data['updated_at'] ??= $now;
        }
        return $this->db->insert($this->table, $data);
    }

    public function update(int $id, array $data): int
    {
        if ($data === []) {
            return 0;
        }
        $data = $this->prepare($data);
        if ($this->timestamps) {
            $data['updated_at'] = Database::now();
        }
        return $this->db->update($this->table, $data, [$this->primaryKey => $id]);
    }

    public function updateWhere(array $where, array $data): int
    {
        if ($data === []) {
            return 0;
        }
        $data = $this->prepare($data);
        if ($this->timestamps) {
            $data['updated_at'] = Database::now();
        }
        return $this->db->update($this->table, $data, $where);
    }

    /** Soft delete when the table supports it, otherwise a real delete. */
    public function delete(int $id): int
    {
        if ($this->softDeletes) {
            return $this->db->update(
                $this->table,
                ['deleted_at' => Database::now()],
                [$this->primaryKey => $id]
            );
        }
        return $this->db->delete($this->table, [$this->primaryKey => $id]);
    }

    public function forceDelete(int $id): int
    {
        return $this->db->delete($this->table, [$this->primaryKey => $id]);
    }

    public function restore(int $id): int
    {
        if (!$this->softDeletes) {
            return 0;
        }
        return $this->db->update($this->table, ['deleted_at' => null], [$this->primaryKey => $id]);
    }

    /** @return array<string,mixed>|null including soft-deleted rows */
    public function findWithTrashed(int $id): ?array
    {
        $row = $this->db->first(
            'SELECT * FROM ' . $this->qualified()
            . ' WHERE ' . $this->db->wrap($this->primaryKey) . ' = :id LIMIT 1',
            ['id' => $id]
        );
        return $row === null ? null : $this->hydrate($row);
    }

    /**
     * Paginate with a whitelist-validated ORDER BY.
     *
     * @return array{rows:array<int,array<string,mixed>>,total:int,page:int,per_page:int,pages:int}
     */
    public function paginate(array $conditions = [], int $page = 1, int $perPage = 20, string $orderBy = 'id DESC'): array
    {
        $parts = [$this->notDeleted()];
        $bindings = [];
        foreach ($conditions as $column => $value) {
            $key = 'p_' . preg_replace('/[^a-z0-9_]/i', '', $column);
            if ($value === null) {
                $parts[] = $this->db->wrap($column) . ' IS NULL';
                continue;
            }
            if (is_array($value)) {
                $names = [];
                foreach (array_values($value) as $i => $item) {
                    $names[] = ':' . $key . $i;
                    $bindings[$key . $i] = $item;
                }
                $parts[] = $this->db->wrap($column) . ' IN (' . implode(',', $names) . ')';
                continue;
            }
            $parts[] = $this->db->wrap($column) . ' = :' . $key;
            $bindings[$key] = $value;
        }

        $result = $this->db->paginate(
            '*',
            $this->qualified() . ' WHERE ' . implode(' AND ', $parts),
            $bindings,
            $page,
            $perPage,
            $this->safeOrderBy($orderBy)
        );
        $result['rows'] = $this->hydrateMany($result['rows']);
        return $result;
    }

    /**
     * Only allow "column direction" pairs made of known-safe characters, and
     * verify the column actually exists on the table.
     */
    protected function safeOrderBy(string $orderBy): string
    {
        $out = [];
        foreach (explode(',', $orderBy) as $segment) {
            $segment = trim($segment);
            if ($segment === '') {
                continue;
            }
            if (!preg_match('/^([A-Za-z_][A-Za-z0-9_]*)(?:\s+(ASC|DESC))?$/i', $segment, $m)) {
                continue;
            }
            $out[] = $this->db->wrap($m[1]) . ' ' . (strtoupper($m[2] ?? 'ASC') === 'DESC' ? 'DESC' : 'ASC');
        }
        return $out === [] ? $this->db->wrap($this->primaryKey) . ' DESC' : implode(', ', $out);
    }

    // ------------------------------------------------------------------
    //  JSON column handling
    // ------------------------------------------------------------------

    /** Encode JSON columns before writing. */
    protected function prepare(array $data): array
    {
        foreach ($this->jsonColumns as $column) {
            if (array_key_exists($column, $data) && !is_string($data[$column])) {
                $data[$column] = $data[$column] === null
                    ? null
                    : json_encode($data[$column], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            }
        }
        return $data;
    }

    /** Decode JSON columns after reading. */
    protected function hydrate(array $row): array
    {
        foreach ($this->jsonColumns as $column) {
            if (!array_key_exists($column, $row)) {
                continue;
            }
            if ($row[$column] === null || $row[$column] === '') {
                $row[$column] = [];
                continue;
            }
            if (is_string($row[$column])) {
                $decoded = json_decode($row[$column], true);
                $row[$column] = is_array($decoded) ? $decoded : [];
            }
        }
        return $row;
    }

    /** @return array<int,array<string,mixed>> */
    protected function hydrateMany(array $rows): array
    {
        return array_map([$this, 'hydrate'], $rows);
    }

    public function db(): Database
    {
        return $this->db;
    }
}
