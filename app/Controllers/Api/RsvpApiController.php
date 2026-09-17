<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Repositories\InvitationRepository;
use App\Repositories\RsvpRepository;
use App\Services\FeatureFlagService;
use App\Services\InvitationService;
use App\Services\RsvpService;

final class RsvpApiController extends Controller
{
    public function store(Request $request): Response
    {
        if (FeatureFlagService::instance()->disabled('rsvp', true)) {
            return $this->error('RSVP is switched off.', 403);
        }

        $invitation = (new InvitationRepository())->findForRender((string) $request->param('slug'));
        if ($invitation === null) {
            return $this->error('Invitation not found.', 404);
        }
        if ((string) $invitation['status'] !== 'published') {
            return $this->error('This invitation is not accepting responses.', 422);
        }

        $result = (new RsvpService())->submit($invitation, $request->all());

        return $result['ok']
            ? $this->success(['updated' => $result['updated']], $result['message'])
            : $this->error($result['message'], 422, $result['errors']);
    }

    public function index(Request $request): Response
    {
        $invitation = (new InvitationService())->findOwnedOrFail($request->int('id'));
        $repository = new RsvpRepository();

        $result = $repository->paginateForInvitation(
            (int) $invitation['id'],
            ['response' => (string) $request->query('response', '')],
            max(1, $request->int('page', 1)),
            max(1, min(100, $request->int('per_page', 25)))
        );

        return $this->success(
            array_map(static fn (array $row): array => [
                'id'       => (int) $row['id'],
                'name'     => (string) $row['name'],
                'phone'    => (string) ($row['phone'] ?? ''),
                'email'    => (string) ($row['email'] ?? ''),
                'response' => (string) $row['response'],
                'guests'   => (int) $row['guests'],
                'message'  => (string) ($row['message'] ?? ''),
                'received' => (string) $row['created_at'],
            ], $result['rows']),
            '',
            [
                'summary'  => $repository->summary((int) $invitation['id']),
                'page'     => $result['page'],
                'pages'    => $result['pages'],
                'total'    => $result['total'],
            ]
        );
    }
}
