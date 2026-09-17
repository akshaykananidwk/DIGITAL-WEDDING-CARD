<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Request;
use App\Core\Response;
use App\Core\Url;
use App\Repositories\AuditRepository;

final class AuditController extends AdminController
{
    public function index(Request $request): Response
    {
        $repository = new AuditRepository();
        $filters = [
            'action'  => preg_replace('/[^a-z0-9_.\-]/i', '', (string) $request->query('action', '')) ?? '',
            'user_id' => $request->int('user_id'),
            'q'       => mb_substr((string) $request->query('q', ''), 0, 80),
            'from'    => preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $request->query('from', '')) === 1
                ? (string) $request->query('from')
                : '',
            'to'      => preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $request->query('to', '')) === 1
                ? (string) $request->query('to')
                : '',
        ];

        $result = $repository->paginateLogs($filters, max(1, $request->int('page', 1)), 40);

        return $this->admin('admin.audit', 'Audit log', [
            'entries'    => $result['rows'],
            'pagination' => $this->paginationMeta($result, Url::to('admin/audit'), array_filter($filters)),
            'filters'    => $filters,
            'actions'    => $repository->actions(),
        ]);
    }
}
