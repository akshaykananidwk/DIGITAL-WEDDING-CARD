<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\Url;
use App\Repositories\InvitationDataRepository;
use App\Repositories\InvitationRepository;
use App\Repositories\RsvpRepository;
use App\Services\AnalyticsService;
use App\Services\AuditService;
use App\Services\InvitationService;

final class InvitationController extends AdminController
{
    public function __construct(
        private readonly InvitationRepository $invitations = new InvitationRepository()
    ) {
    }

    public function index(Request $request): Response
    {
        $filters = [
            'status' => in_array($request->query('status'), ['draft', 'published', 'unpublished', 'archived'], true)
                ? (string) $request->query('status')
                : '',
            'q'      => mb_substr((string) $request->query('q', ''), 0, 80),
        ];

        $result = $this->invitations->paginateAll($filters, max(1, $request->int('page', 1)), 25);

        return $this->admin('admin.invitations.index', 'Invitations', [
            'invitations' => $result['rows'],
            'pagination'  => $this->paginationMeta($result, Url::to('admin/invitations'), array_filter($filters)),
            'filters'     => $filters,
            'stats'       => $this->invitations->stats(),
        ]);
    }

    public function show(Request $request): Response
    {
        $invitation = $this->invitations->find($request->int('id'));
        if ($invitation === null) {
            throw HttpException::notFound('That invitation does not exist.');
        }

        return $this->admin('admin.invitations.show', (string) $invitation['title'], [
            'invitation' => $invitation,
            'content'    => (new InvitationDataRepository())->forInvitation((int) $invitation['id']),
            'rsvp'       => (new RsvpRepository())->summary((int) $invitation['id']),
            'report'     => (new AnalyticsService())->report($invitation, '30d'),
            'owner'      => (new \App\Repositories\UserRepository())->find((int) $invitation['user_id']),
            'template'   => (new \App\Repositories\TemplateRepository())->find((int) $invitation['template_id']),
        ]);
    }

    public function setStatus(Request $request): Response
    {
        $id = $request->int('id');
        $invitation = $this->invitations->find($id);
        if ($invitation === null) {
            throw HttpException::notFound();
        }

        $status = (string) $request->input('status');
        if (!in_array($status, ['draft', 'published', 'unpublished', 'archived'], true)) {
            return $this->respond($request, false, 'Unknown status.', 'admin/invitations');
        }

        $this->invitations->update($id, ['status' => $status]);
        AuditService::instance()->log('admin.invitation.status', 'invitation', $id, 'Set to ' . $status);

        return $this->respond($request, true, 'Status updated.', 'admin/invitations/' . $id);
    }

    public function destroy(Request $request): Response
    {
        $id = $request->int('id');
        $invitation = $this->invitations->find($id);
        if ($invitation === null) {
            throw HttpException::notFound();
        }

        // A hard delete also removes the uploaded files.
        if ($request->bool('purge')) {
            (new InvitationService())->purge($invitation);
            $message = 'Invitation and its files were permanently deleted.';
        } else {
            $this->invitations->delete($id);
            $message = 'Invitation deleted.';
        }

        AuditService::instance()->log('admin.invitation.delete', 'invitation', $id, (string) $invitation['title']);

        return $this->respond($request, true, $message, 'admin/invitations');
    }
}
