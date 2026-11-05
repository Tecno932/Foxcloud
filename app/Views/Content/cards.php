
<?php
if (!$contents) {
    echo '<div class="empty-state"><span class="empty-icon">✦</span>';
    echo '<h3>Próximamente, nuevo contenido</h3>';
    echo '<p>Estamos preparando publicaciones para esta sección.</p></div>';
    return;
}

echo '<div class="content-grid">';

foreach ($contents as $item) {
    $image = image_display_url(trim((string)($item['image_url'] ?? '')));
    $validImage = $image !== '' &&
        (str_starts_with($image, '/') || str_starts_with($image, 'https://'));

    $detailUrl = '/' . rawurlencode((string)$item['category_slug']) . '/' .
        (int)$item['id'] . '/' . rawurlencode((string)$item['slug']);

    $platform = ucfirst((string)($item['platform'] ?? 'other'));
    $category = (string)($item['category_name'] ?? 'Contenido');

    echo '<article class="content-card">';

    echo '<a class="content-card-link" href="' . e($detailUrl) . '">';

    echo '<div class="content-image">';

    if ($validImage) {
        echo '<img class="content-image-background" loading="lazy" src="' . e($image) . '" alt="" aria-hidden="true">';
        echo '<img class="content-image-main" loading="lazy" src="' . e($image) . '" alt="' . e((string)$item['title']) . '">';
    } else {
        echo '<span class="image-placeholder">FOXCRAFT</span>';
    }

    echo '<div class="content-image-overlay">';

    echo '<div class="content-image-labels">';
    echo '<span>' . e($platform) . '</span>';
    echo '<span>' . e($category) . '</span>';
    echo '</div>';

    echo '<span class="content-image-downloads">';
    echo '<span aria-hidden="true">↓</span> ';
    echo '<span>' . number_format((int)$item['downloads_count']) . '</span>';
    echo '</span>';

    echo '</div>';

    echo '</div>';

    echo '<div class="content-info">';

    echo '<h3>' . e((string)$item['title']) . '</h3>';

    echo '<p>' . e(mb_strimwidth((string)$item['description'], 0, 115, '…', 'UTF-8')) . '</p>';

    echo '</div>';

    echo '</a>';

    if (!empty($showFavoriteActions)) {
        echo '<form method="post" action="/?page=favorites" class="inline-form content-card-favorite">';
        echo '<input type="hidden" name="csrf_token" value="' . e((string)$_SESSION['csrf_token']) . '">';
        echo '<input type="hidden" name="content_id" value="' . (int)$item['id'] . '">';
        echo '<input type="hidden" name="favorite_action" value="remove">';
        echo '<button class="button button-quiet" type="submit" aria-label="Quitar de favoritos" title="Quitar de favoritos">♥</button>';
        echo '</form>';
    }

    echo '</article>';
}

echo '</div>';
