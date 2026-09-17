<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Auth;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\Url;
use App\Repositories\MediaRepository;
use App\Services\AuditService;
use App\Services\MediaService;

/**
 * Media library.
 *
 * Deleting an asset that a template or invitation still uses requires an
 * explicit confirmation, so a shared background cannot vanish from a hundred
 * invitations by accident.
 */
final class MediaController extends AdminController
{
    public function __construct(
        private readonly MediaRepository $media = new MediaRepository(),
        private readonly MediaService $service = new MediaService()
    ) {
    }

    public function index(Request $request): Response
    {
        $filters = [
            'kind'         => in_array($request->query('kind'), ['image', 'audio', 'font', 'video', 'document'], true)
                ? (string) $request->query('kind')
                : '',
            'folder'       => preg_replace('/[^a-z0-9_\-]/i', '', (string) $request->query('folder', '')) ?? '',
            'q'            => mb_substr((string) $request->query('q', ''), 0, 80),
            'library_only' => $request->query('library') === '1',
        ];

        $result = $this->media->paginateLibrary($filters, max(1, $request->int('page', 1)), 36);

        return $this->admin('admin.media', 'Media library', [
            'items'      => $result['rows'],
            'pagination' => $this->paginationMeta($result, Url::to('admin/media'), array_filter($filters)),
            'filters'    => $filters,
            'folders'    => $this->media->folders(),
            'storage'    => $this->media->storageStats(),
        ]);
    }

    public function store(Request $request): Response
    {
        $files = $request->fileList('files');
        if ($files === []) {
            return $this->respond($request, false, 'Please choose at least one file.', 'admin/media');
        }

        $folder = preg_replace('/[^a-z0-9_\-]/i', '', (string) $request->input('folder', 'media')) ?: 'media';
        $isLibrary = $request->bool('is_library');
        $kind = (string) $request->input('kind', 'image');

        $uploaded = 0;
        $errors = [];

        foreach ($files as $file) {
            try {
                if ($kind === 'audio') {
                    $stored = $this->service->storeAudio($file, 'music');
                    $this->media->create([
                        'user_id'       => Auth::id(),
                        'folder'        => 'music',
                        'path'          => $stored['path'],
                        'original_name' => $stored['original_name'],
                        'title'         => pathinfo($stored['original_name'], PATHINFO_FILENAME),
                        'mime'          => $stored['mime'],
                        'extension'     => pathinfo($stored['path'], PATHINFO_EXTENSION),
                        'size'          => $stored['size'],
                        'kind'          => 'audio',
                        'is_library'    => $isLibrary ? 1 : 0,
                        'content_hash'  => hash_file('sha256', UPLOAD_PATH . '/' . $stored['path']) ?: null,
                    ]);
                } else {
                    $this->service->storeToLibrary($file, Auth::id(), $folder, $isLibrary);
                }
                $uploaded++;
            } catch (\Throwable $e) {
                $errors[] = $e->getMessage();
            }
        }

        AuditService::instance()->log('admin.media.upload', 'media', null, $uploaded . ' file(s) uploaded');

        $message = $uploaded . ' file(s) uploaded'
            . ($errors !== [] ? '. Problems: ' . implode(' ', array_slice($errors, 0, 3)) : '.');

        return $this->respond($request, $uploaded > 0, $message, 'admin/media');
    }

    public function update(Request $request): Response
    {
        $id = $request->int('id');
        $item = $this->media->find($id);
        if ($item === null) {
            throw HttpException::notFound();
        }

        $this->media->update($id, [
            'title'      => mb_substr(trim(strip_tags((string) $request->input('title', ''))), 0, 191) ?: null,
            'alt_text'   => mb_substr(trim(strip_tags((string) $request->input('alt_text', ''))), 0, 191) ?: null,
            'folder'     => preg_replace('/[^a-z0-9_\-]/i', '', (string) $request->input('folder', (string) $item['folder'])) ?: 'media',
            'is_library' => $request->bool('is_library') ? 1 : 0,
        ]);

        return $this->respond($request, true, 'File details updated.', 'admin/media');
    }

    public function destroy(Request $request): Response
    {
        $id = $request->int('id');
        $result = $this->service->deleteMedia($id, $request->bool('force'));

        return $this->respond(
            $request,
            (bool) $result['ok'],
            (string) $result['message'],
            'admin/media',
            $result['usage'] ?? []
        );
    }
}
