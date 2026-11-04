
<section class="profile-edit-page">

    <header class="profile-edit-heading">
        <span class="eyebrow">CONFIGURACIÓN DE CUENTA</span>
        <h1>Editar <span>perfil.</span></h1>
        <p>Personalizá tu espacio dentro de la comunidad FoxCraft.</p>
    </header>

    <?php if (is_array($profileData)): ?>

        <form
            class="profile-edit-form"
            method="post"
            action="/?page=edit-profile"
            enctype="multipart/form-data"
        >
            <input
                type="hidden"
                name="csrf_token"
                value="<?= e((string)$_SESSION['csrf_token']) ?>"
            >

            <div class="profile-edit-layout">

                <aside class="profile-edit-sidebar">

                    <div class="profile-edit-avatar-card">
                        <span class="profile-edit-section-label">FOTO DE PERFIL</span>

                        <div class="profile-edit-preview">
                            <img
                                id="avatar-preview"
                                src="<?= e((string)($profileData['avatar_path'] ?? '')) ?>"
                                alt="Vista previa del avatar"
                                <?= empty($profileData['avatar_path']) ? 'hidden' : '' ?>
                            >

                            <span
                                id="avatar-placeholder"
                                <?= !empty($profileData['avatar_path']) ? 'hidden' : '' ?>
                            >
                                <?= e(strtoupper(substr(
                                    (string)($profileData['display_name'] ?: $profileData['username']),
                                    0,
                                    1
                                ))) ?>
                            </span>
                        </div>

                        <strong class="profile-edit-avatar-name">
                            <?= e((string)($profileData['display_name'] ?: $profileData['username'])) ?>
                        </strong>

                        <span class="profile-edit-avatar-username">
                            @<?= e((string)$profileData['username']) ?>
                        </span>

                        <label class="profile-edit-upload" for="avatar">
                            <span>+</span> Cambiar fotografía
                        </label>

                        <input
                            class="profile-edit-file-input"
                            id="avatar"
                            name="avatar"
                            type="file"
                            accept="image/jpeg,image/png,image/webp"
                        >

                        <small>JPG, PNG o WebP. Máximo 2 MB.</small>

                            <div
                                class="avatar-cropper"
                                id="avatar-cropper"
                                hidden
                                role="dialog"
                                aria-modal="true"
                                aria-labelledby="avatar-cropper-title"
                            >
                                <div class="avatar-cropper-panel">

                                    <header class="avatar-cropper-header">
                                        <div>
                                            <span class="profile-edit-section-label">FOXCRAFT / AVATAR</span>
                                            <h2 id="avatar-cropper-title">Ajustar fotografía</h2>
                                            <p>Mové y acercá la imagen para elegir tu encuadre.</p>
                                        </div>

                                        <button
                                            type="button"
                                            class="avatar-cropper-close"
                                            id="avatar-cropper-close"
                                            aria-label="Cerrar editor"
                                        >×</button>
                                    </header>

                                    <div class="avatar-cropper-loading" id="avatar-cropper-loading" hidden>
                                        <span class="avatar-cropper-spinner"></span>
                                        <p>Cargando imagen...</p>
                                    </div>

                                    <div class="avatar-cropper-workspace" id="avatar-cropper-workspace" hidden>
                                        <div class="avatar-cropper-stage" id="avatar-cropper-stage">
                                            <img
                                                id="avatar-cropper-image"
                                                src=""
                                                alt="Imagen para recortar"
                                                draggable="false"
                                            >
                                            <div class="avatar-cropper-mask">
                                                <div class="avatar-cropper-circle"></div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="avatar-cropper-controls">
                                        <label for="avatar-zoom">Zoom</label>

                                        <div class="avatar-zoom-control">
                                            <span>−</span>
                                            <input
                                                type="range"
                                                id="avatar-zoom"
                                                min="1"
                                                max="3"
                                                step="0.01"
                                                value="1"
                                            >
                                            <span>+</span>
                                        </div>

                                        <small>Arrastrá la imagen para ajustar el encuadre.</small>
                                    </div>

                                    <footer class="avatar-cropper-actions">
                                        <button
                                            type="button"
                                            class="button button-outline"
                                            id="avatar-cropper-cancel"
                                        >
                                            Cancelar
                                        </button>

                                        <button
                                            type="button"
                                            class="button button-primary"
                                            id="avatar-cropper-confirm"
                                        >
                                            Confirmar recorte
                                        </button>
                                    </footer>
                                </div>
                        </div>
                    </div>

                    <div class="profile-edit-tip">
                        <span>✦</span>
                        <p>Tu perfil representa tu identidad dentro de FoxCraft. Utilizá información que quieras compartir públicamente.</p>
                    </div>

                </aside>

                <div class="profile-edit-main">

                    <section class="profile-edit-section">
                        <div class="profile-edit-section-heading">
                            <div>
                                <span class="profile-edit-section-label">01 / IDENTIDAD</span>
                                <h2>Información personal</h2>
                                <p>Así te verá el resto de la comunidad.</p>
                            </div>
                        </div>

                        <div class="profile-edit-field">
                            <label for="display_name">Nombre visible</label>
                            <input
                                id="display_name"
                                name="display_name"
                                type="text"
                                maxlength="60"
                                placeholder="Tu nombre"
                                value="<?= e((string)($profileData['display_name'] ?? '')) ?>"
                            >
                            <small>Máximo 60 caracteres.</small>
                        </div>

                        <div class="profile-edit-field">
                            <label for="bio">Biografía</label>
                            <textarea
                                id="bio"
                                name="bio"
                                maxlength="500"
                                rows="5"
                                placeholder="Contale a la comunidad un poco sobre vos..."
                            ><?= e((string)($profileData['bio'] ?? '')) ?></textarea>
                            <small>Máximo 500 caracteres.</small>
                        </div>
                    </section>

                    <section class="profile-edit-section">
                        <div class="profile-edit-section-heading">
                            <div>
                                <span class="profile-edit-section-label">02 / ENLACES</span>
                                <h2>Tu sitio web</h2>
                                <p>Compartí tu página o proyecto personal.</p>
                            </div>
                        </div>

                        <div class="profile-edit-field">
                            <label for="website_url">Dirección web</label>
                            <input
                                id="website_url"
                                name="website_url"
                                type="url"
                                maxlength="255"
                                placeholder="https://ejemplo.com"
                                value="<?= e((string)($profileData['website_url'] ?? '')) ?>"
                            >
                            <small>Debe ser una dirección HTTPS válida.</small>
                        </div>
                    </section>

                    <div class="profile-edit-actions">
                        <a class="button button-outline" href="/?page=profile">
                            Cancelar
                        </a>

                        <button class="button button-primary" type="submit">
                            Guardar cambios <span>↗</span>
                        </button>
                    </div>

                </div>
            </div>
        </form>

    <?php else: ?>

        <div class="profile-edit-error">
            No pudimos cargar los datos de tu cuenta.
        </div>

    <?php endif; ?>

</section>
