<?php
/**
 * One invitation in the user's list.
 *
 * @var array<string,mixed> $invitation
 */
$i = $invitation;
$status = (string) $i['status'];
$badge = match ($status) {
    'published'   => 'success',
    'draft'       => 'secondary',
    'unpublished' => 'warning',
    default       => 'light',
};
$eventAt = $i['event_at'] ?? null;
?>
<div class="sk-row">
    <div class="sk-row__main">
        <div class="d-flex align-items-center gap-2 flex-wrap">
            <a class="fw-semibold" href="<?= e(url('builder/' . $i['id'])) ?>"><?= e($i['title']) ?></a>
            <span class="badge text-bg-<?= e($badge) ?>"><?= e(__('common.' . ($status === 'published' ? 'published' : 'draft'))) ?></span>
            <?php if ((int) ($i['unread_rsvp'] ?? 0) > 0): ?>
                <span class="badge text-bg-danger"><?= (int) $i['unread_rsvp'] ?> <?= e(__('rsvp.new')) ?></span>
            <?php endif; ?>
        </div>
        <div class="sk-row__meta">
            <?php if ($eventAt !== null): ?>
                <span><i class="bi bi-calendar-event me-1" aria-hidden="true"></i><?= e(date('d M Y', strtotime((string) $eventAt))) ?></span>
            <?php endif; ?>
            <span><i class="bi bi-eye me-1" aria-hidden="true"></i><?= number_format((int) $i['view_count']) ?></span>
            <span><i class="bi bi-share me-1" aria-hidden="true"></i><?= number_format((int) $i['share_count']) ?></span>
            <span><i class="bi bi-clipboard-check me-1" aria-hidden="true"></i><?= number_format((int) $i['rsvp_count']) ?></span>
            <?php if ($status === 'published'): ?>
                <span class="text-truncate"><i class="bi bi-link-45deg me-1" aria-hidden="true"></i><?= e('/invite/' . $i['slug']) ?></span>
            <?php endif; ?>
        </div>
    </div>
    <div class="sk-row__actions">
        <a class="btn btn-sm btn-outline-secondary" href="<?= e(url('builder/' . $i['id'])) ?>"
           title="<?= eattr(__('common.edit')) ?>"
           aria-label="<?= eattr(__('common.edit') . ': ' . $i['title']) ?>"><i class="bi bi-pencil" aria-hidden="true"></i></a>
        <?php if ($status === 'published'): ?>
            <a class="btn btn-sm btn-outline-secondary" href="<?= e(url('invite/' . $i['slug'])) ?>" target="_blank"
               rel="noopener" title="<?= eattr(__('common.view')) ?>"
               aria-label="<?= eattr(__('common.view') . ': ' . $i['title']) ?>"><i class="bi bi-box-arrow-up-right" aria-hidden="true"></i></a>
        <?php else: ?>
            <a class="btn btn-sm btn-outline-secondary" href="<?= e(url('builder/' . $i['id'] . '/preview')) ?>" target="_blank"
               rel="noopener" title="<?= eattr(__('builder.step_preview')) ?>"
               aria-label="<?= eattr(__('builder.step_preview') . ': ' . $i['title']) ?>"><i class="bi bi-eye" aria-hidden="true"></i></a>
        <?php endif; ?>
        <div class="dropdown">
            <button class="btn btn-sm btn-outline-secondary" type="button" data-bs-toggle="dropdown"
                    aria-expanded="false" aria-label="<?= eattr(__('common.actions')) ?>">
                <i class="bi bi-three-dots" aria-hidden="true"></i>
            </button>
            <ul class="dropdown-menu dropdown-menu-end">
                <li><a class="dropdown-item" href="<?= e(url('builder/' . $i['id'] . '/share')) ?>">
                    <i class="bi bi-share me-2"></i><?= e(__('invite.share')) ?></a></li>
                <li><a class="dropdown-item" href="<?= e(url('invitations/' . $i['id'] . '/rsvp')) ?>">
                    <i class="bi bi-clipboard-check me-2"></i><?= e(__('rsvp.title')) ?></a></li>
                <li><a class="dropdown-item" href="<?= e(url('invitations/' . $i['id'] . '/analytics')) ?>">
                    <i class="bi bi-graph-up me-2"></i><?= e(__('analytics.title')) ?></a></li>
                <li><hr class="dropdown-divider"></li>
                <li>
                    <form method="post" action="<?= e(url('builder/' . $i['id'] . '/duplicate')) ?>">
                        <?= csrf_field() ?>
                        <button class="dropdown-item" type="submit"><i class="bi bi-files me-2"></i><?= e(__('common.duplicate')) ?></button>
                    </form>
                </li>
                <li>
                    <form method="post" action="<?= e(url('builder/' . $i['id'])) ?>"
                          data-sk-confirm-form="<?= eattr(__('builder.delete_confirm')) ?>">
                        <?= csrf_field() ?>
                        <input type="hidden" name="_method" value="DELETE">
                        <button class="dropdown-item text-danger" type="submit">
                            <i class="bi bi-trash me-2"></i><?= e(__('common.delete')) ?>
                        </button>
                    </form>
                </li>
            </ul>
        </div>
    </div>
</div>
