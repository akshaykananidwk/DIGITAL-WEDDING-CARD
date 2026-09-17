<?php
/**
 * One template card for the gallery grids.
 *
 * @var array<string,mixed> $template
 * @var bool|null $selectable  render a "Use this" action instead of a link
 */
$t = $template;
$tags = is_array($t['tags'] ?? null) ? $t['tags'] : [];
$thumb = (string) ($t['thumbnail'] ?? '');
$href = url('templates/' . $t['slug']);
?>
<article class="sk-card h-100">
    <a class="sk-card__media" href="<?= e($href) ?>"
       style="--sk-card-a:<?= eattr($t['color_primary'] ?? '#C8102E') ?>;--sk-card-b:<?= eattr($t['color_secondary'] ?? '#F0A02A') ?>">
        <?php if ($thumb !== ''): ?>
            <img src="<?= e(url($thumb)) ?>" alt="<?= eattr($t['name']) ?>" loading="lazy" decoding="async" width="400" height="560">
        <?php else: ?>
            <span class="sk-card__initial" aria-hidden="true"><?= e(mb_substr((string) $t['name'], 0, 1)) ?></span>
        <?php endif; ?>
        <span class="sk-card__badges">
            <?php if ((int) ($t['is_premium'] ?? 0) === 1): ?>
                <span class="badge text-bg-warning"><i class="bi bi-star-fill"></i> <?= e(__('templates.premium')) ?></span>
            <?php endif; ?>
            <?php if ((int) ($t['has_animation'] ?? 0) === 1): ?>
                <span class="badge text-bg-dark"><i class="bi bi-magic"></i> <?= e(__('templates.animated')) ?></span>
            <?php endif; ?>
            <?php if ((int) ($t['page_count'] ?? 1) > 1): ?>
                <span class="badge text-bg-secondary"><?= (int) $t['page_count'] ?> <?= e(__('templates.pages')) ?></span>
            <?php endif; ?>
        </span>
    </a>
    <div class="sk-card__body">
        <h3 class="sk-card__title"><a href="<?= e($href) ?>"><?= e($t['name']) ?></a></h3>
        <p class="sk-card__meta">
            <span class="text-uppercase"><?= e(strtoupper((string) $t['language'])) ?></span>
            <span aria-hidden="true">·</span>
            <span><?= e(str_replace('_', ' ', (string) $t['type'])) ?></span>
            <?php if ((int) ($t['use_count'] ?? 0) > 0): ?>
                <span aria-hidden="true">·</span>
                <span><?= number_format((int) $t['use_count']) ?> <?= e(__('templates.uses')) ?></span>
            <?php endif; ?>
        </p>
        <?php if ($tags !== []): ?>
            <p class="sk-card__tags">
                <?php foreach (array_slice($tags, 0, 3) as $tag): ?>
                    <a class="sk-chip sk-chip--sm" href="<?= e(url('templates', ['tag' => (string) $tag])) ?>"><?= e($tag) ?></a>
                <?php endforeach; ?>
            </p>
        <?php endif; ?>
        <div class="sk-card__actions">
            <?php if (!empty($selectable) && !App\Core\Auth::check()): ?>
                <a class="btn btn-primary btn-sm flex-grow-1" href="<?= e(url('login')) ?>"><?= e(__('templates.use')) ?></a>
            <?php elseif (!empty($selectable)): ?>
                <form method="post" action="<?= e(url('create/start')) ?>" class="d-grid flex-grow-1">
                    <?= csrf_field() ?>
                    <input type="hidden" name="template_id" value="<?= (int) $t['id'] ?>">
                    <button class="btn btn-primary btn-sm" type="submit" data-sk-loading="<?= eattr(__('common.loading')) ?>">
                        <?= e(__('templates.use')) ?>
                    </button>
                </form>
            <?php else: ?>
                <a class="btn btn-primary btn-sm flex-grow-1" href="<?= e($href) ?>"><?= e(__('templates.details')) ?></a>
            <?php endif; ?>
            <a class="btn btn-outline-secondary btn-sm" href="<?= e(url('templates/' . $t['slug'] . '/preview')) ?>"
               target="_blank" rel="noopener" title="<?= eattr(__('templates.preview')) ?>"
               aria-label="<?= eattr(__('templates.preview') . ': ' . $t['name']) ?>">
                <i class="bi bi-eye" aria-hidden="true"></i>
            </a>
        </div>
    </div>
</article>
