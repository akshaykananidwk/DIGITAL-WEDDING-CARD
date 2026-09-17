<?php

declare(strict_types=1);

namespace App\Controllers\Web;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Lang;
use App\Core\Request;
use App\Core\Response;
use App\Repositories\InvitationRepository;
use App\Repositories\NotificationRepository;
use App\Repositories\RsvpRepository;
use App\Repositories\TemplateRepository;
use App\Services\AnalyticsService;
use App\Services\SeoService;

final class DashboardController extends Controller
{
    public function index(Request $request): Response
    {
        $userId = (int) Auth::id();
        $invitations = new InvitationRepository();
        $analytics = new AnalyticsService();

        return $this->view('dashboard.index', [
            'seo'         => SeoService::make()->title(Lang::get('nav.dashboard'))->noindex(),
            'stats'       => $invitations->dashboardStats($userId),
            'recent'      => $invitations->forUser($userId, 6),
            'series'      => $analytics->userOverview($userId, 30)['series'],
            'top'         => $analytics->userOverview($userId, 30)['top'],
            'unreadRsvp'  => (new RsvpRepository())->unreadCount($userId),
            'notifications' => (new NotificationRepository())->forUser($userId, 5),
            'featured'    => (new TemplateRepository())->featured(4),
        ]);
    }
}
