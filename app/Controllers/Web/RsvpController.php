<?php

declare(strict_types=1);

namespace App\Controllers\Web;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\HttpException;
use App\Core\Lang;
use App\Core\Request;
use App\Core\Response;
use App\Core\Url;
use App\Repositories\InvitationRepository;
use App\Repositories\RsvpRepository;
use App\Services\FeatureFlagService;
use App\Services\InvitationService;
use App\Services\RsvpService;
use App\Services\SeoService;

final class RsvpController extends Controller
{
    private const PER_PAGE = 25;

    public function __construct(
        private readonly RsvpService $rsvp = new RsvpService(),
        private readonly RsvpRepository $repository = new RsvpRepository(),
        private readonly InvitationRepository $invitations = new InvitationRepository(),
        private readonly InvitationService $service = new InvitationService()
    ) {
    }

    /** Guest submission from the public invitation page. */
    public function store(Request $request): Response
    {
        if (FeatureFlagService::instance()->disabled('rsvp', true)) {
            return $this->error('RSVP is switched off.', 403);
        }

        $invitation = $this->invitations->findForRender((string) $request->param('slug'));
        if ($invitation === null) {
            throw HttpException::notFound();
        }
        if ((string) $invitation['status'] !== 'published') {
            return $request->expectsJson()
                ? $this->error('This invitation is not accepting responses yet.', 422)
                : $this->back(['response' => ['This invitation is not accepting responses yet.']]);
        }

        $result = $this->rsvp->submit($invitation, $request->all());

        if ($request->expectsJson()) {
            return $result['ok']
                ? $this->success(['updated' => $result['updated']], $result['message'])
                : $this->error($result['message'], 422, $result['errors']);
        }

        if (!$result['ok']) {
            return $this->back($result['errors'], $request->all());
        }
        $this->flash('success', $result['message']);
        return Response::redirect(Url::invite((string) $invitation['slug']) . '#rsvp');
    }

    /** Owner-facing RSVP list. */
    public function manage(Request $request): Response
    {
        $invitation = $this->service->findOwnedOrFail($request->int('id'));

        $filters = [
            'response' => in_array($request->query('response'), ['yes', 'maybe', 'no'], true)
                ? (string) $request->query('response')
                : '',
            'q'        => mb_substr((string) $request->query('q', ''), 0, 80),
        ];

        $result = $this->repository->paginateForInvitation(
            (int) $invitation['id'],
            $filters,
            max(1, $request->int('page', 1)),
            self::PER_PAGE
        );

        return $this->view('dashboard.rsvp', [
            'seo'        => SeoService::make()->title(Lang::get('rsvp.title'))->noindex(),
            'invitation' => $invitation,
            'responses'  => $result['rows'],
            'pagination' => $this->paginationMeta(
                $result,
                Url::to('invitations/' . $invitation['id'] . '/rsvp'),
                array_filter($filters)
            ),
            'summary'    => $this->repository->summary((int) $invitation['id']),
            'filters'    => $filters,
        ]);
    }

    public function markRead(Request $request): Response
    {
        $invitation = $this->service->findOwnedOrFail($request->int('id'));
        $this->repository->markAllRead((int) $invitation['id']);

        return $request->expectsJson()
            ? $this->success(null, 'Marked as read.')
            : $this->redirect('invitations/' . $invitation['id'] . '/rsvp');
    }

    public function destroy(Request $request): Response
    {
        $invitation = $this->service->findOwnedOrFail($request->int('id'));
        $deleted = $this->rsvp->delete($request->int('rsvpId'), (int) $invitation['id']);

        if ($request->expectsJson()) {
            return $deleted
                ? $this->success(null, 'Response deleted.')
                : $this->error('That response no longer exists.', 404);
        }
        $this->flash($deleted ? 'success' : 'warning', $deleted ? 'Response deleted.' : 'That response no longer exists.');
        return $this->redirect('invitations/' . $invitation['id'] . '/rsvp');
    }

    public function export(Request $request): Response
    {
        $invitation = $this->service->findOwnedOrFail($request->int('id'));
        $csv = $this->rsvp->toCsv((int) $invitation['id']);

        return Response::download(
            $csv,
            $invitation['slug'] . '-rsvp.csv',
            'text/csv; charset=utf-8'
        );
    }
}
