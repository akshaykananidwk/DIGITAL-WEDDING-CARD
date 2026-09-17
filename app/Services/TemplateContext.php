<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Lang;
use App\Core\Str;
use App\Core\Url;

/**
 * Everything an invitation layout needs, in one object.
 *
 * Layouts (app/Views/invite/layouts/*.php) receive a TemplateContext and ask
 * it for values. All accessors escape by default, so a layout cannot
 * accidentally emit unescaped user content; raw() exists for the few places
 * where markup is intentional and has already been sanitised.
 */
final class TemplateContext
{
    /** @var array<string,mixed> */
    private array $data;
    /** @var array<string,array<string,mixed>> */
    private array $fields;
    /** @var array<string,array<string,mixed>> */
    private array $sections;

    public function __construct(
        private readonly array $invitation,
        private readonly array $template,
        array $data,
        array $fields,
        array $sections,
        private readonly array $photos,
        private readonly ?array $music,
        private readonly array $theme,
        private readonly bool $preview = false
    ) {
        $this->data = $data;
        $this->fields = $fields;
        $this->sections = $sections;
    }

    // ------------------------------------------------------------------
    //  Field values
    // ------------------------------------------------------------------

    /** Escaped field value, ready to print. */
    public function get(string $key, string $default = ''): string
    {
        $value = $this->rawValue($key);
        if ($value === null || $value === '' || is_array($value)) {
            return $default === '' ? '' : e($default);
        }
        return e((string) $value);
    }

    /** Unescaped value - only for building URLs and attributes deliberately. */
    public function raw(string $key, mixed $default = null): mixed
    {
        $value = $this->rawValue($key);
        return $value === null || $value === '' ? $default : $value;
    }

    public function has(string $key): bool
    {
        $value = $this->rawValue($key);
        if (is_array($value)) {
            return $value !== [];
        }
        return $value !== null && trim((string) $value) !== '';
    }

    /** First key that has a value - handy for template variants. */
    public function first(string ...$keys): string
    {
        foreach ($keys as $key) {
            if ($this->has($key)) {
                return $this->get($key);
            }
        }
        return '';
    }

    private function rawValue(string $key): mixed
    {
        if (array_key_exists($key, $this->data)) {
            return $this->data[$key];
        }
        // Fall back to the field's configured default.
        return $this->fields[$key]['default_value'] ?? null;
    }

    /** Escaped value with single newlines turned into <br>. */
    public function multiline(string $key): string
    {
        if (!$this->has($key)) {
            return '';
        }
        return nl2br(e((string) $this->rawValue($key)), false);
    }

    /** @return array<int,string> a list value (family names, event list) */
    public function listOf(string $key): array
    {
        $value = $this->rawValue($key);
        if (is_array($value)) {
            return array_values(array_filter(array_map(
                static fn ($item) => is_scalar($item) ? trim((string) $item) : '',
                $value
            ), static fn ($item) => $item !== ''));
        }
        if (!is_string($value) || trim($value) === '') {
            return [];
        }
        // Users type one name per line; commas are also accepted.
        $parts = preg_split('/\R|,/u', $value) ?: [];
        return array_values(array_filter(array_map('trim', $parts), static fn ($item) => $item !== ''));
    }

    /** @return array<string,mixed> every resolved value (for JSON/preview) */
    public function all(): array
    {
        return $this->data;
    }

    // ------------------------------------------------------------------
    //  Dates and times
    // ------------------------------------------------------------------

    public function date(string $key, string $format = 'd M Y'): string
    {
        $value = $this->rawValue($key);
        if (!is_string($value) || trim($value) === '') {
            return '';
        }
        $timestamp = strtotime($value);
        return $timestamp === false ? e($value) : e(date($format, $timestamp));
    }

    /** Localised long date, e.g. "Friday, 25 December 2026". */
    public function longDate(string $key): string
    {
        $value = $this->rawValue($key);
        if (!is_string($value) || trim($value) === '') {
            return '';
        }
        $timestamp = strtotime($value);
        if ($timestamp === false) {
            return e($value);
        }
        $locale = Lang::locale();
        if ($locale === 'en') {
            return e(date('l, j F Y', $timestamp));
        }
        $weekday = Lang::get('date.weekdays.' . strtolower(date('D', $timestamp)));
        $month = Lang::get('date.months.' . strtolower(date('M', $timestamp)));
        return e($weekday . ', ' . date('j', $timestamp) . ' ' . $month . ' ' . date('Y', $timestamp));
    }

    public function time(string $key, string $format = 'g:i A'): string
    {
        $value = $this->rawValue($key);
        if (!is_string($value) || trim($value) === '') {
            return '';
        }
        $timestamp = strtotime($value);
        return $timestamp === false ? e($value) : e(date($format, $timestamp));
    }

    /** ISO 8601 datetime for the countdown script and structured data. */
    public function eventIso(): string
    {
        $eventAt = $this->invitation['event_at'] ?? null;
        if (!is_string($eventAt) || $eventAt === '') {
            return '';
        }
        $timestamp = strtotime($eventAt);
        return $timestamp === false ? '' : date('c', $timestamp);
    }

    public function eventTimestamp(): int
    {
        $eventAt = $this->invitation['event_at'] ?? null;
        if (!is_string($eventAt) || $eventAt === '') {
            return 0;
        }
        $timestamp = strtotime($eventAt);
        return $timestamp === false ? 0 : $timestamp;
    }

    public function isPastEvent(): bool
    {
        $timestamp = $this->eventTimestamp();
        return $timestamp > 0 && $timestamp < time();
    }

    // ------------------------------------------------------------------
    //  Sections
    // ------------------------------------------------------------------

    /**
     * Should a section render?
     *
     * A section is on unless the owner switched it off, and a section the
     * template does not support is always off.
     */
    public function showSection(string $key, bool $default = true): bool
    {
        if (!$this->templateSupports($key)) {
            return false;
        }
        if (isset($this->sections[$key])) {
            return (int) $this->sections[$key]['is_visible'] === 1;
        }
        $settings = $this->settings();
        if (array_key_exists('section_' . $key, $settings)) {
            return (bool) $settings['section_' . $key];
        }
        return $default;
    }

    private function templateSupports(string $section): bool
    {
        return match ($section) {
            'music'     => (int) ($this->template['supports_music'] ?? 1) === 1,
            'gallery'   => (int) ($this->template['supports_gallery'] ?? 1) === 1,
            'countdown' => (int) ($this->template['supports_countdown'] ?? 1) === 1,
            'rsvp'      => (int) ($this->template['supports_rsvp'] ?? 1) === 1,
            'map'       => (int) ($this->template['supports_map'] ?? 1) === 1,
            default     => true,
        };
    }

    public function sectionTitle(string $key, string $default = ''): string
    {
        $title = $this->sections[$key]['title'] ?? null;
        if (is_string($title) && trim($title) !== '') {
            return e($title);
        }
        return $default === '' ? '' : e($default);
    }

    /** @return array<string,mixed> */
    public function settings(): array
    {
        $settings = $this->invitation['settings'] ?? [];
        return is_array($settings) ? $settings : [];
    }

    public function setting(string $key, mixed $default = null): mixed
    {
        return $this->settings()[$key] ?? $default;
    }

    // ------------------------------------------------------------------
    //  Theme
    // ------------------------------------------------------------------

    public function theme(string $key, string $default = ''): string
    {
        $value = $this->theme[$key] ?? $default;
        return is_scalar($value) ? (string) $value : $default;
    }

    /** CSS custom properties block for the invitation root element. */
    public function cssVariables(): string
    {
        $map = [
            '--inv-primary'        => $this->theme('primary', '#C8102E'),
            '--inv-secondary'      => $this->theme('secondary', '#F0B429'),
            '--inv-background'     => $this->theme('background', '#FFF8EE'),
            '--inv-surface'        => $this->theme('surface', '#FFFFFF'),
            '--inv-text'           => $this->theme('text', '#3D2B1F'),
            '--inv-muted'          => $this->theme('muted', '#7A6A55'),
            '--inv-accent'         => $this->theme('accent', $this->theme('secondary', '#F0B429')),
            '--inv-border'         => $this->theme('border', 'rgba(0,0,0,.12)'),
            '--inv-heading-font'   => $this->theme('heading_font_stack', "'GreatVibes', cursive"),
            '--inv-body-font'      => $this->theme('body_font_stack', "'NotoSans', system-ui, sans-serif"),
            '--inv-heading-scale'  => $this->theme('heading_scale', '1'),
            '--inv-radius'         => $this->theme('radius', '18px'),
        ];
        $out = [];
        foreach ($map as $property => $value) {
            // Values come from admin-authored themes and a constrained set of
            // user choices; still sanitise so nothing can close the attribute.
            $clean = preg_replace('/[<>"\'{};]/', '', (string) $value) ?? '';
            $out[] = $property . ':' . trim($clean);
        }
        return implode(';', $out);
    }

    public function ornament(): string
    {
        $value = $this->theme('ornament', 'paisley');
        return preg_match('/^[a-z0-9\-]+$/', $value) === 1 ? $value : 'paisley';
    }

    public function motion(): string
    {
        $value = (string) ($this->theme['motion'] ?? 'gentle');
        return in_array($value, ['none', 'gentle', 'rich'], true) ? $value : 'gentle';
    }

    // ------------------------------------------------------------------
    //  Media
    // ------------------------------------------------------------------

    /** @return array<int,array<string,mixed>> */
    public function photos(?string $role = null): array
    {
        if ($role === null) {
            return $this->photos;
        }
        return array_values(array_filter(
            $this->photos,
            static fn (array $photo): bool => (string) ($photo['role'] ?? 'gallery') === $role
        ));
    }

    public function photoUrl(array $photo, bool $thumb = false): string
    {
        $path = $thumb && !empty($photo['thumb_path']) ? $photo['thumb_path'] : ($photo['path'] ?? '');
        return $path === '' ? '' : Url::upload((string) $path);
    }

    public function heroPhotoUrl(): string
    {
        $hero = $this->photos('hero');
        if ($hero !== []) {
            return $this->photoUrl($hero[0]);
        }
        $gallery = $this->photos('gallery');
        return $gallery === [] ? '' : $this->photoUrl($gallery[0]);
    }

    public function hasMusic(): bool
    {
        return $this->music !== null && ($this->music['path'] ?? '') !== '';
    }

    public function musicUrl(): string
    {
        return $this->hasMusic() ? Url::upload((string) $this->music['path']) : '';
    }

    public function musicTitle(): string
    {
        return $this->hasMusic() ? e((string) ($this->music['title'] ?? 'Background music')) : '';
    }

    public function musicAutoplay(): bool
    {
        return $this->hasMusic() && (int) ($this->music['autoplay'] ?? 0) === 1;
    }

    // ------------------------------------------------------------------
    //  Links
    // ------------------------------------------------------------------

    public function publicUrl(): string
    {
        return Url::invite((string) ($this->invitation['slug'] ?? ''));
    }

    public function shortUrl(): string
    {
        return Url::shortInvite((string) ($this->invitation['short_code'] ?? ''));
    }

    public function pdfUrl(): string
    {
        return Url::to('invite/' . ($this->invitation['slug'] ?? '') . '/pdf');
    }

    public function qrUrl(string $format = 'png'): string
    {
        return Url::to('invite/' . ($this->invitation['slug'] ?? '') . '/qr.' . ($format === 'svg' ? 'svg' : 'png'));
    }

    public function icsUrl(): string
    {
        return Url::to('invite/' . ($this->invitation['slug'] ?? '') . '/calendar.ics');
    }

    public function rsvpUrl(): string
    {
        return Url::to('invite/' . ($this->invitation['slug'] ?? '') . '/rsvp');
    }

    /** Validated Google Maps link, or a search URL built from the address. */
    public function mapsUrl(): string
    {
        $explicit = $this->raw('maps_url');
        if (is_string($explicit) && preg_match('#^https?://#i', $explicit) === 1) {
            return $explicit;
        }
        $venue = trim((string) ($this->raw('venue_address') ?? $this->raw('venue') ?? ''));
        if ($venue === '') {
            return '';
        }
        return 'https://www.google.com/maps/search/?api=1&query=' . rawurlencode($venue);
    }

    public function googleCalendarUrl(): string
    {
        $timestamp = $this->eventTimestamp();
        if ($timestamp === 0) {
            return '';
        }
        $title = trim(strip_tags((string) ($this->invitation['title'] ?? 'Celebration')));
        $end = $timestamp + 10800; // assume a three hour function
        $details = trim(strip_tags((string) ($this->raw('custom_message') ?? '')));
        $location = trim(strip_tags((string) ($this->raw('venue_address') ?? $this->raw('venue') ?? '')));

        return 'https://calendar.google.com/calendar/render?' . http_build_query([
            'action'   => 'TEMPLATE',
            'text'     => $title,
            'dates'    => gmdate('Ymd\THis\Z', $timestamp) . '/' . gmdate('Ymd\THis\Z', $end),
            'details'  => mb_substr($details, 0, 500) . "\n" . $this->publicUrl(),
            'location' => mb_substr($location, 0, 200),
            'ctz'      => 'Asia/Kolkata',
        ]);
    }

    /** wa.me link for the contact number on the invitation. */
    public function whatsappContactUrl(): string
    {
        $number = Str::whatsappNumber((string) ($this->raw('whatsapp_number') ?? $this->raw('rsvp_phone') ?? ''));
        if ($number === '') {
            return '';
        }
        $message = Lang::get('invite.whatsapp_contact', ['title' => (string) ($this->invitation['title'] ?? '')]);
        return 'https://wa.me/' . $number . '?text=' . rawurlencode($message);
    }

    public function telLink(string $key): string
    {
        $number = Str::phone((string) ($this->raw($key) ?? ''));
        return $number === '' ? '' : 'tel:' . $number;
    }

    // ------------------------------------------------------------------
    //  Meta
    // ------------------------------------------------------------------

    public function invitation(): array
    {
        return $this->invitation;
    }

    public function template(): array
    {
        return $this->template;
    }

    public function title(): string
    {
        return e((string) ($this->invitation['title'] ?? ''));
    }

    public function isPreview(): bool
    {
        return $this->preview;
    }

    public function showWatermark(): bool
    {
        return (bool) $this->setting('watermark', false) && FeatureFlagService::instance()->enabled('watermark');
    }

    /** @return array<int,array{label:string,date:string,time:string,venue:string}> */
    public function eventSchedule(): array
    {
        $events = $this->raw('events');
        if (is_array($events)) {
            $out = [];
            foreach ($events as $event) {
                if (!is_array($event)) {
                    continue;
                }
                $out[] = [
                    'label' => e((string) ($event['label'] ?? '')),
                    'date'  => e((string) ($event['date'] ?? '')),
                    'time'  => e((string) ($event['time'] ?? '')),
                    'venue' => e((string) ($event['venue'] ?? '')),
                ];
            }
            return $out;
        }

        // Otherwise assemble the schedule from the well-known field keys.
        $known = [
            'haldi'     => 'invite.events.haldi',
            'mehndi'    => 'invite.events.mehndi',
            'sangeet'   => 'invite.events.sangeet',
            'garba'     => 'invite.events.garba',
            'wedding'   => 'invite.events.wedding',
            'reception' => 'invite.events.reception',
        ];
        $out = [];
        foreach ($known as $prefix => $labelKey) {
            if (!$this->has($prefix . '_date') && !$this->has($prefix . '_time')) {
                continue;
            }
            $out[] = [
                'label' => e(Lang::get($labelKey)),
                'date'  => $this->date($prefix . '_date', 'j M Y'),
                'time'  => $this->time($prefix . '_time'),
                'venue' => $this->get($prefix . '_venue'),
            ];
        }
        return $out;
    }
}
