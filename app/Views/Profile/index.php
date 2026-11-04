<section class="profile-page">
    <?php if (is_array($profileData)): ?>
        <?php
        $profileName = trim((string)($profileData['display_name'] ?? ''));
        $username = (string)($profileData['username'] ?? 'Usuario');
        $profileBio = trim((string)($profileData['bio'] ?? ''));
        $profileWebsite = trim((string)($profileData['website_url'] ?? ''));
        $profileAvatar = trim((string)($profileData['avatar_path'] ?? ''));

        if ($profileName === '') {
            $profileName = $username;
        }

        $avatarValid = $profileAvatar !== '' &&
            (
                (str_starts_with($profileAvatar, '/') && !str_starts_with($profileAvatar, '//')) ||
                str_starts_with($profileAvatar, 'https://')
            );

        $profileTab = $_GET['tab'] ?? 'posts';

        if (!in_array($profileTab, ['posts', 'favorites', 'likes'], true)) {
            $profileTab = 'posts';
        }

        $profileTabs = [
            'posts' => [
                'label' => 'Publicaciones',
                'icon' => '▦',
                'contents' => $profileContents ?? [],
            ],
            'favorites' => [
                'label' => 'Favoritos',
                'icon' => '♡',
                'contents' => $favoriteContents ?? [],
            ],
            'likes' => [
                'label' => 'Me gusta',
                'icon' => '♥',
                'contents' => $likedContents ?? [],
            ],
        ];

        $activeContents = $profileTabs[$profileTab]['contents'];
        ?>

        <section class="profile-header">
            <div class="profile-identity">
                <div class="profile-avatar">
                    <?php if ($avatarValid): ?>
                        <img src="<?= e($profileAvatar) ?>" alt="">
                    <?php else: ?>
                        <svg viewBox="0 0 24 24" aria-hidden="true">
                            <circle cx="12" cy="8" r="4"></circle>
                            <path d="M4 21a8 8 0 0 1 16 0"></path>
                        </svg>
                    <?php endif; ?>
                </div>

                <div class="profile-heading">
                    <span class="eyebrow">COMUNIDAD FOXCRAFT</span>
                    <h1><?= e($profileName) ?></h1>
                    <span class="profile-username">@<?= e($username) ?></span>
                </div>
            </div>

            <div class="profile-description">
                <?php if ($profileBio !== ''): ?>
                    <p><?= nl2br(e($profileBio)) ?></p>
                <?php else: ?>
                    <p class="profile-empty">Todavía no agregaste una biografía.</p>
                <?php endif; ?>

                <?php if ($profileWebsite !== ''): ?>
                    <a href="<?= e($profileWebsite) ?>"
                       target="_blank"
                       rel="noopener noreferrer">
                        <span>↗</span> <?= e($profileWebsite) ?>
                    </a>
                <?php endif; ?>
            </div>

            <div class="profile-actions">
                <a class="button button-primary" href="/?page=edit-profile">
                    Editar perfil
                </a>
            </div>
        </section>

        <section class="profile-activity">
            <div class="profile-tabs" role="tablist" aria-label="Actividad del perfil">
                <?php foreach ($profileTabs as $tabId => $tab): ?>
                    <a
                        class="profile-tab <?= $profileTab === $tabId ? 'active' : '' ?>"
                        href="/?page=profile&amp;tab=<?= e($tabId) ?>"
                        <?= $profileTab === $tabId ? 'aria-current="page"' : '' ?>
                    >
                        <span><?= e($tab['icon']) ?></span>
                        <?= e($tab['label']) ?>
                        <small><?= number_format(count($tab['contents'])) ?></small>
                    </a>
                <?php endforeach; ?>
            </div>

            <div class="profile-tab-content">
                <?php if ($activeContents): ?>
                    <?php
                    $contents = $activeContents;
                    $showFavoriteActions = $profileTab === 'favorites';
                    require '/var/www/foxcraft/app/Views/Content/cards.php';
                    ?>
                <?php else: ?>
                    <div class="empty-state">
                        <span class="empty-icon">✦</span>

                        <?php if ($profileTab === 'posts'): ?>
                            <h3>Todavía no tenés publicaciones</h3>
                            <p>Cuando publiques contenido, aparecerá acá.</p>
                        <?php elseif ($profileTab === 'favorites'): ?>
                            <h3>Todavía no tenés favoritos</h3>
                            <p>Guardá contenido que te guste para encontrarlo fácilmente.</p>
                        <?php else: ?>
                            <h3>Todavía no diste me gusta</h3>
                            <p>Las publicaciones que te gusten aparecerán acá.</p>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </section>

    <?php else: ?>
        <div class="profile-card">
            <h1>Perfil no disponible</h1>
            <p>No pudimos cargar los datos de tu cuenta.</p>
            <a class="button button-primary" href="/">Volver al inicio</a>
        </div>
    <?php endif; ?>
</section>