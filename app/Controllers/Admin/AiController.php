<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Core\Url;
use App\Core\ValidationException;
use App\Repositories\AiRepository;
use App\Services\AiService;
use App\Services\AuditService;

/**
 * Gemini configuration.
 *
 * The API key is stored encrypted, never rendered in full and never logged.
 * Submitting the form with the key field untouched keeps the stored key.
 */
final class AiController extends AdminController
{
    public function __construct(
        private readonly AiRepository $repository = new AiRepository(),
        private readonly AiService $ai = new AiService()
    ) {
    }

    public function index(Request $request): Response
    {
        return $this->admin('admin.ai', 'AI settings', [
            'settings' => $this->ai->safeSettings(),
            'stats'    => $this->repository->stats(30),
            'usage'    => $this->repository->usageToday(),
            'models'   => [
                'gemini-2.0-flash'      => 'Gemini 2.0 Flash (fast, low cost)',
                'gemini-2.0-flash-lite' => 'Gemini 2.0 Flash Lite',
                'gemini-1.5-flash'      => 'Gemini 1.5 Flash',
                'gemini-1.5-pro'        => 'Gemini 1.5 Pro (highest quality)',
            ],
            'configured' => $this->ai->isConfigured(),
        ]);
    }

    public function update(Request $request): Response
    {
        try {
            $data = $this->validate($request, [
                'model'         => 'required|string|max:80|alpha_dash',
                'endpoint'      => 'nullable|url',
                'temperature'   => 'required|numeric|min:0|max:2',
                'top_p'         => 'required|numeric|min:0|max:1',
                'max_tokens'    => 'required|integer|min:64|max:8192',
                'timeout'       => 'required|integer|min:5|max:120',
                'daily_limit'   => 'required|integer|min:0|max:100000',
                'system_prompt' => 'nullable|string|max:4000',
                'api_key'       => 'nullable|string|max:200',
            ], [
                'api_key' => 'API key',
            ]);
        } catch (ValidationException $e) {
            return $this->validationResponse($e, $request);
        }

        $payload = [
            'model'         => (string) $data['model'],
            'endpoint'      => $data['endpoint'] ?? null,
            'temperature'   => (float) $data['temperature'],
            'top_p'         => (float) $data['top_p'],
            'max_tokens'    => (int) $data['max_tokens'],
            'timeout'       => (int) $data['timeout'],
            'daily_limit'   => (int) $data['daily_limit'],
            'system_prompt' => $data['system_prompt'] ?? null,
            'is_enabled'    => $request->bool('is_enabled') ? 1 : 0,
            'allow_recommendations' => $request->bool('allow_recommendations', true) ? 1 : 0,
        ];

        // Only overwrite the key when a new one was typed.
        $key = trim((string) $request->raw('api_key', ''));
        if ($key !== '' && !str_starts_with($key, '••')) {
            $payload['api_key'] = $key;
        }

        $this->repository->saveSettings($payload, Auth::id());

        // The audit entry records that AI settings changed, never the key.
        AuditService::instance()->log(
            'admin.ai.update',
            'ai_settings',
            null,
            'AI ' . ($payload['is_enabled'] === 1 ? 'enabled' : 'disabled') . ', model ' . $payload['model']
        );

        return $this->respond($request, true, 'AI settings saved.', 'admin/ai');
    }

    public function test(Request $request): Response
    {
        $result = $this->ai->testConnection();
        AuditService::instance()->log('admin.ai.test', 'ai_settings', null, $result['ok'] ? 'success' : 'failed');

        return $this->respond($request, (bool) $result['ok'], (string) $result['message'], 'admin/ai');
    }

    public function clearKey(Request $request): Response
    {
        $this->repository->clearApiKey(Auth::id());
        $this->repository->saveSettings(['is_enabled' => 0], Auth::id());
        AuditService::instance()->log('admin.ai.clear_key', 'ai_settings', null, 'API key removed');

        return $this->respond($request, true, 'API key removed and AI features switched off.', 'admin/ai');
    }

    public function logs(Request $request): Response
    {
        $filters = [
            'status' => in_array($request->query('status'), ['success', 'error', 'blocked', 'quota'], true)
                ? (string) $request->query('status')
                : '',
            'action' => preg_replace('/[^a-z0-9_\-]/i', '', (string) $request->query('action', '')) ?? '',
        ];

        $result = $this->repository->paginateLogs($filters, max(1, $request->int('page', 1)), 40);

        return $this->admin('admin.ai-logs', 'AI usage log', [
            'entries'    => $result['rows'],
            'pagination' => $this->paginationMeta($result, Url::to('admin/ai/logs'), array_filter($filters)),
            'filters'    => $filters,
            'stats'      => $this->repository->stats(30),
        ]);
    }
}
