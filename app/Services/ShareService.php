<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Lang;
use App\Core\Str;
use App\Core\Url;
use App\Repositories\InvitationDataRepository;

/**
 * Share links and the auto-composed WhatsApp message.
 *
 * The message is written in the invitation's own language, uses the names the
 * owner entered, and ends with the short link so it stays readable inside a
 * WhatsApp bubble.
 */
final class ShareService
{
    public const CHANNELS = ['whatsapp', 'facebook', 'telegram', 'x', 'email', 'copy', 'qr'];

    public function __construct(
        private readonly InvitationDataRepository $data = new InvitationDataRepository()
    ) {
    }

    /** The share text, localised and filled from the invitation content. */
    public function message(array $invitation): string
    {
        $values = $this->data->forInvitation((int) $invitation['id']);
        $locale = (string) ($invitation['language'] ?? 'en');

        $names = $this->headline($invitation, $values);
        $date = $this->formattedDate($invitation, $locale);
        $venue = trim((string) ($values['venue'] ?? $values['venue_name'] ?? ''));
        $link = Url::shortInvite((string) $invitation['short_code']);

        $lines = [];
        $lines[] = $this->translate('share.greeting', $locale);
        $lines[] = '';
        if ($names !== '') {
            $lines[] = '✨ ' . $names;
        }
        if ($date !== '') {
            $lines[] = '📅 ' . $date;
        }
        if ($venue !== '') {
            $lines[] = '📍 ' . $venue;
        }
        $lines[] = '';
        $lines[] = $this->translate('share.cta', $locale);
        $lines[] = $link;

        return implode("\n", $lines);
    }

    private function translate(string $key, string $locale): string
    {
        $previous = Lang::locale();
        if ($locale !== $previous) {
            Lang::setLocale($locale);
        }
        $value = Lang::get($key);
        if ($locale !== $previous) {
            Lang::setLocale($previous);
        }
        return $value;
    }

    private function headline(array $invitation, array $values): string
    {
        $groom = trim((string) ($values['groom_name'] ?? ''));
        $bride = trim((string) ($values['bride_name'] ?? ''));
        if ($groom !== '' && $bride !== '') {
            return $groom . ' ❤️ ' . $bride;
        }
        return trim((string) $invitation['title']);
    }

    private function formattedDate(array $invitation, string $locale): string
    {
        $eventAt = $invitation['event_at'] ?? $invitation['event_date'] ?? null;
        if (!is_string($eventAt) || $eventAt === '') {
            return '';
        }
        $timestamp = strtotime($eventAt);
        if ($timestamp === false) {
            return '';
        }
        if ($locale === 'en') {
            return date('l, j F Y', $timestamp) . (date('H:i', $timestamp) !== '00:00' ? ' · ' . date('g:i A', $timestamp) : '');
        }
        $weekday = $this->translate('date.weekdays.' . strtolower(date('D', $timestamp)), $locale);
        $month = $this->translate('date.months.' . strtolower(date('M', $timestamp)), $locale);
        $out = $weekday . ', ' . date('j', $timestamp) . ' ' . $month . ' ' . date('Y', $timestamp);
        if (date('H:i', $timestamp) !== '00:00') {
            $out .= ' · ' . date('g:i A', $timestamp);
        }
        return $out;
    }

    public function whatsappUrl(array $invitation, ?string $phone = null): string
    {
        $text = rawurlencode($this->message($invitation));
        $number = $phone === null ? '' : Str::whatsappNumber($phone);
        return $number === ''
            ? 'https://wa.me/?text=' . $text
            : 'https://wa.me/' . $number . '?text=' . $text;
    }

    public function facebookUrl(array $invitation): string
    {
        return 'https://www.facebook.com/sharer/sharer.php?u=' . rawurlencode($this->publicUrl($invitation));
    }

    public function telegramUrl(array $invitation): string
    {
        return 'https://t.me/share/url?url=' . rawurlencode($this->publicUrl($invitation))
            . '&text=' . rawurlencode($this->message($invitation));
    }

    public function xUrl(array $invitation): string
    {
        return 'https://twitter.com/intent/tweet?url=' . rawurlencode($this->publicUrl($invitation))
            . '&text=' . rawurlencode(mb_substr((string) $invitation['title'], 0, 180));
    }

    public function emailUrl(array $invitation): string
    {
        return 'mailto:?subject=' . rawurlencode((string) $invitation['title'])
            . '&body=' . rawurlencode($this->message($invitation));
    }

    public function publicUrl(array $invitation): string
    {
        return Url::invite((string) $invitation['slug']);
    }

    /** @return array<string,string> channel => url */
    public function allLinks(array $invitation): array
    {
        return [
            'whatsapp' => $this->whatsappUrl($invitation),
            'facebook' => $this->facebookUrl($invitation),
            'telegram' => $this->telegramUrl($invitation),
            'x'        => $this->xUrl($invitation),
            'email'    => $this->emailUrl($invitation),
            'copy'     => $this->publicUrl($invitation),
        ];
    }

    public static function isValidChannel(string $channel): bool
    {
        return in_array($channel, self::CHANNELS, true);
    }
}
