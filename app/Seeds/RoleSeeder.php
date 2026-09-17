<?php

declare(strict_types=1);

namespace App\Seeds;

/**
 * Roles and permissions.
 *
 * Re-runnable: new permissions introduced by an update are added and granted
 * to the roles that should have them, without disturbing an administrator's
 * customised grants for existing permissions.
 */
final class RoleSeeder extends Seeder
{
    /** slug => [name, description, level, permissions] */
    private const ROLES = [
        'super-admin' => [
            'name'        => 'Super Admin',
            'description' => 'Full control, including system updates and security settings',
            'level'       => 100,
            'permissions' => ['*'],
        ],
        'admin' => [
            'name'        => 'Admin',
            'description' => 'Manages users, templates, categories and content',
            'level'       => 80,
            'permissions' => [
                'dashboard.view', 'users.view', 'users.create', 'users.edit',
                'templates.*', 'categories.*', 'invitations.view', 'invitations.manage_all',
                'media.*', 'fonts.*', 'pages.*', 'analytics.view', 'rsvp.view',
                'settings.view', 'settings.edit', 'ai.view', 'ai.edit',
                'audit.view', 'backups.view', 'backups.create', 'health.view', 'flags.view',
            ],
        ],
        'editor' => [
            'name'        => 'Editor',
            'description' => 'Creates and edits templates and content, no system access',
            'level'       => 50,
            'permissions' => [
                'dashboard.view', 'templates.view', 'templates.create', 'templates.edit',
                'categories.view', 'media.view', 'media.upload', 'fonts.view',
                'pages.view', 'pages.edit', 'analytics.view',
            ],
        ],
        'user' => [
            'name'        => 'User',
            'description' => 'Creates and shares their own invitations',
            'level'       => 10,
            'permissions' => [
                'invitations.create', 'invitations.own', 'rsvp.own', 'analytics.own', 'media.upload',
            ],
        ],
    ];

    /** slug => [name, group] */
    private const PERMISSIONS = [
        'dashboard.view'          => ['View the admin dashboard', 'admin'],
        'users.view'              => ['View users', 'users'],
        'users.create'            => ['Create users', 'users'],
        'users.edit'              => ['Edit users', 'users'],
        'users.delete'            => ['Delete users', 'users'],
        'users.impersonate'       => ['Sign in as another user', 'users'],
        'roles.manage'            => ['Manage roles and permissions', 'users'],
        'templates.view'          => ['View templates', 'templates'],
        'templates.create'        => ['Create templates', 'templates'],
        'templates.edit'          => ['Edit templates', 'templates'],
        'templates.delete'        => ['Delete templates', 'templates'],
        'templates.fields'        => ['Manage template fields', 'templates'],
        'templates.generate'      => ['Generate template variants', 'templates'],
        'categories.view'         => ['View categories', 'categories'],
        'categories.create'       => ['Create categories', 'categories'],
        'categories.edit'         => ['Edit categories', 'categories'],
        'categories.delete'       => ['Delete categories', 'categories'],
        'invitations.create'      => ['Create invitations', 'invitations'],
        'invitations.own'         => ['Manage own invitations', 'invitations'],
        'invitations.view'        => ['View all invitations', 'invitations'],
        'invitations.manage_all'  => ['Manage any invitation', 'invitations'],
        'invitations.delete_any'  => ['Delete any invitation', 'invitations'],
        'media.view'              => ['Browse the media library', 'media'],
        'media.upload'            => ['Upload media', 'media'],
        'media.delete'            => ['Delete media', 'media'],
        'fonts.view'              => ['View fonts', 'media'],
        'fonts.manage'            => ['Add and remove fonts', 'media'],
        'pages.view'              => ['View pages', 'content'],
        'pages.edit'              => ['Edit pages', 'content'],
        'pages.delete'            => ['Delete pages', 'content'],
        'analytics.view'          => ['View platform analytics', 'analytics'],
        'analytics.own'           => ['View own analytics', 'analytics'],
        'rsvp.view'               => ['View all RSVP responses', 'analytics'],
        'rsvp.own'                => ['View own RSVP responses', 'analytics'],
        'settings.view'           => ['View settings', 'system'],
        'settings.edit'           => ['Change settings', 'system'],
        'ai.view'                 => ['View AI configuration', 'system'],
        'ai.edit'                 => ['Change AI configuration', 'system'],
        'audit.view'              => ['View the audit log', 'system'],
        'logs.view'               => ['View application logs', 'system'],
        'backups.view'            => ['View backups', 'system'],
        'backups.create'          => ['Create backups', 'system'],
        'backups.restore'         => ['Restore a backup', 'system'],
        'backups.delete'          => ['Delete backups', 'system'],
        'health.view'             => ['View system health', 'system'],
        'updates.view'            => ['View updates', 'system'],
        'updates.apply'           => ['Apply updates and roll back', 'system'],
        'flags.view'              => ['View feature flags', 'system'],
        'flags.manage'            => ['Change feature flags', 'system'],
        'cron.run'                => ['Run scheduled tasks manually', 'system'],
    ];

    public function run(): void
    {
        $this->seedPermissions();
        $this->seedRoles();
        $this->grantPermissions();
    }

    private function seedPermissions(): void
    {
        $added = 0;
        foreach (self::PERMISSIONS as $slug => [$name, $group]) {
            $exists = (int) $this->db->value(
                'SELECT COUNT(*) FROM ' . $this->db->wrap($this->db->table('permissions')) . ' WHERE slug = :slug',
                ['slug' => $slug],
                0
            ) > 0;
            if ($exists) {
                continue;
            }
            $this->db->insert('permissions', [
                'name'       => $name,
                'slug'       => $slug,
                'group_name' => $group,
                'created_at' => $this->now(),
                'updated_at' => $this->now(),
            ]);
            $added++;
        }
        // The wildcard permission the super admin holds.
        $hasWildcard = (int) $this->db->value(
            'SELECT COUNT(*) FROM ' . $this->db->wrap($this->db->table('permissions')) . ' WHERE slug = :slug',
            ['slug' => '*'],
            0
        ) > 0;
        if (!$hasWildcard) {
            $this->db->insert('permissions', [
                'name'       => 'All permissions',
                'slug'       => '*',
                'group_name' => 'system',
                'created_at' => $this->now(),
                'updated_at' => $this->now(),
            ]);
            $added++;
        }
        $this->note($added . ' permission(s) added.');
    }

    private function seedRoles(): void
    {
        $added = 0;
        foreach (self::ROLES as $slug => $definition) {
            $exists = (int) $this->db->value(
                'SELECT COUNT(*) FROM ' . $this->db->wrap($this->db->table('roles')) . ' WHERE slug = :slug',
                ['slug' => $slug],
                0
            ) > 0;
            if ($exists) {
                continue;
            }
            $this->db->insert('roles', [
                'name'        => $definition['name'],
                'slug'        => $slug,
                'description' => $definition['description'],
                'level'       => $definition['level'],
                'is_system'   => 1,
                'created_at'  => $this->now(),
                'updated_at'  => $this->now(),
            ]);
            $added++;
        }
        $this->note($added . ' role(s) added.');
    }

    /** Grant each role its permissions, skipping grants that already exist. */
    private function grantPermissions(): void
    {
        $permissionIds = $this->db->pairs(
            'SELECT slug, id FROM ' . $this->db->wrap($this->db->table('permissions'))
        );
        $roleIds = $this->db->pairs(
            'SELECT slug, id FROM ' . $this->db->wrap($this->db->table('roles'))
        );

        $granted = 0;
        foreach (self::ROLES as $roleSlug => $definition) {
            $roleId = $roleIds[$roleSlug] ?? null;
            if ($roleId === null) {
                continue;
            }

            $slugs = [];
            foreach ($definition['permissions'] as $pattern) {
                if ($pattern === '*') {
                    $slugs[] = '*';
                    continue;
                }
                if (str_ends_with($pattern, '.*')) {
                    $prefix = substr($pattern, 0, -1); // keep the dot
                    foreach (array_keys($permissionIds) as $slug) {
                        if (str_starts_with((string) $slug, $prefix)) {
                            $slugs[] = (string) $slug;
                        }
                    }
                    continue;
                }
                $slugs[] = $pattern;
            }

            foreach (array_unique($slugs) as $slug) {
                $permissionId = $permissionIds[$slug] ?? null;
                if ($permissionId === null) {
                    continue;
                }
                $exists = (int) $this->db->value(
                    'SELECT COUNT(*) FROM ' . $this->db->wrap($this->db->table('role_permissions'))
                    . ' WHERE role_id = :role AND permission_id = :permission',
                    ['role' => (int) $roleId, 'permission' => (int) $permissionId],
                    0
                ) > 0;
                if ($exists) {
                    continue;
                }
                $this->db->insert('role_permissions', [
                    'role_id'       => (int) $roleId,
                    'permission_id' => (int) $permissionId,
                ]);
                $granted++;
            }
        }
        $this->note($granted . ' permission grant(s) added.');
    }

    /** Ensure the free plan exists and is the default. */
    public function seedPlans(): void
    {
        if (!$this->isEmpty('plans')) {
            return;
        }
        $this->db->insertMany('plans', [
            [
                'name'        => 'Free',
                'slug'        => 'free',
                'description' => 'Everything you need to create and share beautiful invitations',
                'price_monthly' => 0,
                'price_yearly'  => 0,
                'currency'    => 'INR',
                'limits'      => json_encode([
                    'max_invitations' => 0,   // 0 = unlimited
                    'storage_mb'      => 500,
                    'max_photos'      => 30,
                    'ai_credits'      => 50,
                ]),
                'features'    => json_encode([
                    'pdf_export', 'qr_code', 'rsvp', 'analytics', 'music', 'all_templates',
                ]),
                'is_active'   => 1,
                'is_default'  => 1,
                'sort_order'  => 10,
                'created_at'  => $this->now(),
                'updated_at'  => $this->now(),
            ],
            [
                'name'        => 'Premium',
                'slug'        => 'premium',
                'description' => 'Reserved for future paid features. Not enabled.',
                'price_monthly' => 0,
                'price_yearly'  => 0,
                'currency'    => 'INR',
                'limits'      => json_encode([
                    'max_invitations' => 0,
                    'storage_mb'      => 5000,
                    'max_photos'      => 100,
                    'ai_credits'      => 500,
                ]),
                'features'    => json_encode([
                    'pdf_export', 'qr_code', 'rsvp', 'analytics', 'music', 'all_templates',
                    'no_watermark', 'custom_domain', 'advanced_analytics', 'premium_templates',
                ]),
                'is_active'   => 0,
                'is_default'  => 0,
                'sort_order'  => 20,
                'created_at'  => $this->now(),
                'updated_at'  => $this->now(),
            ],
        ]);
        $this->note('2 plan(s) added.');
    }
}
