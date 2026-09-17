<?php

declare(strict_types=1);

namespace App\Controllers\Web;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Lang;
use App\Core\Request;
use App\Core\Response;
use App\Repositories\InvitationRepository;
use App\Services\AnalyticsService;
use App\Services\FeatureFlagService;
use App\Services\InvitationService;
use App\Services\SeoService;

final class AnalyticsController extends Controller
{
    public function __construct(
        private readonly AnalyticsService $analytics = new AnalyticsService(),
        private readonly InvitationService $service = new InvitationService()
    ) {
    }

    public function index(Request $request): Response
    {
        $userId = (int) Auth::id();
        $window = $this->window($request);
        [, $days] = $this->analytics->windowBounds($window);

        return $this->view('dashboard.analytics', [
            'seo'         => SeoService::make()->title(Lang::get('analytics.title'))->noindex(),
            'overview'    => $this->analytics->userOverview($userId, $days),
            'window'      => $window,
            'invitations' => (new InvitationRepository())->forUser($userId, 50),
            'advanced'    => FeatureFlagService::instance()->enabled('advanced_analytics', true),
        ]);
    }

    public function show(Request $request): Response
    {
        $invitation = $this->service->findOwnedOrFail($request->int('id'));
        $window = $this->window($request);

        return $this->view('dashboard.invitation-analytics', [
            'seo'        => SeoService::make()->title(Lang::get('analytics.title'))->noindex(),
            'invitation' => $invitation,
            'report'     => $this->analytics->report($invitation, $window),
            'window'     => $window,
            'advanced'   => FeatureFlagService::instance()->enabled('advanced_analytics', true),
        ]);
    }

    public function export(Request $request): Response
    {
        $invitation = $this->service->findOwnedOrFail($request->int('id'));
        $csv = $this->analytics->toCsv($invitation, $this->window($request));

        return Response::download(
            $csv,
            $invitation['slug'] . '-analytics.csv',
            'text/csv; charset=utf-8'
        );
    }

    private function window(Request $request): string
    {
        $window = (string) $request->query('window', '30d');
        return in_array($window, ['today', '7d', '30d', '90d', 'all'], true) ? $window : '30d';
    }
}
