<?php
if ($totalPages <= 1) {
    return;
}

$paginationParams = $_GET;
$paginationParams['p'] = 1;

$buildPageUrl = static function (int $targetPage) use ($paginationParams): string {
    $params = $paginationParams;
    $params['p'] = $targetPage;

    return '/?' . http_build_query($params);
};

$startPage = max(1, $currentPage - 2);
$endPage = min($totalPages, $currentPage + 2);
?>

<nav class="pagination" aria-label="Paginación">
    <?php if ($currentPage > 1): ?>
        <a class="pagination-link" href="<?= e($buildPageUrl($currentPage - 1)) ?>">
            ← Anterior
        </a>
    <?php endif; ?>

    <?php if ($startPage > 1): ?>
        <a class="pagination-link" href="<?= e($buildPageUrl(1)) ?>">1</a>
        <?php if ($startPage > 2): ?>
            <span class="pagination-ellipsis">…</span>
        <?php endif; ?>
    <?php endif; ?>

    <?php for ($number = $startPage; $number <= $endPage; $number++): ?>
        <?php if ($number === $currentPage): ?>
            <span class="pagination-link active" aria-current="page">
                <?= $number ?>
            </span>
        <?php else: ?>
            <a class="pagination-link" href="<?= e($buildPageUrl($number)) ?>">
                <?= $number ?>
            </a>
        <?php endif; ?>
    <?php endfor; ?>

    <?php if ($endPage < $totalPages): ?>
        <?php if ($endPage < $totalPages - 1): ?>
            <span class="pagination-ellipsis">…</span>
        <?php endif; ?>
        <a class="pagination-link" href="<?= e($buildPageUrl($totalPages)) ?>">
            <?= $totalPages ?>
        </a>
    <?php endif; ?>

    <?php if ($currentPage < $totalPages): ?>
        <a class="pagination-link" href="<?= e($buildPageUrl($currentPage + 1)) ?>">
            Siguiente →
        </a>
    <?php endif; ?>
</nav>