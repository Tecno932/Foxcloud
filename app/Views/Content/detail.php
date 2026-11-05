
<?php if ($contentDetail): ?>
    <?php
    $detailImage = image_display_url(trim((string)($contentDetail['image_url'] ?? '')));
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

    $detailReturnUrl = '/' .
        rawurlencode((string)$contentDetail['category_slug']) . '/' .
        (int)$contentDetail['id'] . '/' .
        rawurlencode((string)$contentDetail['slug']);
    ?>

    <main class="content-detail-layout">

        <section class="content-detail-main">

            <?php if ($detailImageValid): ?>
                <div class="content-detail-media">
                    <img
                        src="<?= e($detailImage) ?>"
                        alt=""
                        aria-hidden="true"
                        class="content-detail-image-background"
                    >

                    <img
                        src="<?= e($detailImage) ?>"
                        alt="<?= e((string)$contentDetail['title']) ?>"
                        class="content-detail-image"
                    >
                </div>
            <?php endif; ?>

            <div class="content-detail-heading">
                <h1><?= e((string)$contentDetail['title']) ?></h1>
            </div>

            <section class="content-detail-description">
                <p><?= nl2br(e((string)$contentDetail['description'])) ?></p>
            </section>

            <a
                class="content-detail-back"
                href="/?page=<?= e((string)$contentDetail['category_slug']) ?>"
            >
                ← Volver a <?= e((string)$contentDetail['category_name']) ?>
            </a>

        </section>

        <aside class="content-detail-sidebar">

            <div class="content-detail-actions">

                <?php if ($downloadValid): ?>
                    <?php require APP_ROOT . '/Views/Downloads/button.php'; ?>
                <?php else: ?>
                    <span class="content-detail-download-disabled">
                        Descarga no disponible
                    </span>
                <?php endif; ?>

                <?php if (!empty($_SESSION['user_id'])): ?>

                    <form method="post" action="/?page=likes" class="inline-form detail-action-form">
                        <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token']) ?>">
                        <input type="hidden" name="content_id" value="<?= (int)$contentDetail['id'] ?>">
                        <input type="hidden" name="like_action" value="<?= $isLiked ? 'remove' : 'add' ?>">
                        <input type="hidden" name="return_to" value="<?= e($detailReturnUrl) ?>">

                        <button
                            class="button button-quiet detail-icon-button <?= $isLiked ? 'is-active' : '' ?>"
                            type="submit"
                            aria-label="<?= $isLiked ? 'Quitar me gusta' : 'Me gusta' ?>"
                            title="<?= $isLiked ? 'Quitar me gusta' : 'Me gusta' ?>"
                        >
                            <svg viewBox="0 0 24 24" aria-hidden="true">
                                <path d="M20.8 4.6a5.5 5.5 0 0 0-7.8 0L12 5.7l-1.1-1.1a5.5 5.5 0 0 0-7.8 7.8l1.1 1.1L12 21l7.8-7.5 1.1-1.1a5.5 5.5 0 0 0-.1-7.8Z"/>
                            </svg>
                        </button>
                    </form>

                    <form method="post" action="/?page=favorites" class="inline-form detail-action-form">
                        <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token']) ?>">
                        <input type="hidden" name="content_id" value="<?= (int)$contentDetail['id'] ?>">
                        <input type="hidden" name="favorite_action" value="<?= $isFavorite ? 'remove' : 'add' ?>">
                        <input type="hidden" name="return_to" value="<?= e($detailReturnUrl) ?>">

                        <button
                            class="button button-quiet detail-icon-button <?= $isFavorite ? 'is-active' : '' ?>"
                            type="submit"
                            aria-label="<?= $isFavorite ? 'Quitar de favoritos' : 'Agregar a favoritos' ?>"
                            title="<?= $isFavorite ? 'Quitar de favoritos' : 'Agregar a favoritos' ?>"
                        >
                            <svg viewBox="0 0 24 24" aria-hidden="true">
                                <path d="M6 3.5h12v17l-6-4-6 4v-17Z"/>
                            </svg>
                        </button>
                    </form>

                <?php else: ?>

                    <a class="button detail-icon-button" href="/?page=login" aria-label="Iniciar sesión para indicar me gusta" title="Me gusta">
                        <svg viewBox="0 0 24 24" aria-hidden="true">
                            <path d="M20.8 4.6a5.5 5.5 0 0 0-7.8 0L12 5.7l-1.1-1.1a5.5 5.5 0 0 0-7.8 7.8l1.1 1.1L12 21l7.8-7.5 1.1-1.1a5.5 5.5 0 0 0-.1-7.8Z"/>
                        </svg>
                    </a>

                    <a class="button detail-icon-button" href="/?page=login" aria-label="Iniciar sesión para guardar en favoritos" title="Favorito">
                        <svg viewBox="0 0 24 24" aria-hidden="true">
                            <path d="M6 3.5h12v17l-6-4-6 4v-17Z"/>
                        </svg>
                    </a>

                <?php endif; ?>

            </div>

            <div class="content-detail-information">

                <div class="content-detail-stat">
                    <span class="content-detail-stat-label">Descargas</span>
                    <strong><?= number_format((int)$contentDetail['downloads_count']) ?></strong>
                </div>

                <div class="content-detail-stat">
                    <span class="content-detail-stat-label">Plataforma</span>
                    <strong><?= e(ucfirst((string)$contentDetail['platform'])) ?></strong>
                </div>

                <div class="content-detail-stat content-detail-versions">
                    <span class="content-detail-stat-label">Versiones de Minecraft</span>

                    <?php if ($versions): ?>
                        <div class="content-detail-version-list">
                            <?php foreach ($versions as $version): ?>
                                <?php if (is_scalar($version)): ?>
                                    <span><?= e((string)$version) ?></span>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <strong>Sin especificar</strong>
                    <?php endif; ?>
                </div>

            </div>

        </aside>

    </main>

<?php else: ?>

    <section class="page-banner">
        <h1>Contenido no encontrado</h1>
        <p>Puede que el contenido no exista o ya no esté publicado.</p>
        <a class="button button-primary" href="/">Volver al inicio</a>
    </section>

<?php endif; ?>
