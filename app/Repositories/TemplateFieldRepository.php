<?php

declare(strict_types=1);

namespace App\Repositories;

final class TemplateFieldRepository extends BaseRepository
{
    protected string $table = 'template_fields';
    protected array $jsonColumns = ['options'];

    /** @return array<int,array<string,mixed>> */
    public function forTemplate(int $templateId, bool $visibleOnly = false): array
    {
        $sql = 'SELECT * FROM ' . $this->qualified() . ' WHERE template_id = :id';
        if ($visibleOnly) {
            $sql .= ' AND is_visible = 1';
        }
        $sql .= ' ORDER BY sort_order ASC, id ASC';
        return $this->hydrateMany($this->db->select($sql, ['id' => $templateId]));
    }

    /** @return array<string,array<string,mixed>> keyed by field_key */
    public function keyed(int $templateId): array
    {
        $out = [];
        foreach ($this->forTemplate($templateId) as $field) {
            $out[(string) $field['field_key']] = $field;
        }
        return $out;
    }

    /** @return array<string,array<int,array<string,mixed>>> grouped by section */
    public function bySection(int $templateId): array
    {
        $out = [];
        foreach ($this->forTemplate($templateId, true) as $field) {
            $out[(string) $field['section']][] = $field;
        }
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
