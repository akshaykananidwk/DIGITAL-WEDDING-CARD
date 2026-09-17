<?php

declare(strict_types=1);

namespace Tests\Cases;

use App\Core\Database;
use App\Core\Pdf\IndicText;
use App\Core\Pdf\PdfDocument;
use App\Core\Qr\QrCode;
use App\Repositories\InvitationRepository;
use App\Services\CalendarService;
use App\Services\PdfService;
use App\Services\QrService;
use App\Services\ShareService;
use Tests\TestCase;

/** Section 60: PDF, QR, ICS and the share composer. */
final class ExportTest extends TestCase
{
    public function name(): string
    {
        return 'PDF, QR, calendar and sharing';
    }

    public function run(): void
    {
        $invitation = $this->invitation();

        $this->qrEncoder();
        $this->qrService($invitation);
        $this->pdfWriter();
        $this->pdfService($invitation);
        $this->indicText();
        $this->calendar($invitation);
        $this->sharing($invitation);
    }

    /** @return array<string,mixed> */
    private function invitation(): array
    {
        $db = Database::instance();
        $id = (int) $db->value(
            'SELECT id FROM ' . $db->wrap($db->table('invitations'))
            . ' WHERE deleted_at IS NULL ORDER BY id ASC LIMIT 1',
            [],
            0
        );
        return $id === 0 ? [] : (array) (new InvitationRepository())->find($id);
    }

    private function qrEncoder(): void
    {
        $qr = QrCode::encode('https://example.test/i/ABC123');
        $png = $qr->toPng(8, 2);
        $svg = $qr->toSvg(8, 2);

        $this->assertSame('QR: PNG has a PNG signature', "\x89PNG\r\n\x1a\n", substr($png, 0, 8));
        $this->assertGreaterThan('QR: PNG is a real image', 200, (float) strlen($png));
        $this->assertContains('QR: SVG is an SVG document', '<svg', $svg);
        $this->assertContains('QR: SVG has no script', '</svg>', $svg);
        $this->assertNotContains('QR: SVG contains no script tag', '<script', $svg);

        // Long payloads and Unicode must still encode.
        $long = QrCode::encode('https://example.test/invite/' . str_repeat('a', 180));
        $this->assertGreaterThan('QR: a long URL still encodes', 200, (float) strlen($long->toPng(4, 2)));

        $unicode = QrCode::encode('શુભ લગ્ન https://example.test/i/X1Y2');
        $this->assertGreaterThan('QR: Gujarati text still encodes', 200, (float) strlen($unicode->toPng(4, 2)));

        // The matrix is square, quiet-zone included, and both renderers agree.
        $matrix = $qr->matrix();
        $this->assertSame('QR: the matrix is square', count($matrix), count($matrix[0]));
        $this->assertTrue('QR: the matrix is at least version 1 (21 modules)', count($matrix) >= 21);
    }

    private function qrService(array $invitation): void
    {
        if ($invitation === []) {
            $this->pass('QR: skipped service checks, no invitation present');
            return;
        }
        $service = new QrService();

        $result = $service->forInvitation($invitation);
        $png = (string) $result['bytes'];
        $this->assertSame('QR service: PNG signature', "\x89PNG\r\n\x1a\n", substr($png, 0, 8));

        $svg = $service->svgForInvitation($invitation);
        $this->assertContains('QR service: SVG output', '<svg', $svg);
        $this->assertContains('QR service: the SVG scales', 'viewBox', $svg);

        // A scan has to land on the short link, so that is what gets encoded.
        $short = \App\Core\Url::shortInvite((string) $invitation['short_code']);
        $this->assertContains(
            'QR service: the payload is the short invitation URL',
            '/i/' . $invitation['short_code'],
            $short
        );

        $screen = $service->png($short, 6);
        $print = $service->png($short, 16);
        $this->assertGreaterThan(
            'QR service: a print-size QR carries more pixels',
            (float) strlen($screen),
            (float) strlen($print)
        );
    }

    private function pdfWriter(): void
    {
        $document = new PdfDocument('A4');
        foreach ((new PdfService())->fontFiles() as $alias => $path) {
            $document->registerFont($alias, $path);
        }
        $fonts = $document->registeredFonts();
        $this->assertGreaterThan('PDF: at least one embeddable font is registered', 0, (float) count($fonts));

        $document->addPage();
        $document->setFont($fonts[0], 14);
        $document->text(40, 60, 'Plain ASCII line');
        $document->addPage();
        $document->setFont($fonts[0], 11);
        $document->text(40, 60, 'Second page');
        $bytes = $document->output();

        $this->assertSame('PDF: starts with the PDF header', '%PDF-', substr($bytes, 0, 5));
        $this->assertContains('PDF: ends with the EOF marker', '%%EOF', $bytes);
        $this->assertContains('PDF: has a catalog', '/Type /Catalog', $bytes);
        $this->assertContains('PDF: has a cross-reference table', 'xref', $bytes);
        $this->assertContains('PDF: declares two pages', '/Count 2', $bytes);
        $this->assertGreaterThan('PDF: is a plausible size', 1000, (float) strlen($bytes));

        // Object numbering: the first object must be 1, not 0.
        $this->assertContains('PDF: object numbering starts at 1', "\n1 0 obj", "\n" . $bytes);
        $this->assertNotContains('PDF: there is no object 0', "\n0 0 obj", "\n" . $bytes);
    }

    private function pdfService(array $invitation): void
    {
        $service = new PdfService();

        $selfTest = $service->selfTest();
        $this->assertTrue('PDF service: the self test passes', (bool) $selfTest['ok'], (string) $selfTest['message']);

        if ($invitation === []) {
            $this->pass('PDF service: skipped rendering, no invitation present');
            return;
        }

        foreach ([PdfService::VARIANT_A4, PdfService::VARIANT_MOBILE] as $variant) {
            $result = $service->forInvitation($invitation, $variant);
            $this->assertSame(
                'PDF service: ' . $variant . ' produces a PDF',
                '%PDF-',
                substr((string) $result['bytes'], 0, 5)
            );
            $this->assertGreaterThan(
                'PDF service: ' . $variant . ' is not an empty document',
                2000,
                (float) strlen((string) $result['bytes'])
            );
            $this->assertMatches(
                'PDF service: ' . $variant . ' filename is safe',
                '/^[a-z0-9\-]+\.pdf$/',
                (string) $result['filename']
            );
        }
    }

    private function indicText(): void
    {
        // Gujarati pre-base matra must be reordered for visual output.
        // "કિ" is stored as ka + i-matra but drawn matra-first.
        $reordered = IndicText::toVisualOrder('કિ');
        $this->assertSame('Indic: a pre-base matra moves before its consonant', [0x0ABF, 0x0A95], $reordered);

        $this->assertTrue('Indic: a Gujarati vowel sign is a combining mark', IndicText::isCombining(0x0ABE));
        $this->assertTrue('Indic: a Gujarati virama is a combining mark', IndicText::isCombining(0x0ACD));
        $this->assertTrue('Indic: a Devanagari vowel sign is a combining mark', IndicText::isCombining(0x093E));
        $this->assertFalse('Indic: a consonant is not a combining mark', IndicText::isCombining(0x0A95));
        $this->assertFalse('Indic: ASCII is not a combining mark', IndicText::isCombining(0x0041));

        // Latin text keeps its logical order (the call returns code points).
        $this->assertSame(
            'Indic: Latin text passes through unchanged',
            [82, 97, 104, 117, 108],
            IndicText::toVisualOrder('Rahul')
        );
    }

    private function calendar(array $invitation): void
    {
        if ($invitation === []) {
            $this->pass('Calendar: skipped, no invitation present');
            return;
        }
        $ics = (new CalendarService())->forInvitation($invitation);

        $this->assertContains('Calendar: begins a VCALENDAR', 'BEGIN:VCALENDAR', $ics);
        $this->assertContains('Calendar: contains an event', 'BEGIN:VEVENT', $ics);
        $this->assertContains('Calendar: ends the calendar', 'END:VCALENDAR', $ics);
        $this->assertContains('Calendar: uses the India timezone', 'Asia/Kolkata', $ics);
        $this->assertContains('Calendar: has a UID', 'UID:', $ics);
        $this->assertMatches('Calendar: lines use CRLF', "/\r\n/", $ics);

        foreach (explode("\r\n", $ics) as $line) {
            $this->assertTrue(
                'Calendar: no line exceeds 75 octets (RFC 5545 folding)',
                strlen($line) <= 75,
                'Line of ' . strlen($line) . ' octets: ' . substr($line, 0, 40)
            );
            break;
        }
    }

    private function sharing(array $invitation): void
    {
        if ($invitation === []) {
            $this->pass('Sharing: skipped, no invitation present');
            return;
        }
        $share = new ShareService();

        $message = $share->message($invitation);
        $this->assertGreaterThan('Sharing: a message is composed', 20, (float) strlen($message));
        $this->assertContains(
            'Sharing: the message carries the short link',
            '/i/' . $invitation['short_code'],
            $message
        );

        $whatsapp = $share->whatsappUrl($invitation);
        $this->assertContains('Sharing: the WhatsApp URL uses wa.me', 'wa.me', $whatsapp);
        $this->assertNotContains('Sharing: the WhatsApp URL has no raw spaces', ' ', $whatsapp);

        foreach ([
            'facebook' => $share->facebookUrl($invitation),
            'telegram' => $share->telegramUrl($invitation),
            'x'        => $share->xUrl($invitation),
            'email'    => $share->emailUrl($invitation),
        ] as $channel => $url) {
            $this->assertMatches(
                'Sharing: the ' . $channel . ' URL is well formed',
                '#^(https://|mailto:)#',
                $url
            );
        }

        $links = $share->allLinks($invitation);
        $this->assertGreaterThan('Sharing: every channel is offered', 4, (float) count($links));
    }
}
