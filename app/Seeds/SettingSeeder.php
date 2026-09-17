<?php

declare(strict_types=1);

namespace App\Seeds;

use App\Core\Version;
use App\Services\SettingsService;

/**
 * Default settings and feature flags.
 *
 * Only inserts keys that do not exist yet, so an update can introduce a new
 * setting without overwriting an administrator's choice.
 */
final class SettingSeeder extends Seeder
{
    /** key => [value, type, group, label, public] */
    private function defaults(): array
    {
        return [
            // General
            'site_name'         => ['Shubh Kankotri', 'string', 'general', 'Site name', true],
            'site_tagline'      => ['Beautiful digital invitations for every Indian celebration', 'string', 'general', 'Tagline', true],
            'site_description'  => ['Create free digital wedding cards, kankotri, pooja and opening invitations. '
                . 'Customise, share on WhatsApp, export PDF and collect RSVPs.', 'text', 'general', 'Meta description', true],
            'contact_email'     => ['', 'string', 'general', 'Contact email', true],
            'contact_phone'     => ['', 'string', 'general', 'Contact phone', true],
            'default_locale'    => ['en', 'string', 'general', 'Default language', true],
            'enabled_locales'   => [['en', 'gu', 'hi'], 'json', 'general', 'Enabled languages', true],
            'timezone'          => ['Asia/Kolkata', 'string', 'general', 'Timezone', false],
            'app_version'       => [Version::current(), 'string', 'system', 'Installed version', false],

            // Branding
            'brand_primary'     => ['#C8102E', 'string', 'branding', 'Primary colour', true],
            'brand_secondary'   => ['#F0B429', 'string', 'branding', 'Accent colour', true],
            'logo_path'         => ['', 'string', 'branding', 'Logo', true],
            'favicon_path'      => ['', 'string', 'branding', 'Favicon', true],
            'footer_note'       => ['', 'text', 'branding', 'Footer note', true],

            // Security
            'login_max_attempts'  => [5, 'integer', 'security', 'Failed logins before lockout', false],
            'login_decay_minutes' => [15, 'integer', 'security', 'Lockout duration (minutes)', false],
            'password_min_length' => [8, 'integer', 'security', 'Minimum password length', false],
            'session_lifetime'    => [7200, 'integer', 'security', 'Session idle timeout (seconds)', false],
            'force_https'         => [false, 'boolean', 'security', 'Force HTTPS', false],
            'csp_enabled'         => [true, 'boolean', 'security', 'Send Content-Security-Policy', false],
            'api_rate_limit'      => [120, 'integer', 'security', 'API requests per minute', false],
            'registration_open'   => [true, 'boolean', 'security', 'Allow new registrations', true],
            'require_email_verification' => [false, 'boolean', 'security', 'Require email verification', false],

            // Uploads
            'max_image_size'  => [8388608, 'integer', 'storage', 'Maximum image size (bytes)', false],
            'max_music_size'  => [10485760, 'integer', 'storage', 'Maximum audio size (bytes)', false],
            'max_photos'      => [30, 'integer', 'storage', 'Photos per invitation', true],

            // PDF
            'pdf_engine' => ['auto', 'string', 'pdf', 'PDF engine', false],
            'pdf_paper'  => ['A4', 'string', 'pdf', 'Paper size', false],

            // Email
            'mail_driver'       => ['mail', 'string', 'email', 'Mail driver', false],
            'mail_from_address' => ['', 'string', 'email', 'From address', false],
            'mail_from_name'    => ['Shubh Kankotri', 'string', 'email', 'From name', false],
            'smtp_host'         => ['', 'string', 'email', 'SMTP host', false],
            'smtp_port'         => [587, 'integer', 'email', 'SMTP port', false],
            'smtp_username'     => ['', 'string', 'email', 'SMTP username', false],
            'smtp_password'     => ['', 'encrypted', 'email', 'SMTP password', false],
            'smtp_encryption'   => ['tls', 'string', 'email', 'SMTP encryption', false],

            // WhatsApp
            'whatsapp_support_number' => ['', 'string', 'whatsapp', 'Support WhatsApp number', true],
            'whatsapp_share_prefix'   => ['', 'text', 'whatsapp', 'Extra line added to share messages', true],

            // SEO
            'seo_allow_indexing'     => [true, 'boolean', 'seo', 'Allow search engines', false],
            'seo_index_invitations'  => [false, 'boolean', 'seo', 'Index individual invitations', false],
            'seo_og_image'           => ['', 'string', 'seo', 'Default share image', true],
            'google_site_verification' => ['', 'string', 'seo', 'Google site verification', false],

            // Analytics
            'analytics_store_ip'  => [false, 'boolean', 'analytics', 'Store IP addresses (not recommended)', false],
            'analytics_retention' => [90, 'integer', 'analytics', 'Days of raw event data', false],

            // Backups & updates
            'backup_auto_enabled' => [true, 'boolean', 'backup', 'Create scheduled backups', false],
            'update_keep_backups' => [5, 'integer', 'updates', 'Backups to keep', false],
            'github_repository'   => ['', 'string', 'updates', 'GitHub repository', false],
            'github_branch'       => ['main', 'string', 'updates', 'Branch', false],
            'github_token'        => ['', 'encrypted', 'updates', 'GitHub token', false],
            'update_protected_paths' => [[], 'json', 'updates', 'Extra protected paths', false],
        ];
    }

    /** key => [name, enabled, description] */
    private function flags(): array
    {
        return [
            'registration'       => ['User registration', true, 'Allow visitors to create an account'],
            'ai_generator'       => ['AI wording helper', true, 'Show "Write it for me" in the builder'],
            'ai_recommendations' => ['AI template suggestions', true, 'Let AI order the template shortlist'],
            'rsvp'               => ['RSVP', true, 'Guests can respond to invitations'],
            'pdf_export'         => ['PDF export', true, 'Download invitations as PDF'],
            'music'              => ['Background music', true, 'Allow music on invitations'],
            'analytics'          => ['Analytics', true, 'Record invitation views and shares'],
            'advanced_analytics' => ['Advanced analytics', true, 'Device, browser and referrer breakdowns'],
            'premium_templates'  => ['Premium templates', false, 'Reserved for future paid plans'],
            'watermark'          => ['Watermark', false, 'Add a small credit line to exports'],
            'custom_domain'      => ['Custom domains', false, 'Reserved for future paid plans'],
            'template_generator' => ['Template generator', true, 'Generate template variants from the admin panel'],
        ];
    }

    public function run(): void
    {
        $settings = SettingsService::instance();
        $existing = $this->db->pairs(
            'SELECT setting_key, id FROM ' . $this->db->wrap($this->db->table('settings'))
        );

        $added = 0;
        foreach ($this->defaults() as $key => [$value, $type, $group, $label, $isPublic]) {
            if (isset($existing[$key])) {
                continue;
            }
            $settings->set($key, $value, $type, $group);
            // is_public and the label are not part of the setter's contract.
            $this->db->update('settings', [
                'label'     => $label,
                'is_public' => $isPublic ? 1 : 0,
            ], ['setting_key' => $key]);
            $added++;
        }
        $settings->flush();
        $this->note($added . ' setting(s) added.');

        $flagRepository = new \App\Repositories\FeatureFlagRepository($this->db);
        $existingFlags = $this->db->pairs(
            'SELECT flag_key, id FROM ' . $this->db->wrap($this->db->table('feature_flags'))
        );
        $flagsAdded = 0;
        foreach ($this->flags() as $key => [$name, $enabled, $description]) {
            if (isset($existingFlags[$key])) {
                continue;
            }
            $flagRepository->put($key, $name, $enabled, 100, $description);
            $flagsAdded++;
        }
        $this->note($flagsAdded . ' feature flag(s) added.');
    }
}
