<?php
if (!$contents) {
    echo '<div class="empty-state"><span class="empty-icon">✦</span>';
    echo '<h3>Próximamente, nuevo contenido</h3>';
    echo '<p>Estamos preparando publicaciones para esta sección.</p></div>';
    return;
}

echo '<div class="content-grid">';
foreach ($contents as $item) {
    $image = trim((string)($item['image_url'] ?? ''));
    $validImage = $image !== '' &&
        (str_starts_with($image, '/') || str_starts_with($image, 'https://'));

    echo '<article class="content-card">';
    $detailUrl = '/' . rawurlencode((string)$item['category_slug']) . '/' .
        (int)$item['id'] . '/' . rawurlencode((string)$item['slug']);
    echo '<a class="content-image" href="' . e($detailUrl) . '">';
    if ($validImage) {
        echo '<img loading="lazy" src="' . e($image) . '" alt="' . e((string)$item['title']) . '">';
    } else {
        echo '<span class="image-placeholder">FOXCRAFT</span>';
    }
    echo '</a><div class="content-info">';
    echo '<span class="content-category">' . e((string)$item['category_name']) . '</span>';
    echo '<h3><a href="' . e($detailUrl) . '">' . e((string)$item['title']) . '</a></h3>';
    echo '<p>' . e(mb_strimwidth((string)$item['description'], 0, 115, '…', 'UTF-8')) . '</p>';
    echo '<div class="content-meta"><span>↓ ' . number_format((int)$item['downloads_count']) . '</span>';
    echo '<span>♥ ' . number_format((int)$item['likes_count']) . '</span></div>';

    if (!empty($showFavoriteActions)) {
        echo '<form method="post" action="/?page=favorites" class="inline-form">';
        echo '<input type="hidden" name="csrf_token" value="' . e((string)$_SESSION['csrf_token']) . '">';
        echo '<input type="hidden" name="content_id" value="' . (int)$item['id'] . '">';
        echo '<input type="hidden" name="favorite_action" value="remove">';
        echo '<button class="button button-quiet" type="submit">♥ Quitar de favoritos</button>';
        echo '</form>';
    }

    echo '</div></article>';
}
echo '</div>';
