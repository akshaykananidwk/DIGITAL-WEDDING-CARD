<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Str;
use App\Repositories\InvitationRepository;

/**
 * Unique, SEO-friendly invitation slugs and short codes.
 *
 * Collisions are resolved by suffixing, and a small blocklist keeps
 * invitation slugs from shadowing application routes.
 */
final class SlugService
{
    /** Slugs that would collide with a real route or look like an attack. */
    private const RESERVED = [
        'admin', 'api', 'install', 'login', 'logout', 'register', 'dashboard',
        'invite', 'i', 'templates', 'template', 'categories', 'category',
        'assets', 'uploads', 'storage', 'app', 'database', 'docs', 'sitemap',
        'robots', 'manifest', 'service-worker', 'favicon', 'rsvp', 'pdf', 'qr',
        'password', 'profile', 'settings', 'builder', 'analytics', 'new', 'edit',
        'create', 'delete', 'me', 'us', 'null', 'undefined', 'true', 'false',
    ];

    public function __construct(
        private readonly InvitationRepository $invitations = new InvitationRepository()
    ) {
    }

    /**
     * Build a slug from a title, guaranteed unique.
     *
     * "Rahul weds Priya" becomes rahul-weds-priya; Gujarati titles are
     * transliterated when intl is available and otherwise fall back to a
     * readable short code so the URL is never empty or ambiguous.
     */
    public function forTitle(string $title, ?int $ignoreId = null): string
    {
        $base = Str::slug($title);
        $base = $this->trimToWords($base, 6);

        if ($base === '' || strlen($base) < 3 || in_array($base, self::RESERVED, true)) {
            $base = 'invite-' . strtolower(Str::shortCode(5));
        }

        return $this->makeUnique($base, $ignoreId);
    }

    /** Validate and uniquify a slug a user typed themselves. */
    public function fromUserInput(string $candidate, ?int $ignoreId = null): string
    {
        $slug = Str::slug($candidate);
        $slug = $this->trimToWords($slug, 8);
        if ($slug === '' || strlen($slug) < 3) {
            throw new \InvalidArgumentException('Please use at least 3 letters or numbers for the link.');
        }
        if (in_array($slug, self::RESERVED, true)) {
            throw new \InvalidArgumentException('That link is reserved. Please choose another.');
        }
        return $this->makeUnique($slug, $ignoreId);
    }

    public function isAvailable(string $slug, ?int $ignoreId = null): bool
    {
        $slug = Str::slug($slug);
        if ($slug === '' || in_array($slug, self::RESERVED, true)) {
            return false;
        }
        return !$this->invitations->slugExists($slug, $ignoreId);
    }

    private function makeUnique(string $base, ?int $ignoreId): string
    {
        $base = substr($base, 0, 150);
        if (!$this->invitations->slugExists($base, $ignoreId)) {
            return $base;
        }
        // A numeric suffix first (nicer URLs), then a random code if the
        // namespace is genuinely crowded.
        for ($i = 2; $i <= 30; $i++) {
            $candidate = $base . '-' . $i;
            if (!$this->invitations->slugExists($candidate, $ignoreId)) {
                return $candidate;
            }
        }
        do {
            $candidate = $base . '-' . strtolower(Str::shortCode(5));
        } while ($this->invitations->slugExists($candidate, $ignoreId));

        return $candidate;
    }

    private function trimToWords(string $slug, int $maxWords): string
    {
        $parts = array_slice(array_filter(explode('-', $slug)), 0, $maxWords);
        return implode('-', $parts);
    }

    /** Short code for /i/XXXXXX, unique and unambiguous. */
    public function shortCode(): string
    {
        $length = 6;
        $attempts = 0;
        do {
            $code = Str::shortCode($length);
            $attempts++;
            // Widen the alphabet space rather than looping forever once the
            // table is large.
            if ($attempts % 12 === 0 && $length < 10) {
                $length++;
            }
        } while ($this->invitations->shortCodeExists($code));

        return $code;
    }
}
