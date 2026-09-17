<?php
/**
 * @var array<int,array<string,mixed>> $items
 * @var array<string,mixed> $pagination
 * @var array<string,mixed> $filters
 * @var array<int,string> $folders
 * @var array{count:int,bytes:int} $storage
 */
$view->extend('layouts.admin');
?>
<div class="row g-4">
    <div class="col-12 col-xl-9">
        <div class="sk-panel">
            <form class="row g-2 align-items-end mb-3" method="get" action="<?= e(url('admin/media')) ?>">
                <div class="col-12 col-sm">
                    <label class="form-label small mb-1" for="q">Search</label>
                    <input class="form-control form-control-sm" type="search" id="q" name="q"
                           maxlength="80" value="<?= e((string) $filters['q']) ?>">
                </div>
                <div class="col-6 col-sm-auto">
                    <label class="form-label small mb-1" for="kind">Kind</label>
                    <select class="form-select form-select-sm" id="kind" name="kind" data-sk-auto-submit>
                        <option value="">All</option>
                        <?php foreach (['image', 'audio', 'font', 'video', 'document'] as $kind): ?>
                            <option value="<?= e($kind) ?>" <?= $filters['kind'] === $kind ? 'selected' : '' ?>>
                                <?= e(ucfirst($kind)) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-6 col-sm-auto">
                    <label class="form-label small mb-1" for="folder">Folder</label>
                    <select class="form-select form-select-sm" id="folder" name="folder" data-sk-auto-submit>
                        <option value="">All</option>
                        <?php foreach ($folders as $folder): ?>
                            <option value="<?= e($folder) ?>" <?= $filters['folder'] === $folder ? 'selected' : '' ?>>
                                <?= e($folder) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-6 col-sm-auto">
                    <div class="form-check form-switch mt-3">
                        <input class="form-check-input" type="checkbox" value="1" id="library" name="library"
                            <?= !empty($filters['library_only']) ? 'checked' : '' ?> data-sk-auto-submit>
                        <label class="form-check-label small" for="library">Library only</label>
                    </div>
                </div>
                <div class="col-6 col-sm-auto">
                    <button class="btn btn-sm btn-outline-secondary w-100" type="submit">Filter</button>
                </div>
            </form>

            <?php if ($items === []): ?>
                <p class="text-muted small mb-0">No files match those filters.</p>
            <?php else: ?>
                <div class="sk-photo-grid" style="grid-template-columns:repeat(auto-fill,minmax(130px,1fr))">
                    <?php foreach ($items as $item): ?>
                        <?php $isImage = (string) $item['kind'] === 'image'; ?>
                        <figure class="sk-media-tile">
                            <button class="sk-media-tile__preview" type="button" data-bs-toggle="collapse"
                                    data-bs-target="#media-<?= (int) $item['id'] ?>"
                                    aria-label="Edit <?= eattr((string) ($item['title'] ?: $item['original_name'])) ?>">
                                <?php if ($isImage): ?>
                                    <img src="<?= e(url((string) ($item['thumbnail'] ?: $item['path']))) ?>"
                                         alt="<?= eattr((string) $item['alt_text']) ?>" loading="lazy">
                                <?php else: ?>
                                    <i class="bi bi-<?= e(match ((string) $item['kind']) {
                                        'audio' => 'music-note-beamed',
                                        'font' => 'fonts',
                                        'video' => 'camera-reels',
                                        default => 'file-earmark',
                                    }) ?>" aria-hidden="true"></i>
                                <?php endif; ?>
                            </button>
                            <figcaption>
                                <span class="d-block text-truncate small">
                                    <?= e((string) ($item['title'] ?: $item['original_name'])) ?>
                                </span>
                                <span class="text-muted" style="font-size:.7rem">
                                    <?= number_format(((int) $item['size']) / 1024) ?> KB
                                    <?php if ((int) $item['is_library'] === 1): ?>· library<?php endif; ?>
                                </span>
                            </figcaption>

                            <div class="collapse" id="media-<?= (int) $item['id'] ?>">
                                <form class="p-2" method="post" action="<?= e(url('admin/media/' . $item['id'])) ?>">
                                    <?= csrf_field() ?>
                                    <input class="form-control form-control-sm mb-1" type="text" name="title"
                                           value="<?= e((string) $item['title']) ?>" placeholder="Title" aria-label="Title">
                                    <input class="form-control form-control-sm mb-1" type="text" name="alt_text"
                                           value="<?= e((string) $item['alt_text']) ?>" placeholder="Alt text" aria-label="Alt text">
                                    <input class="form-control form-control-sm mb-1" type="text" name="folder"
                                           value="<?= e((string) $item['folder']) ?>" placeholder="Folder" aria-label="Folder">
                                    <div class="form-check form-switch mb-2">
                                        <input class="form-check-input" type="checkbox" value="1" name="is_library"
                                               id="lib-<?= (int) $item['id'] ?>" <?= (int) $item['is_library'] === 1 ? 'checked' : '' ?>>
                                        <label class="form-check-label small" for="lib-<?= (int) $item['id'] ?>">
                                            Offer to users
                                        </label>
                                    </div>
                                    <button class="btn btn-sm btn-outline-primary w-100" type="submit">Save</button>
                                </form>
                                <form class="px-2 pb-2" method="post" action="<?= e(url('admin/media/' . $item['id'])) ?>">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="_method" value="DELETE">
                                    <div class="form-check mb-1">
                                        <input class="form-check-input" type="checkbox" value="1" name="force"
                                               id="force-<?= (int) $item['id'] ?>">
                                        <label class="form-check-label small" for="force-<?= (int) $item['id'] ?>">
                                            Delete even if in use
                                        </label>
                                    </div>
                                    <button class="btn btn-sm btn-outline-danger w-100" type="submit"
                                            data-sk-confirm="Delete this file?">Delete</button>
                                </form>
                            </div>
                        </figure>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <?php $view->include('partials.pagination', ['pagination' => $pagination]); ?>
    </div>

    <div class="col-12 col-xl-3">
        <section class="sk-panel mb-4">
            <h2 class="h6 mb-3">Upload</h2>
            <form method="post" action="<?= e(url('admin/media')) ?>" enctype="multipart/form-data">
                <?= csrf_field() ?>
                <div class="mb-2">
                    <label class="form-label small mb-1" for="files">Files</label>
                    <input class="form-control form-control-sm" type="file" id="files" name="files[]" multiple required
                           accept="image/jpeg,image/png,image/webp,audio/mpeg,font/ttf,font/otf,.ttf,.otf">
                </div>
                <div class="mb-2">
                    <label class="form-label small mb-1" for="upload-folder">Folder</label>
                    <input class="form-control form-control-sm" type="text" id="upload-folder" name="folder"
                           value="media" maxlength="40">
                </div>
                <div class="form-check form-switch mb-3">
                    <input class="form-check-input" type="checkbox" value="1" id="upload-library" name="is_library">
                    <label class="form-check-label small" for="upload-library">Add to the user library</label>
                </div>
                <button class="btn btn-sm btn-primary w-100" type="submit" data-sk-loading="Uploading…">Upload</button>
            </form>
            <p class="form-text mb-0">
                Every upload is checked by content type, re-encoded where possible and stored under a random name.
            </p>
        </section>

        <section class="sk-panel">
            <h2 class="h6 mb-3">Storage</h2>
            <dl class="row small mb-0">
                <dt class="col-7 text-muted">Files</dt>
                <dd class="col-5 text-end"><?= number_format($storage['count']) ?></dd>
                <dt class="col-7 text-muted">Total size</dt>
                <dd class="col-5 text-end"><?= number_format($storage['bytes'] / 1048576, 1) ?> MB</dd>
            </dl>
        </section>
    </div>
</div>
