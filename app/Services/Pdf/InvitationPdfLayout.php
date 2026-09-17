<?php

declare(strict_types=1);

namespace App\Services\Pdf;

use App\Core\Lang;
use App\Core\Pdf\PdfDocument;
use App\Core\Str;
use App\Services\MediaService;
use App\Services\QrService;
use App\Services\TemplateContext;

/**
 * Draws an invitation onto a PDF page.
 *
 * This is a print layout, not a screenshot of the web page: a bordered card
 * with vector ornaments, the couple/event details set in the right script,
 * the programme, family names, a photo page and the QR code. It is composed
 * from the invitation's own data and theme, so every template produces a
 * recognisably matching PDF.
 */
final class InvitationPdfLayout
{
    private const FONT_SCRIPT = 'script';
    private const FONT_SERIF  = 'serif';
    private const FONT_SANS   = 'sans';
    private const FONT_GU     = 'gujarati';
    private const FONT_HI     = 'devanagari';

    private float $margin = 46.0;
    private float $contentWidth;
    /** Ink stops here: nothing is drawn below it. */
    private const BOTTOM_MARGIN = 44.0;

    private float $cursor = 0.0;
    /** True once the layout has spilled onto a continuation page. */
    private bool $continuation = false;

    /** @param array<string,string> $fontFiles alias => absolute path */
    public function __construct(
        private readonly PdfDocument $pdf,
        private readonly TemplateContext $context,
        private readonly array $fontFiles
    ) {
        $this->contentWidth = $pdf->width() - ($this->margin * 2);
    }

    public function registerFonts(): void
    {
        foreach ($this->fontFiles as $alias => $path) {
            if ($path !== '') {
                $this->pdf->registerFont($alias, $path);
            }
        }
    }

    public function render(): void
    {
        $this->registerFonts();
        $this->pdf->addPage();

        $this->drawBackground();
        $this->drawBorder();

        $this->cursor = $this->margin + 34;

        $this->drawInvocation();
        $this->drawHeadline();
        $this->drawDateLine();
        $this->drawBlessing();
        $this->drawParents();
        $this->drawVenue();
        $this->drawSchedule();
        $this->drawFamily();
        $this->drawContact();
        $this->drawFooter();

        if ($this->context->photos() !== [] && $this->context->showSection('gallery')) {
            $this->drawGalleryPage();
        }
    }

    // ------------------------------------------------------------------
    //  Chrome
    // ------------------------------------------------------------------

    private function drawBackground(): void
    {
        $background = $this->context->theme('background', '#FFF8EE');
        $this->pdf->gradientRect(
            0,
            0,
            $this->pdf->width(),
            $this->pdf->height(),
            $background,
            $this->shade($background, -0.06)
        );
    }

    private function drawBorder(): void
    {
        $primary = $this->context->theme('primary', '#C8102E');
        $secondary = $this->context->theme('secondary', '#F0B429');
        $inset = 22.0;

        $this->pdf->rect(
            $inset,
            $inset,
            $this->pdf->width() - ($inset * 2),
            $this->pdf->height() - ($inset * 2),
            null,
            $primary,
            1.6
        );
        $this->pdf->rect(
            $inset + 6,
            $inset + 6,
            $this->pdf->width() - (($inset + 6) * 2),
            $this->pdf->height() - (($inset + 6) * 2),
            null,
            $secondary,
            0.7
        );

        $this->drawCornerOrnaments($inset + 6, $secondary, $primary);
    }

    /** Small paisley-style corner flourishes, drawn as vectors. */
    private function drawCornerOrnaments(float $inset, string $light, string $dark): void
    {
        $size = 26.0;
        $corners = [
            [$inset, $inset, 1, 1],
            [$this->pdf->width() - $inset, $inset, -1, 1],
            [$inset, $this->pdf->height() - $inset, 1, -1],
            [$this->pdf->width() - $inset, $this->pdf->height() - $inset, -1, -1],
        ];

        foreach ($corners as [$x, $y, $sx, $sy]) {
            $this->pdf->line($x, $y + ($size * $sy), $x + ($size * 0.45 * $sx), $y + ($size * 0.45 * $sy), $dark, 0.9);
            $this->pdf->line($x + ($size * $sx), $y, $x + ($size * 0.45 * $sx), $y + ($size * 0.45 * $sy), $dark, 0.9);
            $this->pdf->circle($x + ($size * 0.45 * $sx), $y + ($size * 0.45 * $sy), 3.2, $light);
            $this->pdf->circle($x + ($size * 0.9 * $sx), $y + ($size * 0.18 * $sy), 1.7, $dark);
            $this->pdf->circle($x + ($size * 0.18 * $sx), $y + ($size * 0.9 * $sy), 1.7, $dark);
        }
    }

    /** A rule with a diamond in the middle, used between sections. */
    private function divider(float $y, float $width = 0.5): void
    {
        $secondary = $this->context->theme('secondary', '#F0B429');
        $centre = $this->pdf->width() / 2;
        $half = ($this->contentWidth * $width) / 2;

        $this->pdf->line($centre - $half, $y, $centre - 8, $y, $secondary, 0.7);
        $this->pdf->line($centre + 8, $y, $centre + $half, $y, $secondary, 0.7);
        $this->pdf->polygon([
            [$centre, $y - 4],
            [$centre + 4, $y],
            [$centre, $y + 4],
            [$centre - 4, $y],
        ], $secondary);
    }

    // ------------------------------------------------------------------
    //  Content blocks
    // ------------------------------------------------------------------

    private function drawInvocation(): void
    {
        $text = trim((string) ($this->context->raw('invocation') ?? Lang::get('pdf.invocation')));
        if ($text === '') {
            return;
        }
        $this->setFontFor($text, self::FONT_SERIF, 12);
        $this->pdf->text(
            $this->pdf->width() / 2,
            $this->cursor,
            $text,
            $this->context->theme('primary', '#C8102E'),
            'center'
        );
        $this->cursor += 26;
    }

    private function drawHeadline(): void
    {
        $groom = trim((string) ($this->context->raw('groom_name') ?? ''));
        $bride = trim((string) ($this->context->raw('bride_name') ?? ''));

        if ($groom !== '' && $bride !== '') {
            $this->drawCoupleNames($groom, $bride);
            return;
        }

        $title = trim(strip_tags((string) ($this->context->invitation()['title'] ?? '')));
        if ($title === '') {
            return;
        }
        $this->setFontFor($title, self::FONT_SCRIPT, 34);
        $this->pdf->text(
            $this->pdf->width() / 2,
            $this->cursor + 12,
            $title,
            $this->context->theme('primary', '#C8102E'),
            'center'
        );
        $this->cursor += 48;
        $this->divider($this->cursor);
        $this->cursor += 24;
    }

    private function drawCoupleNames(string $groom, string $bride): void
    {
        $primary = $this->context->theme('primary', '#C8102E');
        $secondary = $this->context->theme('secondary', '#F0B429');
        $centre = $this->pdf->width() / 2;

        $this->setFontFor($groom, self::FONT_SCRIPT, 32);
        $this->pdf->text($centre, $this->cursor + 10, $groom, $primary, 'center');
        $this->cursor += 34;

        $joiner = Lang::get('pdf.weds');
        $this->setFontFor($joiner, self::FONT_SERIF, 11);
        $joinerWidth = $this->pdf->textWidth($joiner);
        $this->pdf->line($centre - 70, $this->cursor - 4, $centre - ($joinerWidth / 2) - 8, $this->cursor - 4, $secondary, 0.7);
        $this->pdf->line($centre + ($joinerWidth / 2) + 8, $this->cursor - 4, $centre + 70, $this->cursor - 4, $secondary, 0.7);
        $this->pdf->text($centre, $this->cursor, $joiner, $secondary, 'center');
        $this->cursor += 26;

        $this->setFontFor($bride, self::FONT_SCRIPT, 32);
        $this->pdf->text($centre, $this->cursor + 10, $bride, $primary, 'center');
        $this->cursor += 40;

        $this->divider($this->cursor);
        $this->cursor += 26;
    }

    private function drawDateLine(): void
    {
        $date = $this->longDateText();
        if ($date !== '') {
            $this->setFontFor($date, self::FONT_SERIF, 14);
            $this->pdf->text(
                $this->pdf->width() / 2,
                $this->cursor,
                $date,
                $this->context->theme('text', '#3D2B1F'),
                'center'
            );
            $this->cursor += 22;
        }

        $time = trim((string) ($this->context->raw('wedding_time') ?? $this->context->raw('event_time') ?? ''));
        if ($time !== '') {
            $timestamp = strtotime('1970-01-01 ' . $time);
            $formatted = $timestamp === false ? $time : date('g:i A', $timestamp);
            $this->setFontFor($formatted, self::FONT_SANS, 11);
            $this->pdf->text(
                $this->pdf->width() / 2,
                $this->cursor,
                $formatted,
                $this->context->theme('muted', '#7A6A55'),
                'center'
            );
            $this->cursor += 20;
        }
        $this->cursor += 8;
    }

    private function longDateText(): string
    {
        $timestamp = $this->context->eventTimestamp();
        if ($timestamp === 0) {
            return '';
        }
        if ((string) ($this->context->invitation()['language'] ?? 'en') === 'en') {
            return date('l, j F Y', $timestamp);
        }
        $weekday = Lang::get('date.weekdays.' . strtolower(date('D', $timestamp)));
        $month = Lang::get('date.months.' . strtolower(date('M', $timestamp)));
        return $weekday . ', ' . date('j', $timestamp) . ' ' . $month . ' ' . date('Y', $timestamp);
    }

    private function drawBlessing(): void
    {
        $message = trim((string) ($this->context->raw('custom_message') ?? $this->context->raw('blessing') ?? ''));
        if ($message === '') {
            return;
        }
        $this->setFontFor($message, self::FONT_SANS, 10.5);
        $this->cursor = $this->pdf->paragraph(
            $this->margin + 24,
            $this->cursor,
            $this->contentWidth - 48,
            mb_substr($message, 0, 600),
            $this->context->theme('text', '#4A3728'),
            'center',
            16
        );
        $this->cursor += 12;
    }

    private function drawParents(): void
    {
        $groomParents = trim((string) ($this->context->raw('groom_parents') ?? ''));
        $brideParents = trim((string) ($this->context->raw('bride_parents') ?? ''));
        if ($groomParents === '' && $brideParents === '') {
            return;
        }
        $this->ensureSpace(90);

        $muted = $this->context->theme('muted', '#7A6A55');
        $textColor = $this->context->theme('text', '#3D2B1F');
        $columnWidth = ($this->contentWidth - 30) / 2;
        $startY = $this->cursor;
        $bottom = $startY;

        if ($groomParents !== '') {
            $label = Lang::get('pdf.groom_family');
            $this->setFontFor($label, self::FONT_SANS, 8.5);
            $this->pdf->text($this->margin + ($columnWidth / 2), $startY, mb_strtoupper($label, 'UTF-8'), $muted, 'center', 0.8);
            $this->setFontFor($groomParents, self::FONT_SERIF, 11);
            $bottom = max($bottom, $this->pdf->paragraph(
                $this->margin,
                $startY + 16,
                $columnWidth,
                $groomParents,
                $textColor,
                'center',
                15
            ));
        }

        if ($brideParents !== '') {
            $label = Lang::get('pdf.bride_family');
            $left = $this->margin + $columnWidth + 30;
            $this->setFontFor($label, self::FONT_SANS, 8.5);
            $this->pdf->text($left + ($columnWidth / 2), $startY, mb_strtoupper($label, 'UTF-8'), $muted, 'center', 0.8);
            $this->setFontFor($brideParents, self::FONT_SERIF, 11);
            $bottom = max($bottom, $this->pdf->paragraph(
                $left,
                $startY + 16,
                $columnWidth,
                $brideParents,
                $textColor,
                'center',
                15
            ));
        }

        $this->cursor = $bottom + 16;
    }

    private function drawVenue(): void
    {
        $venue = trim((string) ($this->context->raw('venue') ?? $this->context->raw('venue_name') ?? ''));
        $address = trim((string) ($this->context->raw('venue_address') ?? ''));
        if ($venue === '' && $address === '') {
            return;
        }
        $this->ensureSpace(110);

        $this->divider($this->cursor, 0.35);
        $this->cursor += 22;

        $this->drawLabel(Lang::get('pdf.venue'));

        if ($venue !== '') {
            $this->setFontFor($venue, self::FONT_SERIF, 13);
            $this->pdf->text(
                $this->pdf->width() / 2,
                $this->cursor,
                $venue,
                $this->context->theme('text', '#3D2B1F'),
                'center'
            );
            $this->cursor += 19;
        }
        if ($address !== '') {
            $this->setFontFor($address, self::FONT_SANS, 10);
            $this->cursor = $this->pdf->paragraph(
                $this->margin + 40,
                $this->cursor,
                $this->contentWidth - 80,
                $address,
                $this->context->theme('muted', '#7A6A55'),
                'center',
                14
            );
        }
        $this->cursor += 12;
    }

    private function drawLabel(string $label): void
    {
        $this->setFontFor($label, self::FONT_SANS, 8.5);
        $this->pdf->text(
            $this->pdf->width() / 2,
            $this->cursor,
            mb_strtoupper($label, 'UTF-8'),
            $this->context->theme('muted', '#7A6A55'),
            'center',
            1.0
        );
        $this->cursor += 17;
    }

    /**
     * Make sure there is room for the next block, starting a continuation page
     * if there is not.
     *
     * A wedding with eight functions and forty names has to fit somewhere: the
     * alternative - which this replaces - was to stop drawing and lose the
     * rest, which is the one outcome a printed invitation cannot have.
     */
    private function ensureSpace(float $needed): void
    {
        if ($this->cursor + $needed <= $this->pdf->height() - self::BOTTOM_MARGIN) {
            return;
        }

        $this->pdf->addPage();
        $this->drawBackground();
        $this->drawBorder();
        $this->cursor = $this->margin + 40;
        $this->continuation = true;
    }

    private function drawSchedule(): void
    {
        $schedule = $this->rawSchedule();
        if ($schedule === []) {
            return;
        }

        $this->drawLabel(Lang::get('pdf.programme'));

        $textColor = $this->context->theme('text', '#3D2B1F');
        $muted = $this->context->theme('muted', '#7A6A55');
        $rule = $this->shade($this->context->theme('secondary', '#F0B429'), 0.35);

        foreach ($schedule as $event) {
            // A row plus its rule; a new page if that no longer fits.
            $this->ensureSpace(24);
            $this->setFontFor($event['label'], self::FONT_SERIF, 11);
            $this->pdf->text($this->margin + 30, $this->cursor, $event['label'], $textColor);

            $when = trim($event['date'] . ($event['time'] !== '' ? '  ·  ' . $event['time'] : ''));
            if ($when !== '') {
                $this->setFontFor($when, self::FONT_SANS, 10);
                $this->pdf->text($this->pdf->width() - $this->margin - 30, $this->cursor, $when, $muted, 'right');
            }
            $this->cursor += 8;
            $this->pdf->line(
                $this->margin + 30,
                $this->cursor,
                $this->pdf->width() - $this->margin - 30,
                $this->cursor,
                $rule,
                0.5,
                [1.6, 1.6]
            );
            $this->cursor += 16;
        }
        $this->cursor += 6;
    }

    /** @return array<int,array{label:string,date:string,time:string}> */
    private function rawSchedule(): array
    {
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
            $date = trim((string) ($this->context->raw($prefix . '_date') ?? ''));
            $time = trim((string) ($this->context->raw($prefix . '_time') ?? ''));
            if ($date === '' && $time === '') {
                continue;
            }
            $dateTimestamp = $date === '' ? false : strtotime($date);
            $timeTimestamp = $time === '' ? false : strtotime('1970-01-01 ' . $time);
            $out[] = [
                'label' => Lang::get($labelKey),
                'date'  => $dateTimestamp === false ? $date : date('j M Y', $dateTimestamp),
                'time'  => $timeTimestamp === false ? $time : date('g:i A', $timeTimestamp),
            ];
        }
        return $out;
    }

    private function drawFamily(): void
    {
        $names = $this->context->listOf('family_names');
        if ($names === []) {
            return;
        }
        $this->ensureSpace(50);
        $this->drawLabel(Lang::get('pdf.with_best_wishes'));

        // A joint family invitation can list a lot of names. They are wrapped
        // and flowed line by line so the list continues onto the next page
        // rather than being cut off - the cap is only there to stop a
        // pathological paste producing fifty pages.
        $joined = implode('  •  ', array_slice($names, 0, 200));
        $this->setFontFor($joined, self::FONT_SANS, 10);
        $colour = $this->context->theme('text', '#4A3728');
        $width = $this->contentWidth - 60;

        foreach ($this->pdf->wrapText($joined, $width) as $line) {
            if (trim($line) === '') {
                continue;
            }
            $this->ensureSpace(16);
            $this->setFontFor($line, self::FONT_SANS, 10);
            $this->pdf->text($this->margin + 30 + ($width / 2), $this->cursor, $line, $colour, 'center');
            $this->cursor += 14;
        }
        $this->cursor += 10;
    }

    private function drawContact(): void
    {
        $name = trim((string) ($this->context->raw('rsvp_name') ?? ''));
        $phone = trim((string) ($this->context->raw('rsvp_phone') ?? $this->context->raw('whatsapp_number') ?? ''));
        if ($name === '' && $phone === '') {
            return;
        }
        $this->ensureSpace(26);
        $line = Lang::get('pdf.rsvp') . ': ' . trim($name . ($phone !== '' ? '  ·  ' . Str::phone($phone) : ''));
        $this->setFontFor($line, self::FONT_SANS, 10);
        $this->pdf->text(
            $this->pdf->width() / 2,
            $this->cursor,
            $line,
            $this->context->theme('muted', '#7A6A55'),
            'center'
        );
        $this->cursor += 20;
    }

    /** QR block and the short link, pinned near the bottom of the card. */
    private function drawFooter(): void
    {
        $qrSize = 92.0;
        $pin = $this->pdf->height() - 52 - $qrSize - 30;
        $top = max($this->cursor + 6, $pin);

        // The block is tall: divider, QR, caption, link. Where the card's own
        // content has pushed it past the paper edge it continues overleaf
        // instead - measured from where it would actually be drawn, not from a
        // reserve subtracted out of every page.
        if ($top + $qrSize + 34 > $this->pdf->height() - 18) {
            $this->pdf->addPage();
            $this->drawBackground();
            $this->drawBorder();
            $this->cursor = $this->margin + 40;
            $this->continuation = true;
            $top = max($this->cursor + 6, $pin);
        }

        $this->divider($top - 14, 0.3);

        $qr = (new QrService())->printForInvitation($this->context->invitation(), 8);
        $this->pdf->imageFromString($qr, ($this->pdf->width() - $qrSize) / 2, $top, $qrSize, $qrSize);

        $caption = Lang::get('pdf.scan_caption');
        $this->setFontFor($caption, self::FONT_SANS, 8.5);
        $this->pdf->text(
            $this->pdf->width() / 2,
            $top + $qrSize + 16,
            $caption,
            $this->context->theme('muted', '#7A6A55'),
            'center'
        );

        $link = $this->context->shortUrl();
        $this->setFontFor($link, self::FONT_SANS, 9.5);
        $this->pdf->text(
            $this->pdf->width() / 2,
            $top + $qrSize + 30,
            $link,
            $this->context->theme('primary', '#C8102E'),
            'center'
        );

        if ($this->context->showWatermark()) {
            $mark = Lang::get('pdf.watermark', ['app' => (string) config('app.name')]);
            $this->setFontFor($mark, self::FONT_SANS, 7.5);
            $this->pdf->text(
                $this->pdf->width() / 2,
                $this->pdf->height() - 32,
                $mark,
                $this->shade($this->context->theme('muted', '#7A6A55'), 0.3),
                'center'
            );
        }
    }

    /** A second page with the photo gallery, two columns. */
    private function drawGalleryPage(): void
    {
        $photos = array_slice($this->context->photos(), 0, 8);
        if ($photos === []) {
            return;
        }

        $this->pdf->addPage();
        $this->drawBackground();
        $this->drawBorder();

        $title = Lang::get('pdf.our_moments');
        $this->setFontFor($title, self::FONT_SCRIPT, 26);
        $this->pdf->text(
            $this->pdf->width() / 2,
            $this->margin + 30,
            $title,
            $this->context->theme('primary', '#C8102E'),
            'center'
        );

        $gap = 16.0;
        $columns = 2;
        $cellWidth = ($this->contentWidth - ($gap * ($columns - 1))) / $columns;
        $cellHeight = $cellWidth * 0.78;
        $top = $this->margin + 60;
        $media = new MediaService();

        foreach ($photos as $index => $photo) {
            $column = $index % $columns;
            $row = intdiv($index, $columns);
            $x = $this->margin + ($column * ($cellWidth + $gap));
            $y = $top + ($row * ($cellHeight + $gap + 10));

            if ($y + $cellHeight > $this->pdf->height() - 60) {
                break;
            }

            $this->pdf->roundedRect(
                $x,
                $y,
                $cellWidth,
                $cellHeight,
                8,
                '#FFFFFF',
                $this->context->theme('secondary', '#F0B429'),
                0.6
            );

            $absolute = $media->absolutePath((string) $photo['path']);
            if ($absolute !== null) {
                $this->pdf->save();
                $this->pdf->clipRoundedRect($x + 3, $y + 3, $cellWidth - 6, $cellHeight - 6, 6);
                $this->pdf->image($absolute, $x + 3, $y + 3, $cellWidth - 6, $cellHeight - 6, 'cover');
                $this->pdf->restore();
            }

            $caption = trim((string) ($photo['caption'] ?? ''));
            if ($caption !== '') {
                $this->setFontFor($caption, self::FONT_SANS, 8.5);
                $this->pdf->text(
                    $x + ($cellWidth / 2),
                    $y + $cellHeight + 13,
                    mb_substr($caption, 0, 60),
                    $this->context->theme('muted', '#7A6A55'),
                    'center'
                );
            }
        }
    }

    // ------------------------------------------------------------------
    //  Helpers
    // ------------------------------------------------------------------

    /**
     * Choose a font that can actually draw this string.
     *
     * A decorative Latin script face has no Indic glyphs at all, so falling
     * back by script is what keeps a bilingual card from printing boxes.
     */
    private function setFontFor(string $text, string $preferred, float $size): void
    {
        $candidates = match (Str::detectScript($text)) {
            'gujarati'   => [self::FONT_GU, self::FONT_SANS, self::FONT_SERIF],
            'devanagari' => [self::FONT_HI, self::FONT_SANS, self::FONT_SERIF],
            default      => [$preferred, self::FONT_SERIF, self::FONT_SANS],
        };

        foreach ($candidates as $alias) {
            if ($this->pdf->hasFont($alias) && $this->pdf->fontCovers($alias, $text)) {
                $this->pdf->setFont($alias, $size);
                return;
            }
        }
        foreach ($candidates as $alias) {
            if ($this->pdf->hasFont($alias)) {
                $this->pdf->setFont($alias, $size);
                return;
            }
        }
        foreach ($this->pdf->registeredFonts() as $alias) {
            $this->pdf->setFont($alias, $size);
            return;
        }
    }

    /** Lighten (positive amount) or darken (negative) a hex colour. */
    private function shade(string $hex, float $amount): string
    {
        [$r, $g, $b] = PdfDocument::hexToRgbFloat($hex);
        $mix = static function (float $channel) use ($amount): int {
            $value = $amount >= 0
                ? $channel + ((1 - $channel) * $amount)
                : $channel * (1 + $amount);
            return max(0, min(255, (int) round($value * 255)));
        };
        return sprintf('#%02X%02X%02X', $mix($r), $mix($g), $mix($b));
    }
}
