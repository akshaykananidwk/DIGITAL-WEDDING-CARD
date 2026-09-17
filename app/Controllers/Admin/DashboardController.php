<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Request;
use App\Core\Response;
use App\Repositories\AuditRepository;
use App\Repositories\InvitationRepository;
use App\Repositories\RsvpRepository;
use App\Repositories\TemplateRepository;
use App\Repositories\UserRepository;
use App\Services\AiService;
use App\Services\AnalyticsService;
use App\Services\BackupService;
use App\Services\HealthService;
use App\Services\UpdateService;

final class DashboardController extends AdminController
{
    public function index(Request $request): Response
    {
        $health = (new HealthService())->latest();
        $updates = new UpdateService();

        return $this->admin('admin.dashboard', 'Dashboard', [
            'userStats'     => (new UserRepository())->stats(),
            'invitationStats' => (new InvitationRepository())->stats(),
            'templateStats' => (new TemplateRepository())->stats(),
            'signups'       => (new UserRepository())->signupSeries(30),
            'platform'      => (new AnalyticsService())->platformOverview(30),
            'recentAudit'   => (new AuditRepository())->recent(8),
            'health'        => $health,
            'backup'        => (new BackupService())->stats(),
            'update'        => $updates->latest(),
            'updateLocked'  => $updates->isLocked(),
            'aiConfigured'  => (new AiService())->isConfigured(),
            'popularTemplates' => (new TemplateRepository())->mostUsed(6),
        ]);
    }
}
