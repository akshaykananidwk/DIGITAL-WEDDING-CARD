<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\Url;
use App\Repositories\BackupRepository;
use App\Services\AuditService;
use App\Services\BackupService;

final class BackupController extends AdminController
{
    public function __construct(
        private readonly BackupService $backups = new BackupService(),
        private readonly BackupRepository $repository = new BackupRepository()
    ) {
    }

    public function index(Request $request): Response
    {
        $result = $this->repository->paginateBackups(max(1, $request->int('page', 1)), 20);

        return $this->admin('admin.system.backups', 'Backups', [
            'backups'    => $result['rows'],
            'pagination' => $this->paginationMeta($result, Url::to('admin/system/backups')),
            'stats'      => $this->backups->stats(),
            'canZip'     => class_exists(\ZipArchive::class),
        ]);
    }

    public function store(Request $request): Response
    {
        $type = (string) $request->input('type', BackupService::TYPE_DATABASE);
        if (!in_array($type, [BackupService::TYPE_DATABASE, BackupService::TYPE_FILES, BackupService::TYPE_FULL], true)) {
            return $this->respond($request, false, 'Unknown backup type.', 'admin/system/backups');
        }

        $result = $this->backups->create($type, mb_substr((string) $request->input('note', ''), 0, 300));

        return $this->respond(
            $request,
            (bool) $result['ok'],
            (string) $result['message'],
            'admin/system/backups',
            ['backup_id' => $result['backup_id']]
        );
    }

    public function download(Request $request): Response
    {
        $file = $this->backups->pathForDownload($request->int('id'));
        if ($file === null) {
            throw HttpException::notFound('That backup file is not available.');
        }

        AuditService::instance()->log('admin.backup.download', 'backup', $request->int('id'), $file['filename']);

        return Response::file($file['path'], $file['filename'], $file['mime']);
    }

    /**
     * Restore a backup.
     *
     * Deliberately two-step: the operator must type RESTORE, because this
     * overwrites live data.
     */
    public function restore(Request $request): Response
    {
        if ((string) $request->input('confirm') !== 'RESTORE') {
            return $this->respond(
                $request,
                false,
                'Type RESTORE to confirm - this overwrites current data.',
                'admin/system/backups'
            );
        }

        $id = $request->int('id');
        $backup = $this->repository->find($id);
        if ($backup === null) {
            throw HttpException::notFound();
        }
        $path = (string) ($backup['path'] ?? '');
        if ($path === '' || !is_file($path)) {
            return $this->respond($request, false, 'The backup file is missing from disk.', 'admin/system/backups');
        }

        // Safety net: snapshot the current state before overwriting it.
        $this->backups->create(BackupService::TYPE_DATABASE, 'Automatic snapshot before restoring #' . $id);

        $maintenance = new \App\Services\MaintenanceService();
        $maintenance->enable('Restoring a backup. Back in a moment.', 180);

        try {
            if (str_ends_with($path, '.sql')) {
                $result = $this->backups->restoreDatabase($path);
            } else {
                $dump = $this->backups->extractDatabaseDump($path);
                $fileResult = $this->backups->restoreFiles($path);
                $dbResult = $dump !== null
                    ? $this->backups->restoreDatabase($dump)
                    : ['ok' => true, 'message' => 'No database dump inside the archive.'];
                if ($dump !== null) {
                    @unlink($dump);
                }
                $result = [
                    'ok'      => $fileResult['ok'] && $dbResult['ok'],
                    'message' => $fileResult['message'] . ' ' . $dbResult['message'],
                ];
            }
        } finally {
            $maintenance->disable();
        }

        AuditService::instance()->log('admin.backup.restore', 'backup', $id, (string) $result['message']);

        return $this->respond($request, (bool) $result['ok'], (string) $result['message'], 'admin/system/backups');
    }

    public function verify(Request $request): Response
    {
        $result = $this->backups->verify($request->int('id'));
        return $this->respond($request, (bool) $result['ok'], (string) $result['message'], 'admin/system/backups');
    }

    public function destroy(Request $request): Response
    {
        $result = $this->backups->delete($request->int('id'));
        return $this->respond($request, (bool) $result['ok'], (string) $result['message'], 'admin/system/backups');
    }
}
