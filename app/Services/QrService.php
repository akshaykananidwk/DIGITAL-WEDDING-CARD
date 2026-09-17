<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Cache;
use App\Core\Qr\QrCode;
use App\Core\Url;
use App\Repositories\InvitationRepository;

/**
 * QR codes for invitations, venues and RSVP.
 *
 * The invitation QR is cached on disk under /uploads/qr so repeat downloads
 * and the PDF export do not re-encode it, and is invalidated whenever the
 * slug or short code changes.
 */
final class QrService
{
    public function __construct(
        private readonly InvitationRepository $invitations = new InvitationRepository()
    ) {
    }

    /**
     * PNG for an invitation, generated on first use and cached.
     *
     * @return array{bytes:string,path:string|null}
     */
    public function forInvitation(array $invitation, bool $persist = false, int $scale = 10): array
    {
        $target = Url::shortInvite((string) $invitation['short_code']);
        $relative = 'qr/' . $invitation['id'] . '-' . substr(sha1($target), 0, 10) . '.png';
        $absolute = UPLOAD_PATH . '/' . $relative;

        if (is_file($absolute)) {
            return ['bytes' => (string) file_get_contents($absolute), 'path' => $relative];
        }

        $bytes = $this->png($target, $scale, $this->darkColor($invitation));

        if ($persist) {
            $dir = dirname($absolute);
            if (!is_dir($dir)) {
                @mkdir($dir, 0755, true);
            }
            if (@file_put_contents($absolute, $bytes) !== false) {
                $this->invitations->update((int) $invitation['id'], ['qr_path' => $relative]);
                return ['bytes' => $bytes, 'path' => $relative];
            }
        }

        return ['bytes' => $bytes, 'path' => null];
    }

    public function svgForInvitation(array $invitation, int $scale = 10): string
    {
        return $this->svg(
            Url::shortInvite((string) $invitation['short_code']),
            $scale,
            $this->darkColor($invitation)
        );
    }

    /** Print-friendly QR: pure black on white, generous quiet zone. */
    public function printForInvitation(array $invitation, int $scale = 14): string
    {
        return $this->png(Url::shortInvite((string) $invitation['short_code']), $scale, '#000000', 6);
    }

    /** QR pointing at the venue on Google Maps. */
    public function forVenue(string $mapsUrl, int $scale = 8): string
    {
        return $this->png($mapsUrl, $scale, '#1F1B16');
    }

    /** QR that opens the RSVP form directly. */
    public function forRsvp(array $invitation, int $scale = 8): string
    {
        return $this->png(
            Url::to('invite/' . $invitation['slug'] . '#rsvp'),
            $scale,
            $this->darkColor($invitation)
        );
    }

    public function png(string $payload, int $scale = 10, string $dark = '#000000', int $margin = 4): string
    {
        return QrCode::encode($payload, QrCode::ECC_MEDIUM)->toPng($scale, $margin, $dark, '#FFFFFF');
    }

    public function svg(string $payload, int $scale = 10, string $dark = '#000000', int $margin = 4): string
    {
        return QrCode::encode($payload, QrCode::ECC_MEDIUM)->toSvg($scale, $margin, $dark, '#FFFFFF');
    }

    /** Data URI, for embedding a QR directly in an invitation page. */
    public function dataUri(string $payload, int $scale = 6, string $dark = '#000000'): string
    {
        return 'data:image/png;base64,' . base64_encode($this->png($payload, $scale, $dark));
    }

    private function darkColor(array $invitation): string
    {
        // Keep QR contrast high: use a dark version of the theme colour only
        // when it is dark enough to scan reliably.
        $overrides = is_array($invitation['theme_overrides'] ?? null) ? $invitation['theme_overrides'] : [];
        $candidate = (string) ($overrides['primary'] ?? '');
        if (preg_match('/^#[0-9a-fA-F]{6}$/', $candidate) !== 1) {
            return '#1A1A1A';
        }
        [$r, $g, $b] = [
            hexdec(substr($candidate, 1, 2)),
            hexdec(substr($candidate, 3, 2)),
            hexdec(substr($candidate, 5, 2)),
        ];
        $luminance = (0.2126 * $r + 0.7152 * $g + 0.0722 * $b) / 255;
        return $luminance < 0.45 ? strtoupper($candidate) : '#1A1A1A';
    }

    /** Drop the cached QR file when the URL behind it changes. */
    public function forgetCache(int $invitationId): void
    {
        foreach (glob(UPLOAD_PATH . '/qr/' . $invitationId . '-*.png') ?: [] as $file) {
            @unlink($file);
        }
        $this->invitations->update($invitationId, ['qr_path' => null]);
        Cache::forget('qr:' . $invitationId);
    }
}
