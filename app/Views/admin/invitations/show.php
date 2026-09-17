<?php
/**
 * @var array<string,mixed> $invitation
 * @var array<string,mixed> $content
 * @var array{total:int,yes:int,maybe:int,no:int,guests:int} $rsvp
 * @var array<string,mixed> $report
 * @var array<string,mixed>|null $owner
 * @var array<string,mixed>|null $template
 */
$view->extend('layouts.admin');
$totals = $report['totals'];
$series = $report['series'];
?>
<nav class="small mb-3">
    <a href="<?= e(url('admin/invitations')) ?>">Invitations</a>
    <span aria-hidden="true">/</span>
    <span class="text-muted"><?= e($invitation['title']) ?></span>
</nav>

<div class="row g-4">
    <div class="col-12 col-xl-8">
        <section class="sk-panel mb-4">
            <h2 class="h6 mb-3">Activity · 30 days</h2>
            <div class="sk-chart">
                <canvas height="220" data-sk-chart='<?= eattr(json_encode([
                    'type' => 'line',
                    'labels' => $series['labels'],
                    'datasets' => [
                        ['label' => 'Views', 'data' => $series['views']],
                        ['label' => 'Unique', 'data' => $series['unique_views']],
                        ['label' => 'Shares', 'data' => $series['shares']],
                    ],
                ])) ?>'></canvas>
            </div>
        </section>

        <section class="sk-panel">
            <h2 class="h6 mb-3">Stored content</h2>
            <p class="form-text mt-0">
                What the owner typed, exactly as it is stored. Shown read-only: an administrator can unpublish or
                delete an invitation, but never rewrite someone's wording.
            </p>
            <div class="sk-table-wrap">
                <table class="table table-sm align-middle mb-0">
                    <thead><tr><th>Field</th><th>Value</th></tr></thead>
                    <tbody>
                    <?php foreach ($content as $key => $value): ?>
                        <tr>
                            <td class="text-nowrap"><code class="sk-code"><?= e((string) $key) ?></code></td>
                            <td class="small"><?= nl2br(e(is_array($value) ? implode(', ', array_map('strval', $value)) : (string) $value)) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if ($content === []): ?>
                        <tr><td colspan="2" class="text-muted small">Nothing filled in yet.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </div>

    <div class="col-12 col-xl-4">
        <section class="sk-panel mb-4">
            <h2 class="h6 mb-3">Details</h2>
            <dl class="row small mb-3">
                <dt class="col-5 text-muted">Status</dt>
                <dd class="col-7"><?= e((string) $invitation['status']) ?></dd>
                <dt class="col-5 text-muted">Owner</dt>
                <dd class="col-7">
                    <?php if ($owner !== null): ?>
                        <a href="<?= e(url('admin/users/' . $owner['id'] . '/edit')) ?>"><?= e($owner['name']) ?></a>
                        <span class="d-block text-muted"><?= e($owner['email']) ?></span>
                    <?php else: ?>
                        <span class="text-muted">Removed</span>
                    <?php endif; ?>
                </dd>
                <dt class="col-5 text-muted">Template</dt>
                <dd class="col-7">
                    <?php if ($template !== null): ?>
                        <a href="<?= e(url('admin/templates/' . $template['id'] . '/edit')) ?>"><?= e($template['name']) ?></a>
                    <?php else: ?>
                        <span class="text-muted">Removed</span>
                    <?php endif; ?>
                </dd>
                <dt class="col-5 text-muted">Link</dt>
                <dd class="col-7"><code class="sk-code"><?= e('/invite/' . $invitation['slug']) ?></code></dd>
                <dt class="col-5 text-muted">Event</dt>
                <dd class="col-7">
                    <?= $invitation['event_at'] === null ? '—'
                        : e(date('d M Y, H:i', strtotime((string) $invitation['event_at']))) ?>
                </dd>
                <dt class="col-5 text-muted">Created</dt>
                <dd class="col-7"><?= e(date('d M Y', strtotime((string) $invitation['created_at']))) ?></dd>
            </dl>

            <div class="d-flex flex-wrap gap-2">
                <?php if ((string) $invitation['status'] === 'published'): ?>
                    <a class="btn btn-sm btn-outline-secondary" target="_blank" rel="noopener"
                       href="<?= e(url('invite/' . $invitation['slug'])) ?>">Open card</a>
                <?php endif; ?>
                <form method="post" action="<?= e(url('admin/invitations/' . $invitation['id'] . '/status')) ?>">
                    <?= csrf_field() ?>
                    <input type="hidden" name="status"
                           value="<?= (string) $invitation['status'] === 'published' ? 'unpublished' : 'published' ?>">
                    <button class="btn btn-sm btn-outline-secondary" type="submit"
                            data-sk-confirm="Change the status of this invitation?">
                        <?= (string) $invitation['status'] === 'published' ? 'Unpublish' : 'Publish' ?>
                    </button>
                </form>
                <form method="post" action="<?= e(url('admin/invitations/' . $invitation['id'])) ?>">
                    <?= csrf_field() ?>
                    <input type="hidden" name="_method" value="DELETE">
                    <button class="btn btn-sm btn-outline-danger" type="submit"
                            data-sk-confirm="Delete this invitation and its photos?">Delete</button>
                </form>
            </div>
        </section>

        <div class="row g-3 mb-3">
            <div class="col-6"><?php $view->include('partials.stat-card', [
                'icon' => 'eye', 'label' => 'Views · 30d', 'value' => number_format((int) $totals['views'])]); ?></div>
            <div class="col-6"><?php $view->include('partials.stat-card', [
                'icon' => 'share', 'label' => 'Shares · 30d', 'value' => number_format((int) $totals['shares'])]); ?></div>
        </div>

        <section class="sk-panel">
            <h2 class="h6 mb-3">RSVP</h2>
            <dl class="row small mb-0">
                <dt class="col-7 text-muted">Attending</dt><dd class="col-5 text-end mb-1"><?= number_format($rsvp['yes']) ?></dd>
                <dt class="col-7 text-muted">Maybe</dt><dd class="col-5 text-end mb-1"><?= number_format($rsvp['maybe']) ?></dd>
                <dt class="col-7 text-muted">Declined</dt><dd class="col-5 text-end mb-1"><?= number_format($rsvp['no']) ?></dd>
                <dt class="col-7 text-muted">Guests</dt><dd class="col-5 text-end mb-0"><?= number_format($rsvp['guests']) ?></dd>
            </dl>
        </section>
    </div>
</div>

<?php $view->start('scripts'); ?>
<script defer<?= App\Core\Csp::attribute() ?> src="<?= e(asset('vendor/chart.umd.min.js')) ?>"></script>
<script defer<?= App\Core\Csp::attribute() ?> src="<?= e(asset('js/charts.js')) ?>"></script>
<?php $view->stop(); ?>
