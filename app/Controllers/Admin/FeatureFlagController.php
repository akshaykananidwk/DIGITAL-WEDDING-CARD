<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Request;
use App\Core\Response;
use App\Repositories\FeatureFlagRepository;
use App\Services\AuditService;
use App\Services\FeatureFlagService;

final class FeatureFlagController extends AdminController
{
    public function __construct(
        private readonly FeatureFlagRepository $flags = new FeatureFlagRepository()
    ) {
    }

    public function index(Request $request): Response
    {
        return $this->admin('admin.flags', 'Feature flags', [
            'flags' => $this->flags->map(),
        ]);
    }

    public function toggle(Request $request): Response
    {
        $key = preg_replace('/[^a-z0-9_\-]/i', '', (string) $request->param('key')) ?? '';
        $flags = $this->flags->map();
        if ($key === '' || !isset($flags[$key])) {
            return $this->respond($request, false, 'Unknown feature flag.', 'admin/flags');
        }

        $enabled = $request->bool('enabled');
        $rollout = max(0, min(100, $request->int('rollout', (int) $flags[$key]['rollout'])));

        $this->flags->put(
            $key,
            (string) $flags[$key]['name'],
            $enabled,
            $rollout,
            (string) ($flags[$key]['description'] ?? '')
        );
        FeatureFlagService::instance()->flush();

        AuditService::instance()->log(
            'admin.flag.toggle',
            'feature_flag',
            (int) $flags[$key]['id'],
            $key . ' ' . ($enabled ? 'enabled' : 'disabled') . ' at ' . $rollout . '%'
        );

        return $this->respond(
            $request,
            true,
            $flags[$key]['name'] . ' is now ' . ($enabled ? 'on' : 'off') . '.',
            'admin/flags'
        );
    }
}
