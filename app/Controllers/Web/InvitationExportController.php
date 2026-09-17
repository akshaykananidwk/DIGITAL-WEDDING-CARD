<?php

declare(strict_types=1);

namespace App\Controllers\Web;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\HttpException;
use App\Core\Logger;
use App\Core\Request;
use App\Core\Response;
use App\Repositories\InvitationRepository;
use App\Services\AnalyticsService;
use App\Services\CalendarService;
use App\Services\FeatureFlagService;
use App\Services\PdfService;
use App\Services\QrService;
use App\Services\TemplateEngine;

/** PDF, QR and calendar downloads for a public invitation. */
final class InvitationExportController extends Controller
{
    public function __construct(
        private readonly InvitationRepository $invitations = new InvitationRepository(),
        private readonly AnalyticsService $analytics = new AnalyticsService()
    ) {
    }

    public function pdf(Request $request): Response
    {
        if (FeatureFlagService::instance()->disabled('pdf_export', true)) {
            throw HttpException::forbidden('PDF export is switched off.');
        }

        $invitation = $this->resolve($request);
        $variant = in_array($request->query('variant'), ['mobile', 'card'], true)
            ? (string) $request->query('variant')
            : 'a4';

        try {
            $result = (new PdfService())->forInvitation($invitation, $variant);
        } catch (\Throwable $e) {
            Logger::error('PDF generation failed: ' . $e->getMessage(), [
                'invitation_id' => $invitation['id'],
            ]);
            throw new HttpException(500, 'The PDF could not be generated. Please try again.');
        }

        $this->analytics->recordDownload($invitation, $variant === 'mobile' ? 'pdf_mobile' : 'pdf');

        return Response::download(
            $result['bytes'],
            $result['filename'],
            'application/pdf',
            $request->query('inline') === '1'
        )->cache(0);
    }

    public function qrPng(Request $request): Response
    {
        $invitation = $this->resolve($request);
        $scale = max(4, min(20, $request->int('scale', 10)));
        $print = $request->query('print') === '1';

        $service = new QrService();
        $bytes = $print
            ? $service->printForInvitation($invitation, $scale)
            : $service->forInvitation($invitation, true, $scale)['bytes'];

        if ($request->query('download') === '1') {
            $this->analytics->recordDownload($invitation, 'qr_png');
            return Response::download($bytes, $invitation['slug'] . '-qr.png', 'image/png');
        }

        return Response::make($bytes, 200, ['Content-Type' => 'image/png'])->cache(86400);
    }

    /**
     * A QR that opens the venue on a map, for a printed card or a signboard.
     *
     * Separate from the invitation QR on purpose: a guest standing outside
     * wants directions, not the invitation they have already read.
     */
    public function qrVenue(Request $request): Response
    {
        $invitation = $this->resolve($request);
        $context = (new TemplateEngine())->context($invitation);
        $mapsUrl = $context->mapsUrl();

        if ($mapsUrl === '') {
            throw HttpException::notFound('This invitation has no venue link yet.');
        }

        $bytes = (new QrService())->forVenue($mapsUrl, max(4, min(20, $request->int('scale', 10))));

        if ($request->query('download') === '1') {
            $this->analytics->recordDownload($invitation, 'qr_png');
            return Response::download($bytes, $invitation['slug'] . '-venue-qr.png', 'image/png');
        }
        return Response::make($bytes, 200, ['Content-Type' => 'image/png'])->cache(86400);
    }

    /** A QR that opens the RSVP form, for the reception desk. */
    public function qrRsvp(Request $request): Response
    {
        $invitation = $this->resolve($request);
        $bytes = (new QrService())->forRsvp($invitation, max(4, min(20, $request->int('scale', 10))));

        if ($request->query('download') === '1') {
            $this->analytics->recordDownload($invitation, 'qr_png');
            return Response::download($bytes, $invitation['slug'] . '-rsvp-qr.png', 'image/png');
        }
        return Response::make($bytes, 200, ['Content-Type' => 'image/png'])->cache(86400);
    }

    public function qrSvg(Request $request): Response
    {
        $invitation = $this->resolve($request);
        $svg = (new QrService())->svgForInvitation($invitation, max(4, min(24, $request->int('scale', 10))));

        if ($request->query('download') === '1') {
            $this->analytics->recordDownload($invitation, 'qr_svg');
            return Response::download($svg, $invitation['slug'] . '-qr.svg', 'image/svg+xml');
        }

        return Response::make($svg, 200, ['Content-Type' => 'image/svg+xml'])->cache(86400);
    }

    public function ics(Request $request): Response
    {
        $invitation = $this->resolve($request);
        $service = new CalendarService();

        $this->analytics->recordDownload($invitation, 'ics');

        return Response::download(
            $service->forInvitation($invitation),
            $service->filename($invitation),
            'text/calendar; charset=utf-8'
        );
    }

    /** @return array<string,mixed> */
    private function resolve(Request $request): array
    {
        $invitation = $this->invitations->findForRender((string) $request->param('slug'));
        if ($invitation === null) {
            throw HttpException::notFound('That invitation could not be found.');
        }

        $isPublic = (string) $invitation['status'] === 'published';
        $userId = Auth::id();
        $isOwner = $userId !== null
            && ((int) $invitation['user_id'] === $userId || Auth::can('invitations.manage_all'));

        if (!$isPublic && !$isOwner) {
            throw HttpException::notFound('That invitation is not available.');
        }

        return $invitation;
    }
}
