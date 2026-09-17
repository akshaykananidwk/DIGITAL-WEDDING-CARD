<?php

declare(strict_types=1);

namespace App\Controllers\Web;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\Url;
use App\Repositories\InvitationRepository;
use App\Repositories\RsvpRepository;
use App\Services\AnalyticsService;
use App\Services\SeoService;
use App\Services\ShareService;
use App\Services\TemplateEngine;

/**
 * The public invitation page - the thing guests actually open.
 *
 * Draft and unpublished invitations are visible only to their owner, which is
 * what lets the builder preview a draft without exposing it.
 */
final class PublicInvitationController extends Controller
{
    private const UNLOCK_SESSION_PREFIX = '_invite_unlocked_';

    public function __construct(
        private readonly InvitationRepository $invitations = new InvitationRepository(),
        private readonly TemplateEngine $engine = new TemplateEngine(),
        private readonly AnalyticsService $analytics = new AnalyticsService()
    ) {
    }

    /** /i/CODE - the short link used in WhatsApp messages and QR codes. */
    public function short(Request $request): Response
    {
        $code = strtoupper((string) $request->param('code'));
        $invitation = $this->invitations->findByShortCode($code);
        if ($invitation === null) {
            throw HttpException::notFound('That invitation link is not valid.');
        }
        // A QR scan arrives here, so count it before redirecting.
        if ($this->isPublic($invitation)) {
            $this->analytics->recordDownload($invitation, 'qr_png');
        }
        return Response::redirect(Url::invite((string) $invitation['slug']), 301);
    }

    public function show(Request $request): Response
    {
        $invitation = $this->invitations->findForRender((string) $request->param('slug'));
        if ($invitation === null) {
            throw HttpException::notFound('That invitation could not be found.');
        }

        if (!$this->isPublic($invitation) && !$this->isOwner($invitation)) {
            throw HttpException::notFound('That invitation is not available.');
        }

        // Optional passphrase.
        if ($this->requiresUnlock($invitation)) {
            return $this->view('public.locked', [
                'seo'        => SeoService::make()->title((string) $invitation['title'])->noindex(),
                'invitation' => $invitation,
            ]);
        }

        if ($this->isPublic($invitation)) {
            $this->analytics->recordView($invitation);
        }

        $context = $this->engine->context($invitation);

        return $this->view('invite.shell', [
            'c'          => $context,
            'body'       => $this->engine->render($invitation),
            'styles'     => $this->engine->styles($context),
            'scripts'    => $this->engine->scripts($context),
            'seo'        => SeoService::forInvitation($invitation, $context),
            'isPreview'  => false,
            'share'      => (new ShareService())->allLinks($invitation),
            'rsvpSummary' => (new RsvpRepository())->summary((int) $invitation['id']),
            'isOwnerView' => $this->isOwner($invitation) && !$this->isPublic($invitation),
        ]);
    }

    public function unlock(Request $request): Response
    {
        $invitation = $this->invitations->findForRender((string) $request->param('slug'));
        if ($invitation === null) {
            throw HttpException::notFound();
        }

        $hash = (string) ($invitation['access_password'] ?? '');
        $candidate = (string) $request->raw('passphrase', '');

        if ($hash !== '' && $candidate !== '' && password_verify($candidate, $hash)) {
            Session::set(self::UNLOCK_SESSION_PREFIX . $invitation['id'], true);
            return $this->redirect('invite/' . $invitation['slug']);
        }

        $this->flash('danger', 'That passphrase is not correct.');
        return $this->redirect('invite/' . $invitation['slug']);
    }

    /** Called by the share buttons so shares can be counted. */
    public function recordShare(Request $request): Response
    {
        $invitation = $this->invitations->findForRender((string) $request->param('slug'));
        if ($invitation === null) {
            return $this->error('Not found.', 404);
        }
        if (!$this->isPublic($invitation)) {
            return $this->success(null, 'Ignored.');
        }

        $channel = (string) $request->input('channel', 'other');
        $this->analytics->recordShare($invitation, $channel);

        return $this->success(null, 'Recorded.');
    }

    private function isPublic(array $invitation): bool
    {
        return (string) $invitation['status'] === 'published';
    }

    private function isOwner(array $invitation): bool
    {
        $userId = Auth::id();
        if ($userId === null) {
            return false;
        }
        return (int) $invitation['user_id'] === $userId || Auth::can('invitations.manage_all');
    }

    private function requiresUnlock(array $invitation): bool
    {
        $hash = (string) ($invitation['access_password'] ?? '');
        if ($hash === '') {
            return false;
        }
        if ($this->isOwner($invitation)) {
            return false;
        }
        return Session::get(self::UNLOCK_SESSION_PREFIX . $invitation['id']) !== true;
    }
}
