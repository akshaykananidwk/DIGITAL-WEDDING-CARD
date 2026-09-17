<?php

declare(strict_types=1);

namespace App\Repositories;

final class TemplateComponentRepository extends BaseRepository
{
    protected string $table = 'template_components';
    protected array $jsonColumns = ['styles', 'props'];

    /** @return array<int,array<string,mixed>> */
    public function forTemplate(int $templateId, bool $visibleOnly = false): array
    {
        $sql = 'SELECT * FROM ' . $this->qualified() . ' WHERE template_id = :id';
        if ($visibleOnly) {
            $sql .= ' AND is_visible = 1';
        }
        $sql .= ' ORDER BY page_number ASC, sort_order ASC, id ASC';
        return $this->hydrateMany($this->db->select($sql, ['id' => $templateId]));
    }

    /** @return array<int,array<int,array<string,mixed>>> components grouped per page */
    public function byPage(int $templateId): array
    {
        $out = [];
        foreach ($this->forTemplate($templateId, true) as $component) {
            $out[(int) $component['page_number']][] = $component;
        }
        ksort($out);
        return $out;
    }

    public function deleteForTemplate(int $templateId): int
    {
        return $this->db->delete($this->table, ['template_id' => $templateId]);
    }

    public function reorder(int $templateId, array $orderedIds): void
    {
        $this->db->transaction(function () use ($templateId, $orderedIds): void {
            foreach (array_values($orderedIds) as $index => $id) {
                $this->db->update(
                    $this->table,
                    ['sort_order' => $index * 10],
                    ['id' => (int) $id, 'template_id' => $templateId]
                );
            }
        });
    }
}
