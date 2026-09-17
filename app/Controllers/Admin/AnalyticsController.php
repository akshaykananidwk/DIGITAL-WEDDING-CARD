<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Request;
use App\Core\Response;
use App\Repositories\TemplateRepository;
use App\Repositories\UserRepository;
use App\Services\AnalyticsService;

final class AnalyticsController extends AdminController
{
    public function index(Request $request): Response
    {
        $days = max(7, min(365, $request->int('days', 30)));
        $analytics = new AnalyticsService();

        return $this->admin('admin.analytics', 'Analytics', [
            'days'     => $days,
            'platform' => $analytics->platformOverview($days),
            'signups'  => (new UserRepository())->signupSeries($days),
            'templates' => (new TemplateRepository())->mostUsed(12),
            'userStats' => (new UserRepository())->stats(),
            'invitationStats' => (new \App\Repositories\InvitationRepository())->stats(),
        ]);
    }
}
