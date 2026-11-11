
<?php

if ($skinsError) {
    echo '<div class="empty-state">';
    echo '<h3>No se pudo cargar el catálogo</h3>';
    echo '<p>Estamos teniendo problemas para conectar con Mosaicary. Intentá nuevamente más tarde.</p>';
    echo '</div>';
    return;
}

if (!$skins) {
    echo '<div class="empty-state">';
    echo '<span class="empty-icon">✦</span>';
    echo '<h3>No se encontraron skins</h3>';
    echo '<p>Probá con otro nombre de búsqueda.</p>';
    echo '</div>';
    return;
}

echo '<div class="content-grid skins-grid">';

foreach ($skins as $skin) {
    $name = trim((string) ($skin['name'] ?? 'Skin de Minecraft'));
    $render = trim((string) ($skin['render'] ?? $skin['front'] ?? ''));
    $texture = trim((string) ($skin['png'] ?? ''));
    $pageUrl = trim((string) ($skin['page'] ?? ''));
    $skinId = trim((string) ($skin['id'] ?? ''));

    $pngUrl = $skinId !== ''
        ? 'https://mosaicary.com/api/models/' . rawurlencode($skinId) . '/file'
        : '';

    $publisher = $skin['publisher'] ?? [];
    $author = $skin['author'] ?? [];

    $creditName = '';
    $creditUrl = '';

    if (is_array($author) && !empty($author['name'])) {
        $creditName = trim((string) $author['name']);
        $creditUrl = trim((string) ($author['page'] ?? ''));
    } elseif (is_string($author) && trim($author) !== '') {
        $creditName = trim($author);
    } elseif (is_array($publisher) && !empty($publisher['name'])) {
        $creditName = trim((string) $publisher['name']);
        $creditUrl = trim((string) ($publisher['page'] ?? ''));
    }

    if ($creditName === '') {
        $creditName = 'Autor desconocido';
    }

    $validRender = $render !== ''
        && filter_var($render, FILTER_VALIDATE_URL)
        && parse_url($render, PHP_URL_SCHEME) === 'https'
        && parse_url($render, PHP_URL_HOST) === 'mosaicary.com';

    $validTexture = $texture !== ''
        && filter_var($texture, FILTER_VALIDATE_URL)
        && parse_url($texture, PHP_URL_SCHEME) === 'https'
        && parse_url($texture, PHP_URL_HOST) === 'mosaicary.com';

    $validPage = $pageUrl !== ''
        && filter_var($pageUrl, FILTER_VALIDATE_URL)
        && parse_url($pageUrl, PHP_URL_SCHEME) === 'https'
        && parse_url($pageUrl, PHP_URL_HOST) === 'mosaicary.com';

    $validPng = $pngUrl !== ''
        && filter_var($pngUrl, FILTER_VALIDATE_URL)
        && parse_url($pngUrl, PHP_URL_SCHEME) === 'https'
        && parse_url($pngUrl, PHP_URL_HOST) === 'mosaicary.com';

    $validCreditUrl = $creditUrl !== ''
        && filter_var($creditUrl, FILTER_VALIDATE_URL)
        && parse_url($creditUrl, PHP_URL_SCHEME) === 'https'
        && parse_url($creditUrl, PHP_URL_HOST) === 'mosaicary.com';

        echo '<article class="content-card skin-card"';

        if ($validTexture) {
            echo ' data-skin-card'
                . ' data-skin-name="' . e($name) . '"'
                . ' data-skin-texture="' . e($texture) . '"'
                . ' data-skin-download="' . e($validPng ? $pngUrl : '') . '"'
                . ' data-skin-page="' . e($validPage ? $pageUrl : '') . '"'
                . ' tabindex="0"'
                . ' role="button"'
                . ' aria-label="Ver skin: ' . e($name) . '"';
        }

        echo '>';

    echo '<header class="skin-card-header">';
    echo '<h3>' . e($name) . '</h3>';
    echo '<p>by: ';

    if ($validCreditUrl) {
        echo '<a href="' . e($creditUrl) . '" target="_blank" rel="noopener noreferrer">';
        echo e($creditName);
        echo '</a>';
    } else {
        echo e($creditName);
    }

    echo '</p>';
    echo '</header>';

    echo '<div class="content-image skin-image">';

    if ($validRender) {
        echo '<img class="content-image-main" loading="lazy" src="' . e($render) . '" alt="' . e($name) . '">';
    } else {
        echo '<span class="image-placeholder">FOXCRAFT</span>';
    }

    echo '</div>';

    echo '<div class="skin-card-actions">';

    if ($validPng) {
        echo '<a class="button button-primary" href="' . e($pngUrl) . '">Descargar</a>';
    }

    if ($validTexture) {
        echo '<button'
            . ' type="button"'
            . ' class="button button-outline"'
            . ' data-skin-viewer-open'
            . ' data-skin-name="' . e($name) . '"'
            . ' data-skin-texture="' . e($texture) . '"'
            . ' data-skin-download="' . e($validPng ? $pngUrl : '') . '"'
            . ' data-skin-page="' . e($validPage ? $pageUrl : '') . '"'
            . '>Ver skin</button>';
    }

    echo '</div>';
    echo '</article>';
}

echo '</div>';

?>

<dialog class="skin-viewer-modal" id="skin-viewer-modal" aria-labelledby="skin-viewer-title">
    <div class="skin-viewer-container">
        <header class="skin-viewer-header">
            <h2 id="skin-viewer-title">Visor de skin</h2>
            <button type="button" class="skin-viewer-close" data-skin-viewer-close aria-label="Cerrar">
                &times;
            </button>
        </header>

        <div class="skin-viewer-stage">
            <canvas id="skin-viewer-canvas"></canvas>
        </div>

        <div class="skin-viewer-actions">
            <a class="button button-primary" id="skin-viewer-download" hidden>Descargar</a>
            <a class="button button-outline" id="skin-viewer-page" target="_blank" rel="noopener noreferrer" hidden>Ver página</a>
        </div>
    </div>
</dialog>
