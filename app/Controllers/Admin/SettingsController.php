<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Auth;
use App\Core\Cache;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Repositories\SettingRepository;
use App\Services\AuditService;
use App\Services\MailService;
use App\Services\SettingsService;
use App\Services\SitemapService;

/**
 * Settings, grouped into the tabs the brief lists.
 *
 * Each group declares its fields and types here, so validation, storage type
 * (including encryption) and the form are all driven from one definition.
 */
final class SettingsController extends AdminController
{
    /**
     * group => [key => [type, rule, label]]
     *
     * type: string|text|integer|boolean|json|encrypted
     */
    private function schema(): array
    {
        return [
            'general' => [
                'title'  => 'General',
                'icon'   => 'sliders',
                'fields' => [
                    'site_name'        => ['string', 'required|string|max:120|no_html', 'Site name'],
                    'site_tagline'     => ['string', 'nullable|string|max:191|no_html', 'Tagline'],
                    'site_description' => ['text', 'nullable|string|max:500|no_html', 'Meta description'],
                    'site_url'         => ['string', 'nullable|url', 'Site URL'],
                    'contact_email'    => ['string', 'nullable|email', 'Contact email'],
                    'contact_phone'    => ['string', 'nullable|phone', 'Contact phone'],
                    'default_locale'   => ['string', 'nullable|locale', 'Default language'],
                    'enabled_locales'  => ['json', 'nullable|array', 'Enabled languages'],
                    'registration_open' => ['boolean', 'nullable|boolean', 'Allow registration'],
                ],
            ],
            'branding' => [
                'title'  => 'Branding',
                'icon'   => 'palette',
                'fields' => [
                    'brand_primary'   => ['string', 'nullable|hex_color', 'Primary colour'],
                    'brand_secondary' => ['string', 'nullable|hex_color', 'Accent colour'],
                    'logo_path'       => ['string', 'nullable|string|max:255', 'Logo path'],
                    'favicon_path'    => ['string', 'nullable|string|max:255', 'Favicon path'],
                    'footer_note'     => ['text', 'nullable|string|max:500|safe_text', 'Footer note'],
                ],
            ],
            'email' => [
                'title'  => 'Email & SMTP',
                'icon'   => 'envelope',
                'fields' => [
                    'mail_driver'       => ['string', 'required|in:mail,smtp,log', 'Driver'],
                    'mail_from_address' => ['string', 'nullable|email', 'From address'],
                    'mail_from_name'    => ['string', 'nullable|string|max:120|no_html', 'From name'],
                    'smtp_host'         => ['string', 'nullable|string|max:191', 'SMTP host'],
                    'smtp_port'         => ['integer', 'nullable|integer|min:1|max:65535', 'SMTP port'],
                    'smtp_username'     => ['string', 'nullable|string|max:191', 'SMTP username'],
                    'smtp_password'     => ['encrypted', 'nullable|string|max:191', 'SMTP password'],
                    'smtp_encryption'   => ['string', 'nullable|in:tls,ssl,none', 'Encryption'],
                ],
            ],
            'whatsapp' => [
                'title'  => 'WhatsApp',
                'icon'   => 'whatsapp',
                'fields' => [
                    'whatsapp_support_number' => ['string', 'nullable|phone', 'Support number'],
                    'whatsapp_share_prefix'   => ['text', 'nullable|string|max:300|safe_text', 'Extra share line'],
                ],
            ],
            'storage' => [
                'title'  => 'Storage & uploads',
                'icon'   => 'hdd',
                'fields' => [
                    'max_image_size' => ['integer', 'nullable|integer|min:262144|max:52428800', 'Max image size (bytes)'],
                    'max_music_size' => ['integer', 'nullable|integer|min:262144|max:52428800', 'Max audio size (bytes)'],
                    'max_photos'     => ['integer', 'nullable|integer|min:1|max:200', 'Photos per invitation'],
                ],
            ],
            'security' => [
                'title'  => 'Security',
                'icon'   => 'shield-lock',
                'fields' => [
                    'login_max_attempts'  => ['integer', 'nullable|integer|min:3|max:50', 'Failed logins before lockout'],
                    'login_decay_minutes' => ['integer', 'nullable|integer|min:1|max:1440', 'Lockout minutes'],
                    'password_min_length' => ['integer', 'nullable|integer|min:8|max:64', 'Minimum password length'],
                    'session_lifetime'    => ['integer', 'nullable|integer|min:600|max:604800', 'Session timeout (seconds)'],
                    'force_https'         => ['boolean', 'nullable|boolean', 'Force HTTPS'],
                    'csp_enabled'         => ['boolean', 'nullable|boolean', 'Content-Security-Policy'],
                    'api_rate_limit'      => ['integer', 'nullable|integer|min:10|max:6000', 'API requests / minute'],
                    'require_email_verification' => ['boolean', 'nullable|boolean', 'Require email verification'],
                ],
            ],
            'seo' => [
                'title'  => 'SEO',
                'icon'   => 'search',
                'fields' => [
                    'seo_allow_indexing'    => ['boolean', 'nullable|boolean', 'Allow search engines'],
                    'seo_index_invitations' => ['boolean', 'nullable|boolean', 'Index individual invitations'],
                    'seo_og_image'          => ['string', 'nullable|string|max:255', 'Default share image'],
                    'google_site_verification' => ['string', 'nullable|string|max:191|alpha_dash', 'Google verification'],
                ],
            ],
            'pdf' => [
                'title'  => 'PDF',
                'icon'   => 'file-pdf',
                'fields' => [
                    'pdf_engine' => ['string', 'nullable|in:auto,builtin,mpdf,dompdf', 'Engine'],
                    'pdf_paper'  => ['string', 'nullable|in:A4,A5,LETTER,KANKOTRI,MOBILE', 'Paper size'],
                ],
            ],
            'analytics' => [
                'title'  => 'Analytics',
                'icon'   => 'graph-up',
                'fields' => [
                    'analytics_store_ip'  => ['boolean', 'nullable|boolean', 'Store IP addresses'],
                    'analytics_retention' => ['integer', 'nullable|integer|min:7|max:1095', 'Raw event retention (days)'],
                ],
            ],
            'backup' => [
                'title'  => 'Backups',
                'icon'   => 'archive',
                'fields' => [
                    'backup_auto_enabled' => ['boolean', 'nullable|boolean', 'Scheduled backups'],
                    'update_keep_backups' => ['integer', 'nullable|integer|min:1|max:50', 'Backups to keep'],
                ],
            ],
        ];
    }

    public function index(Request $request): Response
    {
        return $this->group($request->param('group') === null ? $this->withGroup($request, 'general') : $request);
    }

    private function withGroup(Request $request, string $group): Request
    {
        $request->setRouteParams(array_merge($request->params(), ['group' => $group]));
        return $request;
    }

    public function group(Request $request): Response
    {
        $schema = $this->schema();
        $group = (string) ($request->param('group') ?? 'general');
        if (!isset($schema[$group])) {
            throw HttpException::notFound('Unknown settings group.');
        }

        $settings = SettingsService::instance();
        $values = [];
        foreach ($schema[$group]['fields'] as $key => [$type]) {
            $values[$key] = $type === 'encrypted'
                ? ($settings->get($key, '') !== '' ? '••••••••' : '')
                : $settings->get($key, '');
        }

        return $this->admin('admin.settings', $schema[$group]['title'] . ' settings', [
            'schema'  => $schema,
            'group'   => $group,
            'values'  => $values,
            'locales' => \App\Core\Lang::available(),
            'mailDriver' => (string) $settings->get('mail_driver', 'mail'),
        ]);
    }

    public function update(Request $request): Response
    {
        $schema = $this->schema();
        $group = (string) $request->param('group');
        if (!isset($schema[$group])) {
            throw HttpException::notFound();
        }

        $rules = [];
        foreach ($schema[$group]['fields'] as $key => [$type, $rule]) {
            $rules[$key] = $rule;
        }

        $input = $request->all();
        // Unchecked checkboxes are absent from a form post.
        foreach ($schema[$group]['fields'] as $key => [$type]) {
            if ($type === 'boolean' && !array_key_exists($key, $input)) {
                $input[$key] = '0';
            }
        }

        $validator = \App\Core\Validator::make($input, $rules, array_map(
            static fn (array $field): string => (string) $field[2],
            $schema[$group]['fields']
        ));
        if ($validator->fails()) {
            if ($request->expectsJson()) {
                return $this->error('Please correct the highlighted fields.', 422, $validator->errors());
            }
            return $this->back($validator->errors(), $input);
        }

        $settings = SettingsService::instance();
        $before = [];
        $payload = [];

        foreach ($schema[$group]['fields'] as $key => [$type]) {
            if (!array_key_exists($key, $input)) {
                continue;
            }
            $value = $input[$key];

            // A masked password field means "leave it as it is".
            if ($type === 'encrypted' && (is_string($value) && (trim($value) === '' || str_starts_with($value, '••')))) {
                continue;
            }
            if ($type === 'json') {
                $value = is_array($value) ? array_values(array_map('strval', $value)) : [];
            }
            if ($type === 'boolean') {
                $value = in_array((string) $value, ['1', 'true', 'on', 'yes'], true);
            }
            if ($type === 'integer') {
                $value = (int) $value;
            }

            $before[$key] = $type === 'encrypted' ? '[redacted]' : $settings->get($key);
            $payload[$key] = ['value' => $value, 'type' => $type, 'group' => $group];
        }

        $settings->setMany($payload, Auth::id());
        $settings->applyToConfig();
        Cache::flush();
        (new SitemapService())->flush();

        AuditService::instance()->log(
            'admin.settings.update',
            'settings',
            null,
            'Updated the ' . $group . ' settings',
            ['keys' => array_keys($payload)]
        );

        return $this->respond($request, true, $schema[$group]['title'] . ' settings saved.', 'admin/settings/' . $group);
    }

    public function testMail(Request $request): Response
    {
        $to = (string) $request->input('to', '');
        if (filter_var($to, FILTER_VALIDATE_EMAIL) === false) {
            return $this->respond($request, false, 'Enter a valid address to send the test to.', 'admin/settings/email');
        }

        $result = (new MailService())->sendTest($to);
        AuditService::instance()->log('admin.settings.mail_test', 'settings', null, $result['ok'] ? 'sent' : 'failed');

        return $this->respond($request, (bool) $result['ok'], (string) $result['message'], 'admin/settings/email');
    }
}
