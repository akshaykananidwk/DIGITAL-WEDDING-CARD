<?php
/**
 * @var array<string,mixed> $invitation
 * @var array<int,array<string,mixed>> $responses
 * @var array<string,mixed> $pagination
 * @var array{total:int,yes:int,maybe:int,no:int,guests:int} $summary
 * @var array{response:string,q:string} $filters
 */
$view->extend('layouts.app');
$tabs = [
    ''      => __('common.all'),
    'yes'   => __('rsvp.attending'),
    'maybe' => __('rsvp.maybe'),
    'no'    => __('rsvp.declined'),
];
?>
<div class="container py-4">
    <nav aria-label="Breadcrumb" class="small mb-2">
        <a href="<?= e(url('invitations')) ?>"><?= e(__('nav.invitations')) ?></a>
        <span aria-hidden="true">/</span>
        <a href="<?= e(url('builder/' . $invitation['id'])) ?>"><?= e($invitation['title']) ?></a>
    </nav>

    <div class="d-flex flex-wrap align-items-end justify-content-between gap-2 mb-3">
        <h1 class="h4 mb-0"><?= e(__('rsvp.title')) ?></h1>
        <div class="d-flex gap-2">
            <a class="btn btn-sm btn-outline-secondary" href="<?= e(url('invitations/' . $invitation['id'] . '/rsvp/export')) ?>">
                <i class="bi bi-download me-1" aria-hidden="true"></i><?= e(__('rsvp.export')) ?>
            </a>
            <form method="post" action="<?= e(url('invitations/' . $invitation['id'] . '/rsvp/read')) ?>">
                <?= csrf_field() ?>
                <button class="btn btn-sm btn-outline-secondary" type="submit"><?= e(__('rsvp.mark_read')) ?></button>
            </form>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-6 col-lg-3"><?php $view->include('partials.stat-card', [
            'icon' => 'people', 'label' => __('rsvp.attending'), 'value' => number_format($summary['yes'])]); ?></div>
        <div class="col-6 col-lg-3"><?php $view->include('partials.stat-card', [
            'icon' => 'question-circle', 'label' => __('rsvp.maybe'), 'value' => number_format($summary['maybe'])]); ?></div>
        <div class="col-6 col-lg-3"><?php $view->include('partials.stat-card', [
            'icon' => 'x-circle', 'label' => __('rsvp.declined'), 'value' => number_format($summary['no'])]); ?></div>
        <div class="col-6 col-lg-3"><?php $view->include('partials.stat-card', [
            'icon' => 'person-check', 'label' => __('rsvp.guests'), 'value' => number_format($summary['guests'])]); ?></div>
    </div>

    <form class="row g-2 align-items-end mb-3" method="get"
          action="<?= e(url('invitations/' . $invitation['id'] . '/rsvp')) ?>" role="search">
        <div class="col-12 col-sm">
            <label class="form-label small mb-1" for="q"><?= e(__('common.search')) ?></label>
            <input class="form-control form-control-sm" type="search" id="q" name="q"
                   maxlength="80" value="<?= e((string) $filters['q']) ?>">
        </div>
        <div class="col-6 col-sm-auto">
            <label class="form-label small mb-1" for="response"><?= e(__('common.status')) ?></label>
            <select class="form-select form-select-sm" id="response" name="response" data-sk-auto-submit>
                <?php foreach ($tabs as $value => $label): ?>
                    <option value="<?= e((string) $value) ?>" <?= $filters['response'] === $value ? 'selected' : '' ?>>
                        <?= e($label) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-6 col-sm-auto">
            <button class="btn btn-sm btn-outline-secondary w-100" type="submit"><?= e(__('common.search')) ?></button>
        </div>
    </form>

    <?php if ($responses === []): ?>
        <?php $view->include('partials.empty-state', ['icon' => 'clipboard', 'title' => __('rsvp.empty')]); ?>
    <?php else: ?>
        <div class="sk-panel p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                    <tr>
                        <th scope="col"><?= e(__('invite.rsvp_name')) ?></th>
                        <th scope="col"><?= e(__('common.status')) ?></th>
                        <th scope="col" class="text-end"><?= e(__('invite.rsvp_guests')) ?></th>
                        <th scope="col" class="d-none d-md-table-cell"><?= e(__('invite.rsvp_message')) ?></th>
                        <th scope="col" class="d-none d-sm-table-cell"><?= e(__('common.created')) ?></th>
                        <th scope="col"><span class="visually-hidden"><?= e(__('common.actions')) ?></span></th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($responses as $row): ?>
                        <?php
                        $badge = match ((string) $row['response']) {
                            'yes' => 'success', 'maybe' => 'warning', default => 'secondary',
                        };
                        ?>
                        <tr class="<?= $row['read_at'] === null ? 'fw-semibold' : '' ?>">
                            <td>
                                <?= e($row['name']) ?>
                                <?php if (($phone = (string) ($row['phone'] ?? '')) !== ''): ?>
                                    <a class="d-block small text-muted"
                                       href="https://wa.me/<?= e(App\Core\Str::whatsappNumber($phone)) ?>" rel="noopener">
                                        <i class="bi bi-whatsapp me-1"></i><?= e($phone) ?>
                                    </a>
                                <?php endif; ?>
                            </td>
                            <td><span class="badge text-bg-<?= e($badge) ?>"><?= e($tabs[(string) $row['response']] ?? $row['response']) ?></span></td>
                            <td class="text-end"><?= (int) $row['guests'] ?></td>
                            <td class="d-none d-md-table-cell small text-muted">
                                <?= e(mb_strimwidth((string) ($row['message'] ?? ''), 0, 90, '…')) ?>
                            </td>
                            <td class="d-none d-sm-table-cell small text-muted">
                                <?= e(date('d M, H:i', strtotime((string) $row['created_at']))) ?>
                            </td>
                            <td class="text-end">
                                <form method="post"
                                      action="<?= e(url('invitations/' . $invitation['id'] . '/rsvp/' . $row['id'])) ?>"
                                      data-sk-confirm-form="<?= eattr(__('common.confirm')) ?>">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="_method" value="DELETE">
                                    <button class="btn btn-sm btn-outline-danger" type="submit"
                                            aria-label="<?= eattr(__('common.delete')) ?>">
                                        <i class="bi bi-trash" aria-hidden="true"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php $view->include('partials.pagination', ['pagination' => $pagination]); ?>
    <?php endif; ?>

    <p class="text-muted small mt-3 mb-0"><i class="bi bi-shield-check me-1" aria-hidden="true"></i><?= e(__('analytics.privacy_note')) ?></p>
</div>
