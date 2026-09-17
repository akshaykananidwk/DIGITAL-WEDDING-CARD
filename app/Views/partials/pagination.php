<?php
/**
 * @var array{page:int,pages:int,total:int,prev:?string,next:?string,build:callable} $pagination
 */
if (($pagination['pages'] ?? 1) <= 1) {
    return;
}
$build = $pagination['build'];
$page = (int) $pagination['page'];
$pages = (int) $pagination['pages'];
$window = 2;
$from = max(1, $page - $window);
$to = min($pages, $page + $window);
?>
<nav class="d-flex justify-content-center mt-4" aria-label="Pagination">
    <ul class="pagination mb-0 flex-wrap">
        <li class="page-item <?= $pagination['prev'] === null ? 'disabled' : '' ?>">
            <a class="page-link" href="<?= e((string) ($pagination['prev'] ?? '#')) ?>" rel="prev" aria-label="Previous">
                <i class="bi bi-chevron-left" aria-hidden="true"></i>
            </a>
        </li>
        <?php if ($from > 1): ?>
            <li class="page-item"><a class="page-link" href="<?= e($build(1)) ?>">1</a></li>
            <?php if ($from > 2): ?><li class="page-item disabled"><span class="page-link">…</span></li><?php endif; ?>
        <?php endif; ?>
        <?php for ($i = $from; $i <= $to; $i++): ?>
            <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                <?php if ($i === $page): ?>
                    <span class="page-link" aria-current="page"><?= $i ?></span>
                <?php else: ?>
                    <a class="page-link" href="<?= e($build($i)) ?>"><?= $i ?></a>
                <?php endif; ?>
            </li>
        <?php endfor; ?>
        <?php if ($to < $pages): ?>
            <?php if ($to < $pages - 1): ?><li class="page-item disabled"><span class="page-link">…</span></li><?php endif; ?>
            <li class="page-item"><a class="page-link" href="<?= e($build($pages)) ?>"><?= $pages ?></a></li>
        <?php endif; ?>
        <li class="page-item <?= $pagination['next'] === null ? 'disabled' : '' ?>">
            <a class="page-link" href="<?= e((string) ($pagination['next'] ?? '#')) ?>" rel="next" aria-label="Next">
                <i class="bi bi-chevron-right" aria-hidden="true"></i>
            </a>
        </li>
    </ul>
</nav>
