
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
                <label
                    for="content-image-file"
                    class="content-cover-dropzone"
                    data-cover-dropzone
                >
                    <div class="content-cover-preview" data-cover-preview>
                        <div class="content-cover-placeholder">
                            <span class="content-cover-upload-icon" aria-hidden="true">+</span>
                            <strong>Subí la imagen de portada</strong>
                            <span>Arrastrá una imagen hasta acá o hacé clic para seleccionarla</span>
                        </div>
                    </div>

                    <input
                        id="content-image-file"
                        class="content-cover-input"
                        name="image_file"
                        type="file"
                        accept="image/jpeg,image/png,image/webp"
                        required
                        data-cover-input
                    >
                </label>

                <small class="form-hint">
                    Obligatoria. JPG, PNG o WebP. Máximo 2 MB. Podés seleccionar otra imagen para reemplazarla.
                </small>
            </div>

            <div class="content-introduction">
                <label for="content-introduction">Introducción de la tarjeta</label>
                <textarea
                    id="content-introduction"
                    name="introduction"
                    rows="3"
                    maxlength="300"
                    required
                    placeholder="Escribí una presentación breve de tu contenido..."
                ><?= e((string)($formContent['introduction'] ?? '')) ?></textarea>
                <small class="form-hint">
                    Este texto aparecerá en la tarjeta de tu publicación. Máximo 300 caracteres.
                </small>
            </div>

            <label for="content-title">Nombre del contenido</label>
            <input
                id="content-title"
                name="title"
                maxlength="180"
                required
                value="<?= e((string)($formContent['title'] ?? '')) ?>"
                placeholder="Nombre del mod"
            >

            <label for="content-slug">Slug de la URL</label>
            <input
                id="content-slug"
                name="slug"
                maxlength="200"
                pattern="[a-z0-9-]+"
                required
                value="<?= e((string)($formContent['slug'] ?? '')) ?>"
                placeholder="nombre-de-la-url"
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
            
            <label for="content-versions">Versiones de Minecraft</label>
            <input
                id="content-versions"
                name="game_versions"
                maxlength="1000"
                value="<?= e($formVersionsText) ?>"
                placeholder="Ejemplo: 1.20.80, 1.21.0"
            >

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

                <label for="content-description-editor">Descripción del contenido</label>

                <div class="content-editor-wrapper">
                    <div id="content-description-toolbar">
                        <span class="ql-formats">
                            <select class="ql-header">
                                <option selected></option>
                                <option value="1"></option>
                                <option value="2"></option>
                                <option value="3"></option>
                            </select>
                            <select class="ql-font"></select>
                            <select class="ql-size"></select>
                        </span>

                        <span class="ql-formats">
                            <button type="button" class="ql-bold"></button>
                            <button type="button" class="ql-italic"></button>
                            <button type="button" class="ql-underline"></button>
                            <button type="button" class="ql-strike"></button>
                        </span>

                        <span class="ql-formats">
                            <select class="ql-color"></select>
                            <select class="ql-background"></select>
                        </span>

                        <span class="ql-formats">
                            <button type="button" class="ql-list" value="ordered"></button>
                            <button type="button" class="ql-list" value="bullet"></button>
                            <button type="button" class="ql-blockquote"></button>
                        </span>

                        <span class="ql-formats">
                            <button type="button" class="ql-link"></button>
                            <button type="button" class="ql-clean"></button>
                        </span>
                    </div>

                    <div id="content-description-editor"></div>
                </div>

                <textarea
                    id="content-description"
                    name="description"
                    hidden
                ><?= e((string)($formContent['description'] ?? '')) ?></textarea>

                <div class="content-editor-preview-controls">
                    <button
                        type="button"
                        class="button button-secondary"
                        data-description-preview-toggle
                        aria-expanded="false"
                    >
                        Vista previa del contenido
                    </button>
                </div>

                <section
                    class="content-editor-preview"
                    data-description-preview
                    hidden
                >
                    <h3>Vista previa de la publicación</h3>
                    <div
                        class="content-rich-description"
                        data-description-preview-content
                    ></div>
                </section>

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
