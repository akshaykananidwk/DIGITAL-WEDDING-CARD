<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use App\Core\Logger;
use App\Core\Pdf\PdfDocument;
use App\Repositories\FontRepository;
use App\Services\Pdf\InvitationPdfLayout;

/**
 * Invitation PDF export.
 *
 * The built-in engine is the default and needs nothing installed. If an
 * administrator has added mPDF or Dompdf through Composer, the `pdf.engine`
 * setting can switch to it for HTML-fidelity output; the built-in engine
 * remains the fallback so the feature never simply stops working.
 */
final class PdfService
{
    public const VARIANT_A4     = 'a4';
    public const VARIANT_MOBILE = 'mobile';
    public const VARIANT_CARD   = 'card';

    public function __construct(
        private readonly TemplateEngine $engine = new TemplateEngine(),
        private readonly FontRepository $fonts = new FontRepository()
    ) {
    }

    /**
     * Render an invitation to PDF bytes.
     *
     * @return array{bytes:string,filename:string,engine:string,pages:int}
     */
    public function forInvitation(array $invitation, string $variant = self::VARIANT_A4): array
    {
        $variant = in_array($variant, [self::VARIANT_A4, self::VARIANT_MOBILE, self::VARIANT_CARD], true)
            ? $variant
            : self::VARIANT_A4;

        $engine = $this->resolveEngine();

        if ($engine !== 'builtin') {
            try {
                return $this->renderWithHtmlEngine($invitation, $variant, $engine);
            } catch (\Throwable $e) {
                Logger::warning('HTML PDF engine failed, using the built-in engine: ' . $e->getMessage());
            }
        }

        return $this->renderWithBuiltinEngine($invitation, $variant);
    }

    /** Which engine will actually be used. Exposed for System → Health. */
    public function resolveEngine(): string
    {
        $configured = (string) Config::get('pdf.engine', 'auto');

        $available = $this->availableEngines();
        if ($configured !== 'auto' && in_array($configured, $available, true)) {
            return $configured;
        }
        // 'auto' deliberately prefers the built-in engine: it is always
        // present, needs no vendor directory and survives an update.
        return 'builtin';
    }

    /** @return array<int,string> */
    public function availableEngines(): array
    {
        $engines = ['builtin'];
        if (class_exists(\Mpdf\Mpdf::class)) {
            $engines[] = 'mpdf';
        }
        if (class_exists(\Dompdf\Dompdf::class)) {
            $engines[] = 'dompdf';
        }
        return $engines;
    }

    // ------------------------------------------------------------------
    //  Built-in engine
    // ------------------------------------------------------------------

    /** @return array{bytes:string,filename:string,engine:string,pages:int} */
    private function renderWithBuiltinEngine(array $invitation, string $variant): array
    {
        $size = match ($variant) {
            self::VARIANT_MOBILE => 'MOBILE',
            self::VARIANT_CARD   => 'KANKOTRI',
            default              => (string) Config::get('pdf.paper', 'A4'),
        };

        $document = new PdfDocument($size, 'portrait');
        $document->setInfo('Title', (string) $invitation['title']);
        $document->setInfo('Author', (string) (setting('site_name') ?: Config::get('app.name')));
        $document->setInfo('Subject', 'Digital invitation');
        $document->setInfo('Keywords', 'invitation, kankotri, ' . ($invitation['event_type'] ?? 'celebration'));

        $context = $this->engine->context($invitation);
        $layout = new InvitationPdfLayout($document, $context, $this->fontFiles());
        $layout->render();

        return [
            'bytes'    => $document->output(),
            'filename' => $this->filename($invitation, $variant),
            'engine'   => 'builtin',
            'pages'    => $document->pageCount(),
        ];
    }

    /**
     * Font aliases the layout expects, mapped to real files.
     *
     * Administrator-uploaded fonts registered in the Fonts manager win over
     * the bundled ones, so a site can use its own typeface in print.
     *
     * @return array<string,string>
     */
    public function fontFiles(): array
    {
        $bundled = [
            'script'     => ASSET_PATH . '/fonts/GreatVibes-Regular.ttf',
            'serif'      => ASSET_PATH . '/fonts/PlayfairDisplay-Regular.ttf',
            'sans'       => ASSET_PATH . '/fonts/NotoSans-Regular.ttf',
            'gujarati'   => ASSET_PATH . '/fonts/NotoSansGujarati-Regular.ttf',
            'devanagari' => ASSET_PATH . '/fonts/NotoSansDevanagari-Regular.ttf',
        ];

        try {
            foreach (['gujarati', 'devanagari', 'latin'] as $script) {
                $font = $this->fonts->forScript($script);
                if ($font === null) {
                    continue;
                }
                $path = (string) ($font['file_path'] ?? '');
                if ($path === '') {
                    continue;
                }
                $absolute = $this->resolveFontPath($path);
                if ($absolute === null) {
                    continue;
                }
                $alias = match ($script) {
                    'gujarati'   => 'gujarati',
                    'devanagari' => 'devanagari',
                    default      => 'sans',
                };
                $bundled[$alias] = $absolute;
            }
        } catch (\Throwable $e) {
            Logger::warning('Could not resolve PDF fonts from the database: ' . $e->getMessage());
        }

        // Drop anything that is not actually on disk so registration is quiet.
        return array_filter($bundled, static fn (string $path): bool => is_file($path));
    }

    /** Resolve a stored font path to an absolute file inside the project. */
    private function resolveFontPath(string $stored): ?string
    {
        $stored = ltrim(str_replace('\\', '/', $stored), '/');
        $candidates = [
            ROOT_PATH . '/' . $stored,
            UPLOAD_PATH . '/' . $stored,
            ASSET_PATH . '/' . $stored,
        ];
        foreach ($candidates as $candidate) {
            $real = realpath($candidate);
            if ($real !== false && is_file($real) && str_starts_with($real, ROOT_PATH . DIRECTORY_SEPARATOR)) {
                return $real;
            }
        }
        return null;
    }

    // ------------------------------------------------------------------
    //  Optional HTML engines
    // ------------------------------------------------------------------

    /** @return array{bytes:string,filename:string,engine:string,pages:int} */
    private function renderWithHtmlEngine(array $invitation, string $variant, string $engine): array
    {
        $html = $this->printableHtml($invitation);
        $paper = match ($variant) {
            self::VARIANT_MOBILE => [396.0, 704.0],
            self::VARIANT_CARD   => [510.24, 737.01],
            default              => 'A4',
        };

        if ($engine === 'mpdf' && class_exists(\Mpdf\Mpdf::class)) {
            $mpdf = new \Mpdf\Mpdf([
                'mode'          => 'utf-8',
                'format'        => is_array($paper) ? $paper : $paper,
                'margin_left'   => 8,
                'margin_right'  => 8,
                'margin_top'    => 8,
                'margin_bottom' => 8,
                'tempDir'       => STORAGE_PATH . '/tmp',
            ]);
            $mpdf->SetTitle((string) $invitation['title']);
            $mpdf->WriteHTML($html);
            return [
                'bytes'    => (string) $mpdf->Output('', 'S'),
                'filename' => $this->filename($invitation, $variant),
                'engine'   => 'mpdf',
                'pages'    => 1,
            ];
        }

        if ($engine === 'dompdf' && class_exists(\Dompdf\Dompdf::class)) {
            $dompdf = new \Dompdf\Dompdf([
                'isRemoteEnabled'      => false,
                'isHtml5ParserEnabled' => true,
                'tempDir'              => STORAGE_PATH . '/tmp',
                'defaultFont'          => 'DejaVu Sans',
            ]);
            $dompdf->loadHtml($html, 'UTF-8');
            $dompdf->setPaper(is_array($paper) ? $paper : 'A4', 'portrait');
            $dompdf->render();
            return [
                'bytes'    => (string) $dompdf->output(),
                'filename' => $this->filename($invitation, $variant),
                'engine'   => 'dompdf',
                'pages'    => 1,
            ];
        }

        throw new \RuntimeException('The configured PDF engine is not installed.');
    }

    /** Print-oriented HTML for the optional HTML engines. */
    public function printableHtml(array $invitation): string
    {
        $context = $this->engine->context($invitation);
        return \App\Core\View::make('invite.print', [
            'c'      => $context,
            'engine' => $this->engine,
        ])->render();
    }

    public function filename(array $invitation, string $variant = self::VARIANT_A4): string
    {
        $slug = preg_replace('/[^a-z0-9\-]/i', '', (string) $invitation['slug']) ?: 'invitation';
        $suffix = $variant === self::VARIANT_A4 ? '' : '-' . $variant;
        return $slug . $suffix . '.pdf';
    }

    /** Health check: can the engine produce a valid, non-trivial PDF? */
    public function selfTest(): array
    {
        try {
            $document = new PdfDocument('A4');
            foreach ($this->fontFiles() as $alias => $path) {
                $document->registerFont($alias, $path);
            }
            $document->addPage();
            $registered = $document->registeredFonts();
            if ($registered === []) {
                return ['ok' => false, 'message' => 'No usable PDF font is installed.'];
            }
            $document->setFont($registered[0], 12);
            $document->text(40, 60, 'PDF engine self test');
            $bytes = $document->output();

            $ok = str_starts_with($bytes, '%PDF-') && str_contains($bytes, '%%EOF') && strlen($bytes) > 800;
            return [
                'ok'      => $ok,
                'message' => $ok
                    ? 'Built-in engine produced a ' . \App\Core\Str::bytesToHuman(strlen($bytes)) . ' PDF.'
                    : 'The generated PDF looks malformed.',
                'engine'  => $this->resolveEngine(),
                'fonts'   => count($registered),
            ];
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => 'PDF engine error: ' . $e->getMessage()];
        }
    }
}
