<?php
/**
 * @var array<string,mixed> $settings
 * @var array{total:int,success:int,errors:int,tokens:int} $stats
 * @var int $usage
 * @var array<string,string> $models
 * @var bool $configured
 */
$view->extend('layouts.admin');
$dailyLimit = (int) ($settings['daily_limit'] ?? 100);
?>
<div class="row g-4">
    <div class="col-12 col-xl-8">
        <form class="sk-panel mb-4" method="post" action="<?= e(url('admin/ai')) ?>">
            <?= csrf_field() ?>
            <h2 class="h6 mb-3">Google Gemini</h2>

            <div class="alert alert-secondary small">
                The generator only rewrites facts a user has already typed. It is instructed never to invent
                names, dates, venues or numbers, and any sentence containing a number the user did not supply is
                dropped before the text is shown.
            </div>

            <div class="row g-3">
                <div class="col-12 col-sm-8">
                    <label class="form-label" for="api_key">API key</label>
                    <input class="form-control <?= error_for('api_key') ? 'is-invalid' : '' ?>" type="password"
                           id="api_key" name="api_key" autocomplete="off"
                           placeholder="<?= $settings['has_api_key'] ? eattr((string) $settings['api_key_masked']) : 'Not set' ?>">
                    <?php if ($m = error_for('api_key')): ?><div class="invalid-feedback"><?= e($m) ?></div><?php endif; ?>
                    <div class="form-text">
                        Encrypted with the application key before it is stored. It is never shown in full, never
                        sent to the browser and never written to a log.
                    </div>
                </div>
                <div class="col-12 col-sm-4">
                    <label class="form-label" for="model">Model</label>
                    <select class="form-select" id="model" name="model" required>
                        <?php foreach ($models as $key => $label): ?>
                            <option value="<?= e((string) $key) ?>"
                                <?= (string) ($settings['model'] ?? '') === (string) $key ? 'selected' : '' ?>>
                                <?= e((string) $label) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-12">
                    <label class="form-label" for="endpoint">Endpoint</label>
                    <input class="form-control" type="url" id="endpoint" name="endpoint"
                           value="<?= e((string) ($settings['endpoint'] ?? '')) ?>">
                </div>
                <div class="col-6 col-sm-3">
                    <label class="form-label" for="temperature">Temperature</label>
                    <input class="form-control" type="number" id="temperature" name="temperature"
                           step="0.05" min="0" max="2" value="<?= e((string) ($settings['temperature'] ?? '0.7')) ?>" required>
                </div>
                <div class="col-6 col-sm-3">
                    <label class="form-label" for="top_p">Top-p</label>
                    <input class="form-control" type="number" id="top_p" name="top_p"
                           step="0.05" min="0" max="1" value="<?= e((string) ($settings['top_p'] ?? '0.95')) ?>" required>
                </div>
                <div class="col-6 col-sm-3">
                    <label class="form-label" for="max_tokens">Max tokens</label>
                    <input class="form-control" type="number" id="max_tokens" name="max_tokens"
                           min="64" max="8192" value="<?= (int) ($settings['max_tokens'] ?? 2048) ?>" required>
                </div>
                <div class="col-6 col-sm-3">
                    <label class="form-label" for="timeout">Timeout (s)</label>
                    <input class="form-control" type="number" id="timeout" name="timeout"
                           min="5" max="120" value="<?= (int) ($settings['timeout'] ?? 30) ?>" required>
                </div>
                <div class="col-12 col-sm-4">
                    <label class="form-label" for="daily_limit">Daily request limit</label>
                    <input class="form-control" type="number" id="daily_limit" name="daily_limit"
                           min="0" max="100000" value="<?= $dailyLimit ?>" required>
                    <div class="form-text">0 removes the cap. Protects your quota from a runaway loop.</div>
                </div>
                <div class="col-12 col-sm-8">
                    <label class="form-label" for="system_prompt">Extra system instructions</label>
                    <textarea class="form-control" id="system_prompt" name="system_prompt" rows="3"
                              maxlength="4000"><?= e((string) ($settings['system_prompt'] ?? '')) ?></textarea>
                </div>
                <div class="col-12">
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" value="1" id="is_enabled" name="is_enabled"
                            <?= (int) ($settings['is_enabled'] ?? 0) === 1 ? 'checked' : '' ?>>
                        <label class="form-check-label" for="is_enabled">Offer AI wording to users</label>
                    </div>
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" value="1" id="allow_recommendations"
                               name="allow_recommendations"
                            <?= (int) ($settings['allow_recommendations'] ?? 1) === 1 ? 'checked' : '' ?>>
                        <label class="form-check-label" for="allow_recommendations">
                            Let the model reorder template recommendations
                        </label>
                    </div>
                </div>
            </div>

            <div class="d-flex flex-wrap gap-2 mt-4">
                <button class="btn btn-primary" type="submit">Save AI settings</button>
            </div>
        </form>

        <section class="sk-panel">
            <h2 class="h6 mb-2">Connection</h2>
            <p class="small text-muted mb-3">
                Status: <?= $configured
                    ? '<span class="badge text-bg-success">Configured</span>'
                    : '<span class="badge text-bg-secondary">Not configured</span>' ?>
            </p>
            <div class="d-flex flex-wrap gap-2">
                <form method="post" action="<?= e(url('admin/ai/test')) ?>">
                    <?= csrf_field() ?>
                    <button class="btn btn-outline-primary" type="submit" data-sk-loading="Testing…">
                        Test connection
                    </button>
                </form>
                <?php if ($settings['has_api_key']): ?>
                    <form method="post" action="<?= e(url('admin/ai/clear-key')) ?>">
                        <?= csrf_field() ?>
                        <button class="btn btn-outline-danger" type="submit"
                                data-sk-confirm="Remove the stored API key and switch AI features off?">
                            Remove API key
                        </button>
                    </form>
                <?php endif; ?>
                <a class="btn btn-outline-secondary" href="<?= e(url('admin/ai/logs')) ?>">Usage log</a>
            </div>
        </section>
    </div>

    <div class="col-12 col-xl-4">
        <div class="row g-3 mb-3">
            <div class="col-6"><?php $view->include('partials.stat-card', [
                'icon' => 'stars', 'label' => 'Calls · 30 days', 'value' => number_format($stats['total'])]); ?></div>
            <div class="col-6"><?php $view->include('partials.stat-card', [
                'icon' => 'check2-circle', 'label' => 'Successful', 'value' => number_format($stats['success'])]); ?></div>
            <div class="col-6"><?php $view->include('partials.stat-card', [
                'icon' => 'exclamation-triangle', 'label' => 'Errors', 'value' => number_format($stats['errors'])]); ?></div>
            <div class="col-6"><?php $view->include('partials.stat-card', [
                'icon' => 'hash', 'label' => 'Tokens', 'value' => number_format($stats['tokens'])]); ?></div>
        </div>

        <section class="sk-panel">
            <h2 class="h6 mb-2">Today</h2>
            <p class="mb-1"><?= number_format($usage) ?><?= $dailyLimit > 0 ? ' / ' . number_format($dailyLimit) : '' ?> requests</p>
            <?php if ($dailyLimit > 0): ?>
                <?php $percent = min(100, (int) round(($usage / max(1, $dailyLimit)) * 100)); ?>
                <div class="progress" role="progressbar" aria-valuenow="<?= $percent ?>"
                     aria-valuemin="0" aria-valuemax="100" aria-label="Daily AI quota used">
                    <div class="progress-bar<?= $percent >= 90 ? ' bg-danger' : '' ?>" style="width: <?= $percent ?>%"></div>
                </div>
            <?php endif; ?>
            <p class="form-text mb-0">
                Requests are counted per day in Asia/Kolkata. Users see a clear message when the cap is reached,
                and every other feature keeps working.
            </p>
        </section>
    </div>
</div>
