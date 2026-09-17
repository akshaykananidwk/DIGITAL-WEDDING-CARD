<?php
/** @var array<string,array<string,mixed>> $flags */
$view->extend('layouts.admin');
?>
<p class="text-muted small">
    Flags switch features on and off without a deploy. A rollout below 100% enables the feature for a stable
    subset of users (the same user always gets the same answer), which is how a new feature is tried on a few
    accounts first.
</p>

<div class="sk-panel">
    <div class="sk-table-wrap">
        <table class="table align-middle mb-0">
            <thead>
            <tr>
                <th scope="col">Feature</th>
                <th scope="col" class="d-none d-md-table-cell">Key</th>
                <th scope="col">Rollout</th>
                <th scope="col">State</th>
                <th scope="col"><span class="visually-hidden">Actions</span></th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($flags as $key => $flag): ?>
                <tr>
                    <td>
                        <span class="fw-semibold"><?= e((string) $flag['name']) ?></span>
                        <?php if (($description = (string) ($flag['description'] ?? '')) !== ''): ?>
                            <span class="d-block small text-muted"><?= e($description) ?></span>
                        <?php endif; ?>
                    </td>
                    <td class="d-none d-md-table-cell"><code class="sk-code"><?= e((string) $key) ?></code></td>
                    <td>
                        <form class="d-flex gap-1 align-items-center"
                              method="post" action="<?= e(url('admin/flags/' . $key)) ?>">
                            <?= csrf_field() ?>
                            <input type="hidden" name="enabled" value="<?= (int) $flag['is_enabled'] ?>">
                            <input class="form-control form-control-sm" style="max-width:5.5rem" type="number"
                                   name="rollout" min="0" max="100" value="<?= (int) $flag['rollout'] ?>"
                                   aria-label="Rollout percentage for <?= eattr((string) $flag['name']) ?>">
                            <span class="small text-muted">%</span>
                            <button class="btn btn-sm btn-outline-secondary" type="submit">Save</button>
                        </form>
                    </td>
                    <td>
                        <?php if ((int) $flag['is_enabled'] === 1): ?>
                            <span class="badge text-bg-success">On</span>
                        <?php else: ?>
                            <span class="badge text-bg-secondary">Off</span>
                        <?php endif; ?>
                    </td>
                    <td class="text-end">
                        <form method="post" action="<?= e(url('admin/flags/' . $key)) ?>">
                            <?= csrf_field() ?>
                            <input type="hidden" name="enabled" value="<?= (int) $flag['is_enabled'] === 1 ? '0' : '1' ?>">
                            <input type="hidden" name="rollout" value="<?= (int) $flag['rollout'] ?>">
                            <button class="btn btn-sm btn-outline-<?= (int) $flag['is_enabled'] === 1 ? 'danger' : 'primary' ?>"
                                    type="submit">
                                <?= (int) $flag['is_enabled'] === 1 ? 'Turn off' : 'Turn on' ?>
                            </button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if ($flags === []): ?>
                <tr><td colspan="5" class="text-muted small">No flags are defined.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
