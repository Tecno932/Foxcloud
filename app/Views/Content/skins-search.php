<section class="section search-section skins-search">
    <form method="GET" action="/skins" class="search-form">
        <div class="search-field">
            <label for="skins-search">Buscar skins</label>
            <input
                type="search"
                id="skins-search"
                name="q"
                value="<?= e($searchQuery) ?>"
                maxlength="180"
                placeholder="Buscar skins por nombre..."
            >
        </div>

        <div class="search-actions">
            <button class="button button-primary" type="submit">
                Buscar skins
            </button>
            <a class="button button-outline" href="/skins">
                Limpiar
            </a>
        </div>
    </form>
</section>
