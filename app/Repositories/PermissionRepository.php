<?php

declare(strict_types=1);

namespace App\Repositories;

final class PermissionRepository extends BaseRepository
{
    protected string $table = 'permissions';

    /** @return array<string,array<int,array<string,mixed>>> grouped by group_name */
    public function grouped(): array
    {
        $rows = $this->db->select('SELECT * FROM ' . $this->qualified() . ' ORDER BY group_name ASC, name ASC');
        $out = [];
        foreach ($rows as $row) {
            $out[(string) $row['group_name']][] = $row;
        }
        return $out;
    }
}
