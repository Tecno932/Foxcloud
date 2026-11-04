<?php if ($contentDetail): ?>
    <?php
    $detailImage = trim((string)($contentDetail['image_url'] ?? ''));
    $detailImageValid = $detailImage !== '' &&
        (str_starts_with($detailImage, '/') || str_starts_with($detailImage, 'https://'));
    $downloadUrl = trim((string)($contentDetail['download_url'] ?? ''));
    $downloadParts = $downloadUrl !== '' ? parse_url($downloadUrl) : false;
    $downloadValid = filter_var($downloadUrl, FILTER_VALIDATE_URL) &&
        is_array($downloadParts) &&
        strtolower((string)($downloadParts['scheme'] ?? '')) === 'https';
    $versions = json_decode((string)($contentDetail['game_versions'] ?? '[]'), true);
    if (!is_array($versions)) {
        $versions = [];
    }
    ?>
    <section class="page-banner content-detail">
        <span class="eyebrow"><?= e((string)$contentDetail['category_name']) ?></span>
        <h1><?= e((string)$contentDetail['title']) ?></h1>
        <p><?= e((string)$contentDetail['description']) ?></p>

        <?php if ($detailImageValid): ?>
            <img
                src="<?= e($detailImage) ?>"
                alt="<?= e((string)$contentDetail['title']) ?>"
                loading="lazy"
                class="content-detail-image"
            >
        <?php endif; ?>

        <div class="content-meta">
            <span>Plataforma: <?= e(ucfirst((string)$contentDetail['platform'])) ?></span>
            <span>↓ <?= number_format((int)$contentDetail['downloads_count']) ?> descargas</span>
            <span>♥ <?= number_format((int)$contentDetail['likes_count']) ?> me gusta</span>
        </div>

        <?php if (!empty($_SESSION['user_id'])): ?>
            <form method="post" action="/?page=likes" class="inline-form">
                <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token']) ?>">
                <input type="hidden" name="content_id" value="<?= (int)$contentDetail['id'] ?>">
                <input type="hidden" name="like_action" value="<?= $isLiked ? 'remove' : 'add' ?>">
                <input type="hidden" name="return_to" value="<?= e('/' . rawurlencode((string)$contentDetail['category_slug']) . '/' . (int)$contentDetail['id'] . '/' . rawurlencode((string)$contentDetail['slug'])) ?>">
                <button class="button button-quiet" type="submit">
                    <?= $isLiked ? '♥ Ya te gusta' : '♡ Me gusta' ?>
                </button>
            </form>

            <form method="post" action="/?page=favorites" class="inline-form">
                <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token']) ?>">
                <input type="hidden" name="content_id" value="<?= (int)$contentDetail['id'] ?>">
                <input type="hidden" name="favorite_action" value="<?= $isFavorite ? 'remove' : 'add' ?>">
                <input type="hidden" name="return_to" value="<?= e('/' . rawurlencode((string)$contentDetail['category_slug']) . '/' . (int)$contentDetail['id'] . '/' . rawurlencode((string)$contentDetail['slug'])) ?>">
                <button class="button button-quiet" type="submit">
                    <?= $isFavorite ? '♥ Quitar de favoritos' : '♡ Agregar a favoritos' ?>
                </button>
            </form>
        <?php else: ?>
            <p><a href="/?page=login">Iniciá sesión para guardar esta publicación en favoritos.</a></p>
        <?php endif; ?>

        <?php if ($versions): ?>
            <h2>Versiones compatibles</h2>
            <ul>
                <?php foreach ($versions as $version): ?>
                    <?php if (is_scalar($version)): ?>
                        <li><?= e((string)$version) ?></li>
                    <?php endif; ?>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>

        <?php if ($downloadValid): ?>
            <?php require '/var/www/foxcraft/app/Views/Downloads/button.php'; ?>
        <?php else: ?>
            <p>La descarga todavía no está disponible.</p>
        <?php endif; ?>

        <p><a href="/?page=<?= e((string)$contentDetail['category_slug']) ?>">← Volver a <?= e((string)$contentDetail['category_name']) ?></a></p>
    </section>
<?php else: ?>
    <section class="page-banner">
        <h1>Contenido no encontrado</h1>
        <p>Puede que el contenido no exista o ya no esté publicado.</p>
        <a class="button button-primary" href="/">Volver al inicio</a>
    </section>
<?php endif; ?>
