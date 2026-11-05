<section class="page-banner">
    <span class="eyebrow">TU COLECCIÓN</span>
    <h1>Mis <span>favoritos</span></h1>
    <p>Acá encontrás todas las publicaciones que guardaste.</p>
</section>

<section class="section listing-section">
    <div class="section-heading">
        <div>
            <span class="eyebrow">CONTENIDO GUARDADO</span>
            <h2>Tus <span>publicaciones</span></h2>
        </div>
        <span><?= count($favoriteContents) ?> guardados</span>
    </div>

    <?php if (!$favoriteContents): ?>
        <div class="empty-state">
            <span class="empty-icon">♡</span>
            <h3>Todavía no tenés favoritos</h3>
            <p>Explorá el contenido de FoxCraft y guardá las publicaciones que quieras encontrar fácilmente.</p>
            <a class="button button-primary" href="/?page=mods">Explorar contenido</a>
        </div>
    <?php else: ?>
        <?php
        $contents = $favoriteContents;
        $showFavoriteActions = true;
        require APP_ROOT . '/Views/Content/cards.php';
        unset($showFavoriteActions);
        ?>
    <?php endif; ?>
</section>
