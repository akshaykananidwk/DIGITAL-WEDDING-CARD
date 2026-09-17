<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Url;
use App\Repositories\InvitationDataRepository;

/**
 * .ics generation.
 *
 * Written by hand rather than with a library: the format is small, and doing
 * it here means correct folding, escaping and IST handling.
 */
final class CalendarService
{
    private const TIMEZONE = 'Asia/Kolkata';

    public function __construct(
        private readonly InvitationDataRepository $data = new InvitationDataRepository()
    ) {
    }

    public function forInvitation(array $invitation): string
    {
        $values = $this->data->forInvitation((int) $invitation['id']);

        $start = $invitation['event_at'] ?? null;
        $timestamp = is_string($start) && $start !== '' ? strtotime($start) : false;
        if ($timestamp === false) {
            $timestamp = time() + 86400;
        }
        $end = $timestamp + 10800;

        $title = trim(strip_tags((string) $invitation['title']));
        $location = trim(strip_tags((string) ($values['venue_address'] ?? $values['venue'] ?? '')));
        $description = trim(strip_tags((string) ($values['custom_message'] ?? '')));
        $url = Url::invite((string) $invitation['slug']);

        $lines = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//Shubh Kankotri//Invitation//EN',
            'CALSCALE:GREGORIAN',
            'METHOD:PUBLISH',
            'BEGIN:VTIMEZONE',
            'TZID:' . self::TIMEZONE,
            'BEGIN:STANDARD',
            'DTSTART:19700101T000000',
            'TZOFFSETFROM:+0530',
            'TZOFFSETTO:+0530',
            'TZNAME:IST',
            'END:STANDARD',
            'END:VTIMEZONE',
            'BEGIN:VEVENT',
            'UID:' . $invitation['short_code'] . '@' . $this->hostname(),
            'DTSTAMP:' . gmdate('Ymd\THis\Z'),
            'DTSTART;TZID=' . self::TIMEZONE . ':' . date('Ymd\THis', $timestamp),
            'DTEND;TZID=' . self::TIMEZONE . ':' . date('Ymd\THis', $end),
            'SUMMARY:' . $this->escape($title),
            'DESCRIPTION:' . $this->escape(($description !== '' ? $description . '\\n\\n' : '') . $url),
            'LOCATION:' . $this->escape($location),
            'URL:' . $this->escape($url),
            'STATUS:CONFIRMED',
            'TRANSP:OPAQUE',
            'BEGIN:VALARM',
            'TRIGGER:-P1D',
            'ACTION:DISPLAY',
            'DESCRIPTION:' . $this->escape($title . ' is tomorrow'),
            'END:VALARM',
            'END:VEVENT',
            'END:VCALENDAR',
        ];

        $folded = array_map([$this, 'fold'], $lines);
        return implode("\r\n", $folded) . "\r\n";
    }

    private function hostname(): string
    {
        $host = parse_url(Url::base(), PHP_URL_HOST);
        return is_string($host) && $host !== '' ? $host : 'invitation.local';
    }

    /** Escape per RFC 5545: backslash, semicolon, comma and newlines. */
    private function escape(string $value): string
    {
        $value = str_replace(['\\', ';', ','], ['\\\\', '\;', '\\,'], $value);
        return str_replace(["\r\n", "\n", "\r"], '\\n', $value);
    }

    /** Fold lines at 75 octets, as the RFC requires. */
    private function fold(string $line): string
    {
        if (strlen($line) <= 75) {
            return $line;
        }
        $out = '';
        $remaining = $line;
        $first = true;
        while ($remaining !== '') {
            $limit = $first ? 75 : 74;
            $chunk = mb_strcut($remaining, 0, $limit, 'UTF-8');
            $out .= ($first ? '' : "\r\n ") . $chunk;
            $remaining = substr($remaining, strlen($chunk));
            $first = false;
        }
        return $out;
    }

    public function filename(array $invitation): string
    {
        $slug = preg_replace('/[^a-z0-9\-]/i', '', (string) $invitation['slug']) ?: 'invitation';
        return $slug . '.ics';
    }
}
