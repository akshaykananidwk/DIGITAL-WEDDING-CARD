<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Cache;
use App\Core\Logger;
use App\Core\View;
use App\Repositories\FontRepository;
use App\Repositories\InvitationDataRepository;
use App\Repositories\InvitationMusicRepository;
use App\Repositories\InvitationPhotoRepository;
use App\Repositories\InvitationSectionRepository;
use App\Repositories\TemplateComponentRepository;
use App\Repositories\TemplateFieldRepository;
use App\Repositories\TemplateRepository;

/**
 * The template engine.
 *
 * A template is data, not code. Each row names a `layout_key` - one of the
 * renderers in app/Views/invite/layouts - plus a `theme` of design tokens and
 * a set of dynamic fields. Ten layouts and a hundred themes therefore give a
 * thousand distinct templates with no new code, which is what makes the
 * 10,000-template target reachable.
 *
 * Administrators who want something entirely bespoke can instead store
 * `custom_html`, in which case {{field_key}} placeholders are substituted and
 * the markup is sanitised before it reaches a page.
 */
final class TemplateEngine
{
    public function __construct(
        private readonly TemplateRepository $templates = new TemplateRepository(),
        private readonly TemplateFieldRepository $fields = new TemplateFieldRepository(),
        private readonly InvitationDataRepository $data = new InvitationDataRepository(),
        private readonly InvitationPhotoRepository $photos = new InvitationPhotoRepository(),
        private readonly InvitationMusicRepository $music = new InvitationMusicRepository(),
        private readonly InvitationSectionRepository $sections = new InvitationSectionRepository()
    ) {
    }

    /** Layout renderers shipped with the application. */
    public const LAYOUTS = [
        'classic-kankotri' => 'Classic Gujarati Kankotri',
        'krishna-scroll'   => 'Krishna Scroll',
        'royal-arch'       => 'Royal Arch',
        'floral-minimal'   => 'Floral Minimal',
        'envelope-3d'      => '3D Envelope Opening',
        'multi-page-book'  => 'Multi-page Book',
        'modern-hero'      => 'Modern Hero',
        'temple-mandala'   => 'Temple Mandala',
        'celebration-pop'  => 'Celebration Pop',
        'business-launch'  => 'Business Launch',
    ];

    public static function layoutExists(string $key): bool
    {
        return isset(self::LAYOUTS[$key]) && View::exists('invite.layouts.' . $key);
    }

    /**
     * Build the context object for an invitation.
     *
     * @param array<string,mixed>      $invitation the invitation row
     * @param array<string,mixed>|null $overrides  unsaved values, for live preview
     */
    public function context(array $invitation, ?array $overrides = null, bool $preview = false): TemplateContext
    {
        $templateId = (int) $invitation['template_id'];
        $template = $this->templates->definition($templateId);
        if ($template === null) {
            throw new \RuntimeException('The template for this invitation is no longer available.');
        }

        $invitationId = (int) $invitation['id'];
        $values = $invitationId > 0 ? $this->data->forInvitation($invitationId) : [];

        // Values the template ships with fill any blanks, so a half-finished
        // draft still previews as a complete card.
        $defaults = is_array($template['demo_data'] ?? null) ? $template['demo_data'] : [];
        foreach ($defaults as $key => $value) {
            if (!array_key_exists($key, $values) || $values[$key] === null || $values[$key] === '') {
                $values[$key] = $value;
            }
        }

        if ($overrides !== null) {
            foreach ($overrides as $key => $value) {
                $values[$key] = $value;
            }
        }

        $fields = [];
        foreach ($template['fields'] ?? [] as $field) {
            $fields[(string) $field['field_key']] = $field;
        }

        return new TemplateContext(
            $invitation,
            $template,
            $values,
            $fields,
            $invitationId > 0 ? $this->sections->forInvitation($invitationId) : [],
            $invitationId > 0 ? $this->photos->forInvitation($invitationId) : [],
            $invitationId > 0 ? $this->music->forInvitation($invitationId) : null,
            $this->resolveTheme($template, $invitation),
            $preview
        );
    }

    /**
     * Render the invitation body HTML.
     *
     * @param array<string,mixed>|null $overrides unsaved values for preview
     */
    public function render(array $invitation, ?array $overrides = null, bool $preview = false): string
    {
        $context = $this->context($invitation, $overrides, $preview);
        $template = $context->template();

        // A fully custom template wins over the layout renderer.
        $customHtml = (string) ($template['custom_html'] ?? '');
        if (trim($customHtml) !== '') {
            return $this->renderCustomHtml($customHtml, $context);
        }

        // Then a template built from components in the admin panel.
        $components = is_array($template['components'] ?? null) ? $template['components'] : [];
        if ($this->hasVisibleComponents($components)) {
            return $this->renderComponentPages((int) $template['id'], $context);
        }

        $layout = (string) ($template['layout_key'] ?? 'classic-kankotri');
        if (!self::layoutExists($layout)) {
            Logger::warning('Unknown invitation layout, falling back', ['layout' => $layout]);
            $layout = 'classic-kankotri';
        }

        return View::make('invite.layouts.' . $layout, ['c' => $context])->render();
    }

    /** CSS for the invitation: font faces, theme variables, template CSS. */
    public function styles(TemplateContext $context): string
    {
        $template = $context->template();
        $css = $this->fontFaceCss();
        $custom = (string) ($template['custom_css'] ?? '');
        if (trim($custom) !== '') {
            $css .= "\n" . $this->sanitiseCss($custom);
        }
        return $css;
    }

    /** Template JS, sanitised. Templates rarely need any. */
    public function scripts(TemplateContext $context): string
    {
        $custom = (string) ($context->template()['custom_js'] ?? '');
        if (trim($custom) === '') {
            return '';
        }
        // Only administrators can author this, but keep the obvious foot-guns out.
        if (preg_match('/<\/?script|document\.write|eval\s*\(/i', $custom) === 1) {
            Logger::security('Template JavaScript rejected by the sanitiser', [
                'template_id' => $context->template()['id'] ?? null,
            ]);
            return '';
        }
        return $custom;
    }

    /** @font-face rules for every active font, generated once and cached. */
    public function fontFaceCss(): string
    {
        return Cache::remember('css:fontfaces', 3600, static function (): string {
            $css = '';
            foreach ((new FontRepository())->active() as $font) {
                $path = (string) ($font['file_path'] ?? '');
                if ($path === '') {
                    continue;
                }
                $url = \App\Core\Url::path(ltrim($path, '/'));
                $family = preg_replace('/[^A-Za-z0-9 \-]/', '', (string) $font['family']) ?? 'sans-serif';
                $css .= "@font-face{font-family:'" . $family . "';"
                    . "src:url('" . $url . "') format('truetype');"
                    . 'font-weight:' . preg_replace('/[^0-9]/', '', (string) $font['weight']) . ';'
                    . 'font-style:' . ((string) $font['style'] === 'italic' ? 'italic' : 'normal') . ';'
                    . "font-display:swap}\n";
            }
            return $css;
        });
    }

    /**
     * Merge the template theme with the owner's allowed overrides.
     *
     * @return array<string,mixed>
     */
    public function resolveTheme(array $template, array $invitation): array
    {
        $theme = is_array($template['theme'] ?? null) ? $template['theme'] : [];

        $theme['primary'] ??= (string) ($template['color_primary'] ?? '#C8102E');
        $theme['secondary'] ??= (string) ($template['color_secondary'] ?? '#F0B429');
        $theme['background'] ??= (string) ($template['color_background'] ?? '#FFF8EE');
        $theme['heading_font'] ??= (string) ($template['font_heading'] ?? 'GreatVibes');
        $theme['body_font'] ??= (string) ($template['font_body'] ?? 'NotoSans');

        $overrides = is_array($invitation['theme_overrides'] ?? null) ? $invitation['theme_overrides'] : [];
        foreach ($this->allowedOverrides() as $key => $validator) {
            if (!array_key_exists($key, $overrides)) {
                continue;
            }
            $value = $overrides[$key];
            if ($validator($value)) {
                $theme[$key] = $value;
            }
        }

        $theme['heading_font_stack'] = $this->fontStack((string) $theme['heading_font'], 'cursive');
        $theme['body_font_stack'] = $this->fontStack((string) $theme['body_font'], 'system-ui, sans-serif');

        return $theme;
    }

    /**
     * Which theme keys a user may override, and what counts as valid.
     *
     * @return array<string,callable(mixed):bool>
     */
    public function allowedOverrides(): array
    {
        $isColor = static fn ($value): bool => is_string($value)
            && preg_match('/^#(?:[0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/', $value) === 1;
        $isFont = static fn ($value): bool => is_string($value)
            && preg_match('/^[A-Za-z0-9 \-]{1,40}$/', $value) === 1;

        return [
            'primary'       => $isColor,
            'secondary'     => $isColor,
            'background'    => $isColor,
            'surface'       => $isColor,
            'text'          => $isColor,
            'accent'        => $isColor,
            'heading_font'  => $isFont,
            'body_font'     => $isFont,
            'heading_scale' => static fn ($value): bool => is_numeric($value)
                && (float) $value >= 0.7 && (float) $value <= 1.6,
            'radius'        => static fn ($value): bool => is_string($value)
                && preg_match('/^\d{1,2}(px|rem)$/', $value) === 1,
            'ornament'      => static fn ($value): bool => is_string($value)
                && preg_match('/^[a-z0-9\-]{1,30}$/', $value) === 1,
            'motion'        => static fn ($value): bool => in_array($value, ['none', 'gentle', 'rich'], true),
            'text_align'    => static fn ($value): bool => in_array($value, ['left', 'center', 'right'], true),
            'button_style'  => static fn ($value): bool => in_array($value, ['solid', 'outline', 'soft'], true),
        ];
    }

    private function fontStack(string $family, string $fallback): string
    {
        $clean = preg_replace('/[^A-Za-z0-9 \-]/', '', $family) ?? '';
        if ($clean === '') {
            return $fallback;
        }
        return "'" . $clean . "', " . $fallback;
    }

    // ------------------------------------------------------------------
    //  Custom HTML templates
    // ------------------------------------------------------------------

    /**
     * Substitute {{placeholders}} and sanitise the result.
     *
     * Supported forms:
     *   {{groom_name}}                 escaped value
     *   {{wedding_date|date:j M Y}}    formatted date
     *   {{message|multiline}}          newlines become <br>
     *   {{venue|upper}} {{x|lower}}
     *   {{@public_url}} {{@qr_url}}    built-in links
     */
    public function renderCustomHtml(string $html, TemplateContext $context): string
    {
        $rendered = preg_replace_callback(
            '/\{\{\s*([@a-zA-Z0-9_]+)(?:\|([a-zA-Z_]+)(?::([^}]*))?)?\s*\}\}/',
            function (array $match) use ($context): string {
                $key = $match[1];
                $filter = $match[2] ?? '';
                $argument = trim($match[3] ?? '');

                if (str_starts_with($key, '@')) {
                    return $this->builtinPlaceholder(substr($key, 1), $context);
                }

                return match ($filter) {
                    'date'      => $context->date($key, $argument !== '' ? $argument : 'j M Y'),
                    'time'      => $context->time($key, $argument !== '' ? $argument : 'g:i A'),
                    'longdate'  => $context->longDate($key),
                    'multiline' => $context->multiline($key),
                    'upper'     => mb_strtoupper($context->get($key), 'UTF-8'),
                    'lower'     => mb_strtolower($context->get($key), 'UTF-8'),
                    default     => $context->get($key),
                };
            },
            $html
        );

        return $this->sanitiseHtml((string) $rendered);
    }

    private function builtinPlaceholder(string $name, TemplateContext $context): string
    {
        return match ($name) {
            'public_url' => e($context->publicUrl()),
            'short_url'  => e($context->shortUrl()),
            'qr_url'     => e($context->qrUrl()),
            'qr_svg_url' => e($context->qrUrl('svg')),
            'pdf_url'    => e($context->pdfUrl()),
            'ics_url'    => e($context->icsUrl()),
            'maps_url'   => e($context->mapsUrl()),
            'calendar_url' => e($context->googleCalendarUrl()),
            'whatsapp_url' => e($context->whatsappContactUrl()),
            'title'      => $context->title(),
            'year'       => date('Y'),
            default      => '',
        };
    }

    /**
     * Whitelist sanitiser for admin-authored template markup.
     *
     * Admins are trusted, but a compromised admin account should not be able
     * to plant a script that runs on every guest's browser.
     */
    public function sanitiseHtml(string $html): string
    {
        // Remove entire dangerous elements including their content.
        $html = (string) preg_replace(
            '#<\s*(script|iframe|object|embed|applet|form|base|meta|link|style)\b[^>]*>.*?<\s*/\s*\1\s*>#is',
            '',
            $html
        );
        // And their self-closing forms.
        $html = (string) preg_replace(
            '#<\s*/?\s*(script|iframe|object|embed|applet|form|base|meta|link|style)\b[^>]*>#i',
            '',
            $html
        );
        // Inline event handlers.
        $html = (string) preg_replace('/\son[a-z]+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $html);
        // javascript:, vbscript: and data: URLs in attributes.
        $html = (string) preg_replace(
            '/\s(href|src|action|formaction|xlink:href)\s*=\s*("|\')\s*(javascript|vbscript|data)\s*:[^"\']*\2/i',
            '',
            $html
        );

        return $html;
    }

    /** Strip anything executable from admin-authored CSS. */
    public function sanitiseCss(string $css): string
    {
        $css = (string) preg_replace('#/\*.*?\*/#s', '', $css);
        $css = (string) preg_replace('/expression\s*\(|javascript\s*:|behaviour\s*:|behavior\s*:|@import/i', '', $css);
        $css = str_replace(['</style', '<script'], '', $css);
        return $css;
    }

    /** @param array<int,array<string,mixed>> $components */
    private function hasVisibleComponents(array $components): bool
    {
        foreach ($components as $component) {
            if ((int) ($component['is_visible'] ?? 1) === 1 && trim((string) ($component['content'] ?? '')) !== '') {
                return true;
            }
        }
        return false;
    }

    /**
     * Render a component-built template, one wrapper per page.
     *
     * A single page is an ordinary invitation page. Several pages reuse the
     * book markup, so the same page-turning script and the same no-JavaScript
     * fallback (all pages stacked) apply as for the hand-written layouts.
     */
    public function renderComponentPages(int $templateId, TemplateContext $context): string
    {
        $pages = (new TemplateComponentRepository())->byPage($templateId);
        $rendered = [];
        foreach ($pages as $components) {
            $html = $this->renderComponents($components, $context);
            if (trim($html) !== '') {
                $rendered[] = $html;
            }
        }

        if ($rendered === []) {
            return '';
        }
        if (count($rendered) === 1) {
            return '<div class="inv-page"><article class="inv-card">' . $rendered[0] . '</article></div>';
        }

        $out = '<div class="inv-page"><div class="inv-book" data-inv-book>';
        foreach ($rendered as $index => $html) {
            $out .= '<section class="inv-book__page' . ($index === 0 ? ' is-active' : '') . '">'
                . '<article class="inv-card">' . $html . '</article></section>';
        }
        return $out . '</div></div>';
    }

    /**
     * Render the components of a structured (admin-built) template.
     *
     * @param array<int,array<string,mixed>> $components
     */
    public function renderComponents(array $components, TemplateContext $context): string
    {
        $out = '';
        foreach ($components as $component) {
            if ((int) ($component['is_visible'] ?? 1) !== 1) {
                continue;
            }
            $content = (string) ($component['content'] ?? '');
            if (trim($content) === '') {
                continue;
            }
            $styles = is_array($component['styles'] ?? null) ? $component['styles'] : [];
            $style = '';
            foreach ($styles as $property => $value) {
                if (!is_string($property) || !is_scalar($value)) {
                    continue;
                }
                $property = preg_replace('/[^a-z\-]/', '', strtolower($property)) ?? '';
                $value = preg_replace('/[<>"\'{};]/', '', (string) $value) ?? '';
                if ($property !== '' && $value !== '') {
                    $style .= $property . ':' . $value . ';';
                }
            }
            $out .= '<div class="inv-component inv-component--' . e((string) ($component['type'] ?? 'section')) . '"'
                . ($style !== '' ? ' style="' . e($style) . '"' : '') . '>'
                . $this->renderCustomHtml($content, $context)
                . '</div>';
        }
        return $out;
    }

    /**
     * Seed an invitation's content with the template's demo data, so the very
     * first preview already looks like a finished card.
     *
     * @return array<string,mixed>
     */
    public function starterValues(array $template): array
    {
        $values = [];
        foreach ($template['fields'] ?? [] as $field) {
            $key = (string) $field['field_key'];
            $default = $field['default_value'] ?? null;
            if ($default !== null && $default !== '') {
                $values[$key] = $default;
            }
        }
        $demo = is_array($template['demo_data'] ?? null) ? $template['demo_data'] : [];
        foreach ($demo as $key => $value) {
            $values[(string) $key] = $value;
        }
        return $values;
    }
}
