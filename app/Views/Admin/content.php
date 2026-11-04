<?php
$formContent = $editingContent ?: [];
$formVersions = json_decode((string)($formContent['game_versions'] ?? '[]'), true);
if (!is_array($formVersions)) {
    $formVersions = [];
}
$formVersionsText = implode(', ', array_filter($formVersions, 'is_scalar'));
?>

<section class="page-banner">
    <span class="eyebrow">PANEL PRIVADO</span>
    <h1>Administración <span>FoxCraft</span></h1>
    <p>Creá publicaciones, editá su información y controlá qué contenido aparece en la web.</p>
</section>

<section class="section">
    <div class="section-heading">
        <div>
            <span class="eyebrow">GESTIÓN DE CONTENIDO</span>
            <h2><?= $editingContent ? 'Editar publicación' : 'Nueva publicación' ?></h2>
        </div>
        <?php if ($editingContent): ?>
            <a class="text-link" href="/?page=admin">Cancelar edición</a>
        <?php endif; ?>
    </div>

    <form method="post" action="/?page=admin" class="auth-form admin-form">
        <input type="hidden" name="csrf_token" value="<?= e((string)$_SESSION['csrf_token']) ?>">
        <input type="hidden" name="content_id" value="<?= (int)($formContent['id'] ?? 0) ?>">

        <label for="admin-title">Título</label>
        <input id="admin-title" name="title" maxlength="180" required value="<?= e((string)($formContent['title'] ?? '')) ?>">

        <label for="admin-slug">Slug (parte final de la URL)</label>
        <input id="admin-slug" name="slug" maxlength="200" pattern="[a-zA-Z0-9-]+" required value="<?= e((string)($formContent['slug'] ?? '')) ?>">
        <small class="form-hint">Ejemplo: mi-mod-genial</small>

        <label for="admin-category">Categoría</label>
        <select id="admin-category" name="category_id" required>
            <option value="">Seleccioná una categoría</option>
            <?php foreach ($categories as $category): ?>
                <option value="<?= (int)$category['id'] ?>" <?= (int)($formContent['category_id'] ?? 0) === (int)$category['id'] ? 'selected' : '' ?>>
                    <?= e((string)$category['name']) ?>
                </option>
            <?php endforeach; ?>
        </select>

        <label for="admin-description">Descripción</label>
        <textarea id="admin-description" name="description" rows="5"><?= e((string)($formContent['description'] ?? '')) ?></textarea>

        <label for="admin-image">Imagen (URL HTTPS o ruta local)</label>
        <input id="admin-image" name="image_url" type="text" maxlength="1000" value="<?= e((string)($formContent['image_url'] ?? '')) ?>">

        <label for="admin-download">URL de descarga (HTTPS)</label>
        <input id="admin-download" name="download_url" type="url" maxlength="2000" value="<?= e((string)($formContent['download_url'] ?? '')) ?>">

        <label for="admin-type">Tipo de contenido</label>
        <select id="admin-type" name="content_type">
            <?php foreach (['download' => 'Descarga', 'skin' => 'Skin', 'holographic' => 'Holographic'] as $value => $label): ?>
                <option value="<?= e($value) ?>" <?= ($formContent['content_type'] ?? 'download') === $value ? 'selected' : '' ?>><?= e($label) ?></option>
            <?php endforeach; ?>
        </select>

        <label for="admin-platform">Plataforma</label>
        <select id="admin-platform" name="platform">
            <?php foreach (['both' => 'Java y Bedrock', 'java' => 'Java', 'bedrock' => 'Bedrock', 'other' => 'Otra'] as $value => $label): ?>
                <option value="<?= e($value) ?>" <?= ($formContent['platform'] ?? 'both') === $value ? 'selected' : '' ?>><?= e($label) ?></option>
            <?php endforeach; ?>
        </select>

        <label for="admin-versions">Versiones del juego (separadas por coma)</label>
        <input id="admin-versions" name="game_versions" maxlength="1000" value="<?= e($formVersionsText) ?>" placeholder="1.20.80, 1.21.0">

        <label for="admin-status">Estado</label>
        <select id="admin-status" name="status">
            <?php foreach (['draft' => 'Borrador', 'published' => 'Publicado', 'archived' => 'Archivado'] as $value => $label): ?>
                <option value="<?= e($value) ?>" <?= ($formContent['status'] ?? 'draft') === $value ? 'selected' : '' ?>><?= e($label) ?></option>
            <?php endforeach; ?>
        </select>

        <label class="admin-checkbox">
            <input type="checkbox" name="is_featured" value="1" <?= !empty($formContent['is_featured']) ? 'checked' : '' ?>>
            Destacar en el inicio
        </label>

        <button class="button button-primary button-large" type="submit">
            <?= $editingContent ? 'Guardar cambios' : 'Crear publicación' ?> ↗
        </button>
    </form>
</section>

<section class="section listing-section">
    <div class="section-heading">
        <div>
            <span class="eyebrow">BIBLIOTECA</span>
            <h2>Publicaciones <span>existentes</span></h2>
        </div>
        <span class="section-count"><?= count($adminContents) ?> mostradas</span>
    </div>

    <?php if (!$adminContents): ?>
        <div class="empty-state">
            <h3>Todavía no hay publicaciones</h3>
            <p>Usá el formulario para crear la primera.</p>
        </div>
    <?php else: ?>
        <div class="admin-table-wrap">
            <table class="admin-table">
                <thead>
                    <tr><th>Título</th><th>Categoría</th><th>Estado</th><th>Acción</th></tr>
                </thead>
                <tbody>
                <?php foreach ($adminContents as $item): ?>
                    <tr>
                        <td><?= e((string)$item['title']) ?></td>
                        <td><?= e((string)$item['category_name']) ?></td>
                        <td><?= e((string)$item['status']) ?></td>
                        <td><a class="text-link" href="/?page=admin&amp;edit=<?= (int)$item['id'] ?>">Editar</a></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>
