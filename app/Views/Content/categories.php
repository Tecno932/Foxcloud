<section class="section categories-section" id="categorias">
    <div class="section-heading">
        <div><span class="eyebrow">EXPLORÁ A TU MANERA</span><h2>Categorías <span>destacadas</span></h2></div>
        <span class="section-count"><?= count($categories) ?> secciones</span>
    </div>
    <div class="category-grid">
    <?php foreach ($categories as $category): ?>
        <a class="category-card" href="/?page=<?= e((string)$category['slug']) ?>">
            <span class="category-icon">
                <img loading="lazy" src="/assets/icons/categories/<?= e(match ($category['slug']) {
                    'mods' => 'mods.png',
                    'maps' => 'maps.png',
                    'resource-packs' => 'shaders.png',
                    'skins', 'armor-trims' => 'skins.png',
                    'schematics' => 'schematics.png',
                    default => 'mods.png',
                }) ?>" alt="">
            </span>
            <span class="category-copy"><strong><?= e((string)$category['name']) ?></strong><small><?= e((string)($category['description'] ?: 'Explorá contenido para Minecraft.')) ?></small></span>
            <span class="category-arrow">↗</span>
        </a>
    <?php endforeach; ?>
    </div>
</section>
