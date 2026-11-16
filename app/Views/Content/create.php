
<?php
$formContent = $editingContent ?: [];

$formVersions = json_decode(
    (string)($formContent['game_versions'] ?? '[]'),
    true
);

if (!is_array($formVersions)) {
    $formVersions = [];
}

$formVersionsText = implode(', ', array_filter($formVersions, 'is_scalar'));
?>

<section class="page-banner">
    <span class="eyebrow">COMUNIDAD FOXCRAFT</span>
    <h1>Compartí tu <span>contenido.</span></h1>
    <p>¿Creaste un mod, mapa o recurso para Minecraft? Enviá tu propuesta para que podamos compartirla con la comunidad.</p>
</section>

<section class="section">
    <div class="section-heading">
        <div>
            <span class="eyebrow">NUEVA PROPUESTA</span>
            <h2>Enviar contenido</h2>
        </div>
    </div>

    <?php if (!empty($errors)): ?>
        <div class="empty-state">
            <?php foreach ($errors as $error): ?>
                <p><?= e((string)$error) ?></p>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <form
        method="post"
        action="/?page=create-content"
        enctype="multipart/form-data"
        class="auth-form content-wizard"
        data-content-wizard
        novalidate
    >
        <input
            type="hidden"
            name="csrf_token"
            value="<?= e((string)$_SESSION['csrf_token']) ?>"
        >

        <div class="content-wizard-progress" aria-label="Progreso del formulario">
            <span data-wizard-progress="1" class="is-active">01. Identidad</span>
            <span data-wizard-progress="2">02. Plataforma</span>
            <span data-wizard-progress="3">03. Categoría</span>
            <span data-wizard-progress="4">04. Detalles</span>
        </div>

        <!-- ETAPA 1: IDENTIDAD -->
        <fieldset class="content-wizard-step" data-wizard-step="1">
            <div class="content-wizard-heading">
                <span class="eyebrow">PASO 01 DE 04</span>
                <h2>Presentá tu contenido</h2>
                <p>Elegí una imagen de portada y definí cómo se va a identificar tu proyecto.</p>
            </div>

            <div class="content-cover-upload">
                <div class="content-cover-preview" data-cover-preview>
                    <span>Vista previa de portada</span>
                </div>

                <label for="content-image-file">Imagen de portada</label>
                <input
                    id="content-image-file"
                    name="image_file"
                    type="file"
                    accept="image/jpeg,image/png,image/webp"
                    required
                    data-cover-input
                >
                <small class="form-hint">
                    Obligatoria. JPG, PNG o WebP. Máximo 2 MB.
                </small>
            </div>

            <label for="content-title">Nombre del contenido</label>
            <input
                id="content-title"
                name="title"
                maxlength="180"
                required
                value="<?= e((string)($formContent['title'] ?? '')) ?>"
                placeholder="Ejemplo: Mi aventura épica"
            >

            <label for="content-slug">Slug de la URL</label>
            <input
                id="content-slug"
                name="slug"
                maxlength="200"
                pattern="[a-z0-9-]+"
                required
                value="<?= e((string)($formContent['slug'] ?? '')) ?>"
                placeholder="mi-aventura-epica"
                data-slug-input
            >
            <small class="form-hint">
                Se utilizará en la dirección de tu contenido. Los espacios se convierten en guiones.
            </small>

            <div class="content-wizard-actions">
                <button class="button button-primary button-large" type="button" data-wizard-next>
                    Continuar
                </button>
            </div>
        </fieldset>

        <!-- ETAPA 2: PLATAFORMA -->
        <fieldset class="content-wizard-step" data-wizard-step="2">
            <div class="content-wizard-heading">
                <span class="eyebrow">PASO 02 DE 04</span>
                <h2>¿Para qué plataforma es?</h2>
                <p>Seleccioná la edición de Minecraft compatible con tu contenido.</p>
            </div>

            <div class="content-platform-options">
                <label class="content-choice">
                    <input
                        type="radio"
                        name="platform"
                        value="java"
                        required
                        <?= ($formContent['platform'] ?? 'java') === 'java' ? 'checked' : '' ?>
                    >
                    <span class="content-choice-card">
                        <strong>Minecraft Java</strong>
                        <small>Contenido para la edición Java.</small>
                    </span>
                </label>

                <label class="content-choice">
                    <input
                        type="radio"
                        name="platform"
                        value="bedrock"
                        required
                        <?= ($formContent['platform'] ?? '') === 'bedrock' ? 'checked' : '' ?>
                    >
                    <span class="content-choice-card">
                        <strong>Minecraft Bedrock</strong>
                        <small>Contenido para la edición Bedrock.</small>
                    </span>
                </label>
            </div>

            <div class="content-wizard-actions">
                <button class="button button-secondary" type="button" data-wizard-back>
                    Volver
                </button>
                <button class="button button-primary button-large" type="button" data-wizard-next>
                    Continuar
                </button>
            </div>
        </fieldset>

        <!-- ETAPA 3: CATEGORÍA -->
        <fieldset class="content-wizard-step" data-wizard-step="3">
            <div class="content-wizard-heading">
                <span class="eyebrow">PASO 03 DE 04</span>
                <h2>Elegí una categoría</h2>
                <p>Las categorías disponibles dependen de la plataforma que seleccionaste.</p>
            </div>

            <label for="content-category">Tipo de contenido</label>
            <select id="content-category" name="category_id" required>
                <option value="">Seleccioná una categoría</option>

                <?php
                $submissionCategories = [
                    'mods' => [
                        'label' => 'Mod',
                        'platforms' => 'java',
                    ],
                    'modpacks' => [
                        'label' => 'Modpack',
                        'platforms' => 'java',
                    ],
                    'maps' => [
                        'label' => 'Mapa',
                        'platforms' => 'java bedrock',
                    ],
                    'texture-packs' => [
                        'label' => 'Textura',
                        'platforms' => 'java bedrock',
                    ],
                    'shaders' => [
                        'label' => 'Shader',
                        'platforms' => 'java bedrock',
                    ],
                    'scripts' => [
                        'label' => 'Script',
                        'platforms' => 'java',
                    ],
                    'addons' => [
                        'label' => 'Addon',
                        'platforms' => 'bedrock',
                    ],
                ];
                ?>

                <?php foreach ($categories as $category): ?>
                    <?php
                    $categorySlug = (string)$category['slug'];
                    $categoryConfig = $submissionCategories[$categorySlug] ?? null;

                    if ($categoryConfig === null) {
                        continue;
                    }
                    ?>

                    <option
                        value="<?= (int)$category['id'] ?>"
                        data-platforms="<?= e($categoryConfig['platforms']) ?>"
                        <?= (int)($formContent['category_id'] ?? 0) === (int)$category['id'] ? 'selected' : '' ?>
                    >
                        <?= e($categoryConfig['label']) ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <div class="content-wizard-actions">
                <button class="button button-secondary" type="button" data-wizard-back>
                    Volver
                </button>
                <button class="button button-primary button-large" type="button" data-wizard-next>
                    Continuar
                </button>
            </div>
        </fieldset>

        <!-- ETAPA 4: DETALLES -->
        <fieldset class="content-wizard-step" data-wizard-step="4">
            <div class="content-wizard-heading">
                <span class="eyebrow">PASO 04 DE 04</span>
                <h2>Contanos más sobre tu proyecto</h2>
                <p>Agregá la información que necesita la comunidad para conocerlo y descargarlo.</p>
            </div>

            <label for="content-description">Descripción</label>
            <textarea
                id="content-description"
                name="description"
                rows="6"
                required
                placeholder="Explicá qué ofrece tu contenido..."
            ><?= e((string)($formContent['description'] ?? '')) ?></textarea>

            <label for="content-versions">Versiones de Minecraft</label>
            <input
                id="content-versions"
                name="game_versions"
                maxlength="1000"
                value="<?= e($formVersionsText) ?>"
                placeholder="Ejemplo: 1.20.80, 1.21.0"
            >

            <label for="content-download">Enlace de descarga</label>
            <input
                id="content-download"
                name="download_url"
                type="url"
                maxlength="2000"
                value="<?= e((string)($formContent['download_url'] ?? '')) ?>"
                placeholder="https://..."
            >

            <div class="content-wizard-actions">
                <button class="button button-secondary" type="button" data-wizard-back>
                    Volver
                </button>
                <button class="button button-primary button-large" type="submit">
                    Enviar propuesta
                </button>
            </div>
        </fieldset>
    </form>

    <p class="form-hint">
        Todas las propuestas son revisadas por el equipo de FoxCraft antes de publicarse.
    </p>
</section>
