<?php
/**
 * Steps 3-7 of the builder.
 *
 * Desktop: controls on the left, live preview pinned on the right.
 * Mobile:  preview on top, controls underneath.
 *
 * @var array<string,mixed> $invitation
 * @var array<string,mixed>|null $template
 * @var int $step
 * @var array<string,array<int,array<string,mixed>>> $fieldsBySection
 * @var array<string,string> $sectionLabels
 * @var array<string,mixed> $values
 * @var array<int,array<string,mixed>> $sections
 * @var array<int,array<string,mixed>> $photos
 * @var array<string,mixed>|null $music
 * @var array<int,array<string,mixed>> $musicLibrary
 * @var array<string,string> $fonts
 * @var array<int,array<string,mixed>> $palettes
 * @var array<string,mixed> $theme
 * @var array<int,string> $missing
 * @var bool $aiEnabled
 * @var int $maxPhotos
 */
$view->extend('layouts.app');

$id = (int) $invitation['id'];
$settings = is_array($invitation['settings'] ?? null) ? $invitation['settings'] : [];
$overrides = is_array($invitation['theme_overrides'] ?? null) ? $invitation['theme_overrides'] : [];
$sectionState = [];
foreach ($sections as $row) {
    $sectionState[(string) $row['section_key']] = (int) $row['is_visible'] === 1;
}
$heroPhotos = array_values(array_filter($photos, static fn (array $p): bool => (string) $p['role'] === 'hero'));
$galleryPhotos = array_values(array_filter($photos, static fn (array $p): bool => (string) $p['role'] !== 'hero'));
$stepTitles = [
    3 => __('builder.step_details'),
    4 => __('builder.step_photos'),
    5 => __('builder.step_design'),
    6 => __('builder.step_preview'),
    7 => __('builder.step_publish'),
];
$themeValue = static fn (string $key, string $default = ''): string
    => (string) ($overrides[$key] ?? $theme[$key] ?? $default);
?>

<div class="container-fluid sk-builder py-3">
    <?php $view->include('partials.wizard-steps', ['step' => $step, 'invitation' => $invitation]); ?>

    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 my-3">
        <div>
            <h1 class="h5 mb-0"><?= e($stepTitles[$step] ?? '') ?></h1>
            <p class="small text-muted mb-0">
                <?= e($invitation['title']) ?>
                <?php if ($template !== null): ?>
                    <span aria-hidden="true">·</span> <?= e($template['name']) ?>
                <?php endif; ?>
            </p>
        </div>
        <div class="d-flex align-items-center gap-2">
            <span class="small text-muted" id="sk-save-status"></span>
            <a class="btn btn-sm btn-outline-secondary" href="<?= e(url('invitations')) ?>">
                <i class="bi bi-list-ul me-1" aria-hidden="true"></i><?= e(__('nav.invitations')) ?>
            </a>
        </div>
    </div>

    <?php if ($missing !== [] && $step >= 6): ?>
        <div class="alert alert-warning small">
            <?= e(__('builder.missing_fields', ['fields' => implode(', ', $missing)])) ?>
            <a class="ms-1" href="<?= e(url('builder/' . $id, ['step' => 3])) ?>"><?= e(__('common.edit')) ?></a>
        </div>
    <?php endif; ?>

    <div class="row g-3 sk-builder__grid">
        <!-- Live preview: first in the DOM so it sits on top on a phone. -->
        <div class="col-12 col-lg-5 order-1 order-lg-2">
            <div class="sk-builder__preview">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="small fw-semibold"><?= e(__('builder.preview_live')) ?></span>
                    <div class="btn-group btn-group-sm">
                        <button class="btn btn-outline-secondary" type="button" data-sk-refresh-preview
                                aria-label="<?= eattr(__('builder.preview_live')) ?>">
                            <i class="bi bi-arrow-clockwise" aria-hidden="true"></i>
                        </button>
                        <a class="btn btn-outline-secondary" href="<?= e(url('builder/' . $id . '/preview')) ?>"
                           target="_blank" rel="noopener" aria-label="<?= eattr(__('builder.step_preview')) ?>">
                            <i class="bi bi-arrows-fullscreen" aria-hidden="true"></i>
                        </a>
                    </div>
                </div>
                <div class="sk-preview-frame">
                    <iframe id="sk-preview-frame" name="sk-preview-frame"
                            src="<?= e(url('builder/' . $id . '/preview')) ?>"
                            title="<?= eattr(__('builder.preview_live')) ?>" loading="lazy"></iframe>
                </div>
                <p class="form-text text-center mb-0"><?= e(__('builder.preview_hint')) ?></p>
            </div>
        </div>

        <div class="col-12 col-lg-7 order-2 order-lg-1">

            <?php if ($step === 3): ?>
                <form class="sk-panel" id="sk-content-form" method="post"
                      action="<?= e(url('builder/' . $id . '/content')) ?>" data-sk-no-guard>
                    <?= csrf_field() ?>
                    <input type="hidden" name="next" value="4">

                    <?php if ($aiEnabled): ?>
                        <div class="d-flex flex-wrap align-items-end gap-2 mb-3 pb-3 border-bottom">
                            <div>
                                <label class="form-label small mb-1" for="sk-ai-tone"><?= e(__('builder.ai_tone')) ?></label>
                                <select class="form-select form-select-sm" id="sk-ai-tone">
                                    <option value="traditional"><?= e(__('builder.tone_traditional')) ?></option>
                                    <option value="warm"><?= e(__('builder.tone_warm')) ?></option>
                                    <option value="modern"><?= e(__('builder.tone_modern')) ?></option>
                                    <option value="formal"><?= e(__('builder.tone_formal')) ?></option>
                                </select>
                            </div>
                            <p class="form-text mb-0 flex-grow-1">
                                <i class="bi bi-shield-check me-1" aria-hidden="true"></i><?= e(__('builder.ai_facts_note')) ?>
                            </p>
                        </div>
                    <?php endif; ?>

                    <?php $first = true; ?>
                    <?php foreach ($sectionLabels as $sectionKey => $sectionLabel): ?>
                        <?php $fields = $fieldsBySection[$sectionKey] ?? []; ?>
                        <?php if ($fields === []) { continue; } ?>
                        <fieldset class="sk-fieldset">
                            <legend class="h6"><?= e($sectionLabel) ?></legend>
                            <div class="row g-3">
                                <?php foreach ($fields as $field): ?>
                                    <div class="col-12 <?= in_array((string) $field['type'], ['textarea', 'richtext'], true) ? '' : 'col-sm-6' ?>">
                                        <?php $view->include('partials.field-input', [
                                            'field'     => $field,
                                            'values'    => $values,
                                            'aiEnabled' => $aiEnabled,
                                            'fonts'     => $fonts,
                                        ]); ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </fieldset>
                        <?php $first = false; ?>
                    <?php endforeach; ?>

                    <?php if ($fieldsBySection === []): ?>
                        <p class="text-muted small"><?= e(__('builder.no_fields')) ?></p>
                    <?php endif; ?>

                    <div class="d-flex flex-wrap gap-2 mt-3">
                        <button class="btn btn-outline-secondary" type="button" data-sk-save-now>
                            <?= e(__('builder.save_draft')) ?>
                        </button>
                        <button class="btn btn-primary" type="submit" data-sk-loading="<?= eattr(__('builder.saving')) ?>">
                            <?= e(__('builder.next')) ?> <i class="bi bi-arrow-right ms-1" aria-hidden="true"></i>
                        </button>
                    </div>
                </form>

            <?php elseif ($step === 4): ?>
                <section class="sk-panel mb-3">
                    <h2 class="h6 mb-2"><?= e(__('builder.hero_photo')) ?></h2>
                    <div class="sk-photo-grid" id="sk-hero-grid">
                        <?php foreach ($heroPhotos as $photo): ?>
                            <div class="sk-photo" data-id="<?= (int) $photo['id'] ?>">
                                <img src="<?= e(url((string) ($photo['thumbnail'] ?: $photo['path']))) ?>" alt="" loading="lazy">
                                <button class="sk-photo__remove" type="button"
                                        data-sk-remove-photo="<?= (int) $photo['id'] ?>"
                                        aria-label="<?= eattr(__('common.delete')) ?>">&times;</button>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <label class="btn btn-sm btn-outline-secondary mt-2 mb-0" for="sk-hero-input">
                        <i class="bi bi-image me-1" aria-hidden="true"></i><?= e(__('builder.choose_photo')) ?>
                    </label>
                    <input class="visually-hidden" type="file" id="sk-hero-input" accept="image/jpeg,image/png,image/webp">
                </section>

                <section class="sk-panel mb-3">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <h2 class="h6 mb-0"><?= e(__('builder.step_photos')) ?></h2>
                        <span class="small text-muted"><?= count($galleryPhotos) ?>/<?= (int) $maxPhotos ?></span>
                    </div>

                    <div class="sk-dropzone" id="sk-dropzone">
                        <i class="bi bi-cloud-arrow-up" aria-hidden="true"></i>
                        <p class="mb-1 small"><?= e(__('builder.drop_photos')) ?></p>
                        <label class="btn btn-sm btn-primary mb-0" for="sk-photo-input">
                            <?= e(__('builder.choose_photo')) ?>
                        </label>
                        <input class="visually-hidden" type="file" id="sk-photo-input" multiple
                               data-sk-role="gallery" accept="image/jpeg,image/png,image/webp">
                        <p class="form-text mb-0">
                            <?= e(__('builder.photo_hint', ['size' => (string) config('uploads.max_image_mb', 8) . ' MB'])) ?>
                        </p>
                    </div>

                    <div class="sk-photo-grid mt-3" id="sk-photo-grid">
                        <?php foreach ($galleryPhotos as $photo): ?>
                            <div class="sk-photo" data-id="<?= (int) $photo['id'] ?>">
                                <img src="<?= e(url((string) ($photo['thumbnail'] ?: $photo['path']))) ?>" alt="" loading="lazy">
                                <button class="sk-photo__remove" type="button"
                                        data-sk-remove-photo="<?= (int) $photo['id'] ?>"
                                        aria-label="<?= eattr(__('common.delete')) ?>">&times;</button>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </section>

                <section class="sk-panel mb-3">
                    <h2 class="h6 mb-2"><?= e(__('invite.play_music')) ?></h2>
                    <p class="form-text mt-0"><?= e(__('builder.music_hint')) ?></p>

                    <?php if ($music !== null): ?>
                        <div class="d-flex align-items-center gap-2 mb-3">
                            <audio class="flex-grow-1" controls preload="none"
                                   src="<?= e(url((string) $music['path'])) ?>"></audio>
                            <form method="post" action="<?= e(url('builder/' . $id . '/music')) ?>">
                                <?= csrf_field() ?>
                                <input type="hidden" name="_method" value="DELETE">
                                <button class="btn btn-sm btn-outline-danger" type="submit"
                                        aria-label="<?= eattr(__('common.delete')) ?>">
                                    <i class="bi bi-trash" aria-hidden="true"></i>
                                </button>
                            </form>
                        </div>
                    <?php endif; ?>

                    <form method="post" action="<?= e(url('builder/' . $id . '/music')) ?>" enctype="multipart/form-data">
                        <?= csrf_field() ?>
                        <div class="row g-2 align-items-end">
                            <div class="col-12 col-sm-6">
                                <label class="form-label small mb-1" for="music"><?= e(__('builder.upload_music')) ?></label>
                                <input class="form-control form-control-sm <?= error_for('music') ? 'is-invalid' : '' ?>"
                                       type="file" id="music" name="music" accept="audio/mpeg,audio/mp3">
                                <?php if ($message = error_for('music')): ?>
                                    <div class="invalid-feedback"><?= e($message) ?></div>
                                <?php endif; ?>
                            </div>
                            <?php if ($musicLibrary !== []): ?>
                                <div class="col-12 col-sm-6">
                                    <label class="form-label small mb-1" for="library_id"><?= e(__('builder.music_library')) ?></label>
                                    <select class="form-select form-select-sm" id="library_id" name="library_id">
                                        <option value="0"><?= e(__('common.none')) ?></option>
                                        <?php foreach ($musicLibrary as $track): ?>
                                            <option value="<?= (int) $track['id'] ?>">
                                                <?= e((string) ($track['title'] ?: $track['original_name'])) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            <?php endif; ?>
                            <div class="col-12">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="autoplay" name="autoplay" value="1"
                                        <?= !empty($settings['music_autoplay']) ? 'checked' : '' ?>>
                                    <label class="form-check-label small" for="autoplay">
                                        <?= e(__('builder.music_autoplay')) ?>
                                    </label>
                                </div>
                            </div>
                            <div class="col-12">
                                <button class="btn btn-sm btn-outline-primary" type="submit"><?= e(__('common.save')) ?></button>
                            </div>
                        </div>
                    </form>
                </section>

                <div class="d-flex flex-wrap gap-2">
                    <a class="btn btn-outline-secondary" href="<?= e(url('builder/' . $id, ['step' => 3])) ?>">
                        <i class="bi bi-arrow-left me-1" aria-hidden="true"></i><?= e(__('builder.back')) ?>
                    </a>
                    <a class="btn btn-primary" href="<?= e(url('builder/' . $id, ['step' => 5])) ?>">
                        <?= e(__('builder.next')) ?> <i class="bi bi-arrow-right ms-1" aria-hidden="true"></i>
                    </a>
                </div>

            <?php elseif ($step === 5): ?>
                <form class="sk-panel" method="post" action="<?= e(url('builder/' . $id . '/design')) ?>">
                    <?= csrf_field() ?>

                    <fieldset class="sk-fieldset">
                        <legend class="h6"><?= e(__('builder.palette')) ?></legend>
                        <div class="sk-palettes">
                            <?php foreach ($palettes as $paletteKey => $palette): ?>
                                <?php $tokens = [
                                    'primary'    => (string) $palette['primary'],
                                    'secondary'  => (string) $palette['secondary'],
                                    'background' => (string) $palette['background'],
                                    'surface'    => (string) ($palette['surface'] ?? $palette['background']),
                                    'text'       => (string) $palette['text'],
                                    'accent'     => (string) ($palette['accent'] ?? $palette['secondary']),
                                ]; ?>
                                <button class="sk-palette <?= $themeValue('primary') === $tokens['primary'] ? 'active' : '' ?>"
                                        type="button" data-sk-palette='<?= eattr(json_encode($tokens)) ?>'
                                        title="<?= eattr((string) $palette['label']) ?>">
                                    <span style="background:<?= eattr($tokens['primary']) ?>"></span>
                                    <span style="background:<?= eattr($tokens['secondary']) ?>"></span>
                                    <span style="background:<?= eattr($tokens['background']) ?>"></span>
                                    <span class="sk-palette__name"><?= e((string) $palette['label']) ?></span>
                                </button>
                            <?php endforeach; ?>
                        </div>
                    </fieldset>

                    <fieldset class="sk-fieldset">
                        <legend class="h6"><?= e(__('builder.colours')) ?></legend>
                        <div class="row g-3">
                            <?php foreach ([
                                'primary'    => __('builder.colour_primary'),
                                'secondary'  => __('builder.colour_secondary'),
                                'background' => __('builder.colour_background'),
                                'text'       => __('builder.colour_text'),
                            ] as $token => $label): ?>
                                <div class="col-6 col-sm-3">
                                    <label class="form-label small mb-1" for="t-<?= e($token) ?>"><?= e($label) ?></label>
                                    <div class="input-group input-group-sm">
                                        <input class="form-control form-control-color flex-grow-0" type="color"
                                               value="<?= e($themeValue($token, '#C8102E')) ?>"
                                               data-sk-color-sync="#t-<?= e($token) ?>"
                                               aria-label="<?= eattr($label) ?>">
                                        <input class="form-control" type="text" id="t-<?= e($token) ?>"
                                               name="theme[<?= e($token) ?>]" data-sk-theme="<?= e($token) ?>"
                                               pattern="#[0-9a-fA-F]{6}" value="<?= e($themeValue($token)) ?>">
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </fieldset>

                    <fieldset class="sk-fieldset">
                        <legend class="h6"><?= e(__('builder.typography')) ?></legend>
                        <div class="row g-3">
                            <div class="col-12 col-sm-6">
                                <label class="form-label small mb-1" for="t-heading"><?= e(__('builder.heading_font')) ?></label>
                                <select class="form-select form-select-sm" id="t-heading"
                                        name="theme[heading_font]" data-sk-theme="heading_font">
                                    <?php foreach ($fonts as $family => $label): ?>
                                        <option value="<?= e((string) $family) ?>"
                                            <?= $themeValue('heading_font') === (string) $family ? 'selected' : '' ?>>
                                            <?= e((string) $label) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-12 col-sm-6">
                                <label class="form-label small mb-1" for="t-body"><?= e(__('builder.body_font')) ?></label>
                                <select class="form-select form-select-sm" id="t-body"
                                        name="theme[body_font]" data-sk-theme="body_font">
                                    <?php foreach ($fonts as $family => $label): ?>
                                        <option value="<?= e((string) $family) ?>"
                                            <?= $themeValue('body_font') === (string) $family ? 'selected' : '' ?>>
                                            <?= e((string) $label) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-12 col-sm-6">
                                <label class="form-label small mb-1" for="t-align"><?= e(__('builder.text_align')) ?></label>
                                <select class="form-select form-select-sm" id="t-align"
                                        name="theme[text_align]" data-sk-theme="text_align">
                                    <?php foreach (['center' => 'Center', 'left' => 'Left', 'right' => 'Right'] as $value => $label): ?>
                                        <option value="<?= e($value) ?>" <?= $themeValue('text_align') === $value ? 'selected' : '' ?>>
                                            <?= e($label) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-12 col-sm-6">
                                <label class="form-label small mb-1" for="t-motion"><?= e(__('builder.motion')) ?></label>
                                <select class="form-select form-select-sm" id="t-motion"
                                        name="theme[motion]" data-sk-theme="motion">
                                    <?php foreach ([
                                        'gentle' => __('builder.motion_gentle'),
                                        'rich'   => __('builder.motion_rich'),
                                        'none'   => __('builder.motion_none'),
                                    ] as $value => $label): ?>
                                        <option value="<?= e($value) ?>" <?= $themeValue('motion') === $value ? 'selected' : '' ?>>
                                            <?= e($label) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </fieldset>

                    <fieldset class="sk-fieldset">
                        <legend class="h6"><?= e(__('builder.sections')) ?></legend>
                        <div class="row g-2">
                            <?php foreach (App\Services\InvitationService::DEFAULT_SECTIONS as $key => $default): ?>
                                <div class="col-6 col-sm-4">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" value="1"
                                               id="s-<?= e($key) ?>" name="sections[<?= e($key) ?>]"
                                            <?= ($sectionState[$key] ?? $default) ? 'checked' : '' ?>>
                                        <label class="form-check-label small" for="s-<?= e($key) ?>">
                                            <?= e(__('sections.' . $key)) ?>
                                        </label>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </fieldset>

                    <fieldset class="sk-fieldset">
                        <legend class="h6"><?= e(__('builder.options')) ?></legend>
                        <div class="row g-2">
                            <?php foreach ([
                                'show_countdown' => __('invite.countdown'),
                                'show_rsvp'      => __('invite.rsvp_title'),
                                'show_share'     => __('invite.share'),
                                'show_qr'        => __('invite.scan_qr'),
                                'skip_animation' => __('invite.skip_animation'),
                            ] as $key => $label): ?>
                                <div class="col-6 col-sm-4">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" value="1"
                                               id="o-<?= e($key) ?>" name="settings[<?= e($key) ?>]"
                                            <?= !empty($settings[$key]) ? 'checked' : '' ?>>
                                        <label class="form-check-label small" for="o-<?= e($key) ?>"><?= e($label) ?></label>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </fieldset>

                    <div class="d-flex flex-wrap gap-2 mt-3">
                        <a class="btn btn-outline-secondary" href="<?= e(url('builder/' . $id, ['step' => 4])) ?>">
                            <i class="bi bi-arrow-left me-1" aria-hidden="true"></i><?= e(__('builder.back')) ?>
                        </a>
                        <button class="btn btn-primary" type="submit" data-sk-loading="<?= eattr(__('builder.saving')) ?>">
                            <?= e(__('builder.next')) ?> <i class="bi bi-arrow-right ms-1" aria-hidden="true"></i>
                        </button>
                    </div>
                </form>

            <?php elseif ($step === 6): ?>
                <section class="sk-panel">
                    <h2 class="h6 mb-2"><?= e(__('builder.step_preview')) ?></h2>
                    <p class="small text-muted"><?= e(__('builder.preview_check')) ?></p>
                    <ul class="list-unstyled small d-grid gap-2 mb-3">
                        <?php foreach ([
                            __('builder.check_names'),
                            __('builder.check_date'),
                            __('builder.check_venue'),
                            __('builder.check_phone'),
                        ] as $index => $item): ?>
                            <li>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="chk-<?= $index ?>">
                                    <label class="form-check-label" for="chk-<?= $index ?>"><?= e($item) ?></label>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                    <div class="d-flex flex-wrap gap-2">
                        <a class="btn btn-outline-secondary" href="<?= e(url('builder/' . $id, ['step' => 5])) ?>">
                            <i class="bi bi-arrow-left me-1" aria-hidden="true"></i><?= e(__('builder.back')) ?>
                        </a>
                        <a class="btn btn-outline-secondary" href="<?= e(url('builder/' . $id . '/preview')) ?>"
                           target="_blank" rel="noopener">
                            <i class="bi bi-arrows-fullscreen me-1" aria-hidden="true"></i><?= e(__('builder.full_preview')) ?>
                        </a>
                        <a class="btn btn-primary" href="<?= e(url('builder/' . $id, ['step' => 7])) ?>">
                            <?= e(__('builder.next')) ?> <i class="bi bi-arrow-right ms-1" aria-hidden="true"></i>
                        </a>
                    </div>
                </section>

            <?php else: ?>
                <section class="sk-panel mb-3">
                    <h2 class="h6 mb-2"><?= e(__('builder.your_link')) ?></h2>
                    <form id="sk-slug-form" method="post" action="<?= e(url('builder/' . $id . '/slug')) ?>">
                        <?= csrf_field() ?>
                        <label class="form-label small mb-1" for="slug"><?= e(__('builder.slug')) ?></label>
                        <div class="input-group">
                            <input class="form-control <?= error_for('slug') ? 'is-invalid' : '' ?>" type="text"
                                   id="slug" name="slug" maxlength="120" pattern="[a-z0-9\-]+"
                                   value="<?= e((string) $invitation['slug']) ?>">
                            <button class="btn btn-outline-secondary" type="submit"><?= e(__('common.save')) ?></button>
                            <?php if ($message = error_for('slug')): ?>
                                <div class="invalid-feedback"><?= e($message) ?></div>
                            <?php endif; ?>
                        </div>
                        <p class="form-text mb-0">
                            <code id="sk-slug-preview" data-base="<?= eattr(App\Core\Url::to('invite/')) ?>">
                                <?= e(App\Core\Url::invite((string) $invitation['slug'])) ?>
                            </code>
                        </p>
                    </form>
                </section>

                <section class="sk-panel">
                    <h2 class="h6 mb-2"><?= e(__('builder.step_publish')) ?></h2>
                    <?php if ($missing !== []): ?>
                        <div class="alert alert-warning small">
                            <?= e(__('builder.missing_fields', ['fields' => implode(', ', $missing)])) ?>
                        </div>
                    <?php else: ?>
                        <p class="small text-muted"><?= e(__('builder.publish_hint')) ?></p>
                    <?php endif; ?>

                    <div class="d-flex flex-wrap gap-2">
                        <a class="btn btn-outline-secondary" href="<?= e(url('builder/' . $id, ['step' => 6])) ?>">
                            <i class="bi bi-arrow-left me-1" aria-hidden="true"></i><?= e(__('builder.back')) ?>
                        </a>
                        <?php if ((string) $invitation['status'] === 'published'): ?>
                            <a class="btn btn-primary" href="<?= e(url('builder/' . $id . '/share')) ?>">
                                <i class="bi bi-share me-1" aria-hidden="true"></i><?= e(__('builder.step_share')) ?>
                            </a>
                            <form method="post" action="<?= e(url('builder/' . $id . '/unpublish')) ?>">
                                <?= csrf_field() ?>
                                <button class="btn btn-outline-danger" type="submit"
                                        data-sk-confirm="<?= eattr(__('builder.unpublish_confirm')) ?>">
                                    <?= e(__('builder.unpublish')) ?>
                                </button>
                            </form>
                        <?php else: ?>
                            <form method="post" action="<?= e(url('builder/' . $id . '/publish')) ?>">
                                <?= csrf_field() ?>
                                <button class="btn btn-primary btn-lg" type="submit" data-sk-publish
                                        data-sk-loading="<?= eattr(__('builder.saving')) ?>">
                                    <i class="bi bi-send me-1" aria-hidden="true"></i><?= e(__('builder.publish')) ?>
                                </button>
                            </form>
                        <?php endif; ?>
                    </div>
                </section>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php $view->start('scripts'); ?>
<script defer<?= App\Core\Csp::attribute() ?> src="<?= e(asset('vendor/sortable.min.js')) ?>"></script>
<script defer<?= App\Core\Csp::attribute() ?> src="<?= e(asset('js/builder.js')) ?>"></script>
<script<?= App\Core\Csp::attribute() ?>>
    window.addEventListener('load', function () {
        SK.builder.init(<?= ejs([
            'invitationId' => $id,
            'previewUrl'   => App\Core\Url::to('builder/' . $id . '/preview'),
            'saveUrl'      => App\Core\Url::to('builder/' . $id . '/content'),
            'aiEnabled'    => (bool) $aiEnabled,
        ]) ?>);
    });
</script>
<?php $view->stop(); ?>
