<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Services\AnalyticsService;
use App\Services\InvitationService;

final class AnalyticsApiController extends Controller
{
    public function __construct(
        private readonly AnalyticsService $analytics = new AnalyticsService()
    ) {
    }

    public function show(Request $request): Response
    {
        $invitation = (new InvitationService())->findOwnedOrFail($request->int('id'));
        $window = (string) $request->query('window', '30d');
        $window = in_array($window, ['today', '7d', '30d', '90d', 'all'], true) ? $window : '30d';

        return $this->success($this->analytics->report($invitation, $window));
    }

    public function overview(Request $request): Response
    {
        $days = max(1, min(365, $request->int('days', 30)));
        return $this->success($this->analytics->userOverview((int) Auth::id(), $days));
    }
}
