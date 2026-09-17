<?php

declare(strict_types=1);

namespace App\Controllers\Web;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Lang;
use App\Core\Request;
use App\Core\Response;
use App\Core\Url;
use App\Repositories\InvitationRepository;
use App\Services\SeoService;

final class InvitationController extends Controller
{
    private const PER_PAGE = 12;

    public function index(Request $request): Response
    {
        $userId = (int) Auth::id();
        $filters = [
            'status' => in_array($request->query('status'), ['draft', 'published', 'unpublished', 'archived'], true)
                ? (string) $request->query('status')
                : '',
            'q'      => mb_substr((string) $request->query('q', ''), 0, 80),
        ];

        $result = (new InvitationRepository())->paginateForUser(
            $userId,
            $filters,
            max(1, $request->int('page', 1)),
            self::PER_PAGE
        );

        return $this->view('dashboard.invitations', [
            'seo'         => SeoService::make()->title(Lang::get('nav.invitations'))->noindex(),
            'invitations' => $result['rows'],
            'pagination'  => $this->paginationMeta($result, Url::to('invitations'), array_filter($filters)),
            'filters'     => $filters,
            'total'       => $result['total'],
        ]);
    }
}
