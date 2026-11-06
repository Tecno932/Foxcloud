
<section class="section search-section<?= $page === 'explorer' ? ' explorer-search' : '' ?>">
    <form method="GET" action="/" class="search-form">
        <input type="hidden" name="page" value="<?= e($page) ?>">

        <div class="search-field">
            <label for="catalog-search">
                <?= $page === 'skins' ? 'Buscar skins' : 'Buscar contenido' ?>
            </label>
            <input
                type="search"
                id="catalog-search"
                name="q"
                value="<?= e($searchQuery) ?>"
                maxlength="180"
                placeholder="<?= $page === 'skins' ? 'Buscar por nombre...' : 'Buscar mods, mapas, texturas...' ?>"
            >
        </div>

        <?php if ($page !== 'skins'): ?>
            <div class="search-field">
                <label for="catalog-category">Categoría</label>
                <select id="catalog-category" name="category">
                    <option value="">Todas las categorías</option>
                    <?php foreach ($categories as $category): ?>
                        <?php
                        if (
                            $page === 'explorer'
                            && !in_array($category['slug'], ['mods', 'maps', 'resource-packs'], true)
                        ) {
                            continue;
                        }
                        ?>
                        <option value="<?= (int)$category['id'] ?>"
                            <?= $currentCategory === (int)$category['id'] ? 'selected' : '' ?>>
                            <?= e($category['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="search-field">
                <label for="catalog-platform">Plataforma</label>
                <select id="catalog-platform" name="platform">
                    <option value="">Todas</option>
                    <option value="java" <?= $filterPlatform === 'java' ? 'selected' : '' ?>>Java</option>
                    <option value="bedrock" <?= $filterPlatform === 'bedrock' ? 'selected' : '' ?>>Bedrock</option>
                </select>
            </div>

            <div class="search-field">
                <label for="catalog-version">Versión</label>
                <select id="catalog-version" name="version">
                    <option value="">Todas</option>
                    <option value="1.20.8" <?= $filterVersion === '1.20.8' ? 'selected' : '' ?>>1.20.8</option>
                    <option value="26+" <?= $filterVersion === '26+' ? 'selected' : '' ?>>26+</option>
                </select>
            </div>
        <?php endif; ?>

        <div class="search-actions">
            <button class="button button-primary" type="submit">Buscar</button>
            <a class="button button-outline" href="<?= $page === 'home' ? '/' : '/?page=' . rawurlencode($page) ?>">
                Limpiar
            </a>
        </div>
    </form>
</section>
