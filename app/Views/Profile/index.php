
<section class="profile-page">
    <?php if (is_array($profileData)): ?>
        <?php
        $profileName = trim((string)($profileData['display_name'] ?? ''));
        $username = (string)($profileData['username'] ?? 'Usuario');
        $profileBio = trim((string)($profileData['bio'] ?? ''));
        $profileWebsite = trim((string)($profileData['website_url'] ?? ''));
        $profileAvatar = trim((string)($profileData['avatar_path'] ?? ''));
        $profileUserId = (int)($profileData['id'] ?? 0);

        $isOwnProfile = $isOwnProfile ?? false;
        $currentUserId = (int)($_SESSION['user_id'] ?? 0);
        $isLoggedIn = $currentUserId > 0;

        if ($profileName === '') {
            $profileName = $username;
        }

        $avatarValid = $profileAvatar !== '' &&
            (
                (str_starts_with($profileAvatar, '/') && !str_starts_with($profileAvatar, '//')) ||
                str_starts_with($profileAvatar, 'https://')
            );

        $profileTab = (string)($_GET['tab'] ?? 'posts');
        $allowedTabs = $isOwnProfile
            ? ['posts', 'favorites', 'likes']
            : ['posts'];

        if (!in_array($profileTab, $allowedTabs, true)) {
            $profileTab = 'posts';
        }

        $profileTabs = [
            'posts' => [
                'label' => 'Publicaciones',
                'icon' => '▦',
                'contents' => $profileContents ?? [],
            ],
        ];

        if ($isOwnProfile) {
            $profileTabs['favorites'] = [
                'label' => 'Favoritos',
                'icon' => '♡',
                'contents' => $favoriteContents ?? [],
            ];

            $profileTabs['likes'] = [
                'label' => 'Me gusta',
                'icon' => '♥',
                'contents' => $likedContents ?? [],
            ];
        }

        $activeContents = $profileTabs[$profileTab]['contents'] ?? [];
        $followers = $followers ?? [];
        $following = $following ?? [];
        $followersCount = (int)($followersCount ?? 0);
        $followingCount = (int)($followingCount ?? 0);

        $returnTo = '/?page=profile&user=' . rawurlencode($username);
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
                    <h1><?= e($profileName) ?></h1>

                    <div class="profile-social-stats">
                        <button type="button" data-social-list="followers">
                            <strong><?= number_format($followersCount) ?></strong>
                            <span>Seguidores</span>
                        </button>

                        <button type="button" data-social-list="following">
                            <strong><?= number_format($followingCount) ?></strong>
                            <span>Seguidos</span>
                        </button>
                    </div>
                </div>
            </div>

            <div class="profile-description">
                <?php if ($profileBio !== ''): ?>
                    <p><?= nl2br(e($profileBio)) ?></p>
                <?php else: ?>
                    <p class="profile-empty">
                        <?= $isOwnProfile
                            ? 'Todavía no agregaste una biografía.'
                            : 'Este usuario todavía no agregó una biografía.' ?>
                    </p>
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
                <?php if ($isOwnProfile): ?>
                    <a class="button button-primary" href="/?page=edit-profile">
                        Editar perfil
                    </a>
                <?php elseif ($isLoggedIn): ?>
                    <?php if ($isBlockedByMe ?? false): ?>
                        <form method="post" action="/?page=profile&amp;user=<?= e(rawurlencode($username)) ?>">
                            <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token'] ?? '') ?>">
                            <input type="hidden" name="social_action" value="unblock">
                            <input type="hidden" name="target_user_id" value="<?= $profileUserId ?>">
                            <input type="hidden" name="return_to" value="<?= e($returnTo) ?>">
                            <button type="submit" class="button button-primary">
                                Desbloquear usuario
                            </button>
                        </form>
                    <?php elseif ($isBlockedByProfile ?? false): ?>
                        <p class="profile-empty">
                            Este usuario te bloqueó. No podés interactuar con su perfil.
                        </p>
                    <?php else: ?>
                        <form method="post" action="/?page=profile&amp;user=<?= e(rawurlencode($username)) ?>">
                            <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token'] ?? '') ?>">
                            <input type="hidden" name="target_user_id" value="<?= $profileUserId ?>">
                            <input type="hidden" name="return_to" value="<?= e($returnTo) ?>">

                            <input type="hidden" name="social_action"
                                   value="<?= ($isFollowing ?? false) ? 'unfollow' : 'follow' ?>">

                            <button type="submit" class="button button-primary">
                                <?= ($isFollowing ?? false) ? 'Dejar de seguir' : 'Seguir' ?>
                            </button>
                        </form>

                        <form method="post" action="/?page=profile&amp;user=<?= e(rawurlencode($username)) ?>"
                              onsubmit="return confirm('¿Estás seguro de que querés bloquear a este usuario? Se eliminará el seguimiento en ambas direcciones.');">
                            <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token'] ?? '') ?>">
                            <input type="hidden" name="social_action" value="block">
                            <input type="hidden" name="target_user_id" value="<?= $profileUserId ?>">
                            <input type="hidden" name="return_to" value="<?= e($returnTo) ?>">
                            <button type="submit" class="button">
                                Bloquear
                            </button>
                        </form>
                    <?php endif; ?>
                <?php else: ?>
                    <a class="button button-primary" href="/?page=login">
                        Iniciar sesión para seguir
                    </a>
                <?php endif; ?>
            </div>

                        <?php if ($isOwnProfile): ?>
                <section class="discord-account-card">
                    <div class="discord-account-info">
                        <span class="discord-account-label">CUENTA DISCORD</span>

                        <?php if (!empty($discordAccount)): ?>
                            <h2>
                                <?= e(
                                    $discordAccount['global_name']
                                    ?: $discordAccount['username']
                                ) ?>
                            </h2>

                            <p>
                                @<?= e($discordAccount['username']) ?>
                            </p>

                            <span class="discord-account-status">
                                Cuenta vinculada
                            </span>
                        <?php else: ?>
                            <h2>Conectá tu Discord</h2>

                            <p>
                                Vinculá tu cuenta para conectar tu identidad
                                de Discord con tu perfil FoxCraft.
                            </p>
                        <?php endif; ?>
                    </div>

                    <?php if (!empty($discordAccount)): ?>
                        <form
                            method="post"
                            action="/?page=discord-unlink"
                            onsubmit="return confirm('¿Querés desvincular tu cuenta Discord de FoxCraft?');"
                        >
                            <input
                                type="hidden"
                                name="csrf_token"
                                value="<?= e($_SESSION['csrf_token'] ?? '') ?>"
                            >

                            <button type="submit" class="button">
                                Desvincular Discord
                            </button>
                        </form>
                    <?php else: ?>
                        <a
                            class="button button-primary"
                            href="/?page=discord-link"
                        >
                            Vincular Discord
                        </a>
                    <?php endif; ?>
                </section>
            <?php endif; ?>

            <?php if ($isOwnProfile && !empty($discordAccount)): ?>
                <section class="discord-guilds-card">
                    <div class="discord-guilds-heading">
                        <div>
                            <span class="discord-account-label">INTEGRACIÓN CON DISCORD</span>
                            <h2>Mis servidores</h2>
                            <p>Servidores que administrás con la cuenta Discord vinculada.</p>
                        </div>
                        <a class="button button-primary" href="/?page=discord-guilds">
                            <?= empty($discordManagedGuilds) && !$discordGuildsChecked ? 'Consultar servidores' : 'Actualizar lista' ?>
                        </a>
                    </div>

                    <?php if (!empty($discordManagedGuilds)): ?>
                        <div class="discord-guild-list">
                            <?php foreach ($discordManagedGuilds as $guild): ?>
                                <?php
                                $guildId = (string)($guild['id'] ?? '');
                                $guildName = (string)($guild['name'] ?? 'Servidor Discord');
                                $guildInstalled = isset($discordInstalledGuilds[$guildId]);
                                $guildInviteUrl = '';

                                if (
                                    !$guildInstalled
                                    && $discordGuildsChecked
                                    && $discordClientId !== ''
                                    && preg_match('/^[0-9]{17,20}$/', $guildId)
                                ) {
                                    $guildInviteUrl = 'https://discord.com/oauth2/authorize?' . http_build_query([
                                        'client_id' => $discordClientId,
                                        'scope' => 'bot applications.commands',
                                        'permissions' => '0',
                                        'guild_id' => $guildId,
                                        'disable_guild_select' => 'true',
                                    ], '', '&', PHP_QUERY_RFC3986);
                                }
                                ?>
                                <article class="discord-guild-item">
                                    <div class="discord-guild-details">
                                        <h3><?= e($guildName) ?></h3>
                                        <span class="discord-guild-id">ID: <?= e($guildId) ?></span>

                                        <?php if ($guildInstalled): ?>
                                            <span class="discord-guild-status is-installed">
                                                Foxcraftservice está instalado
                                            </span>
                                        <?php elseif ($discordGuildsChecked): ?>
                                            <span class="discord-guild-status is-not-installed">
                                                Foxcraftservice no está instalado
                                            </span>
                                        <?php else: ?>
                                            <span class="discord-guild-status is-unknown">
                                                No se pudo comprobar la instalación
                                            </span>
                                        <?php endif; ?>
                                    </div>

                                    <?php if ($guildInviteUrl !== ''): ?>
                                        <div class="discord-guild-actions">
                                            <a
                                                class="button button-primary"
                                                href="<?= e($guildInviteUrl) ?>"
                                                target="_blank"
                                                rel="noopener noreferrer"
                                            >
                                                Invitar Foxcraftservice
                                            </a>
                                        </div>
                                    <?php elseif ($guildInstalled): ?>
                                        <a
                                            class="button button-primary"
                                            href="/?page=discord-manage&amp;id=<?= rawurlencode($guildId) ?>"
                                        >
                                            Administrar servidor
                                        </a>
                                    <?php endif; ?>
                                </article>
                            <?php endforeach; ?>
                        </div>
                    <?php elseif ($discordGuildsChecked): ?>
                        <p class="discord-guilds-empty">
                            Discord no devolvió servidores que administres con esta cuenta.
                        </p>
                    <?php else: ?>
                        <p class="discord-guilds-empty">
                            Consultá tus servidores para comprobar dónde está instalado Foxcraftservice.
                        </p>
                    <?php endif; ?>

                    <?php if (!empty($discordManagedGuilds) && !$discordGuildsChecked): ?>
                        <p class="discord-guilds-warning">
                            No se pudo verificar el estado de instalación. Actualizá la lista más tarde.
                        </p>
                    <?php endif; ?>
                </section>
            <?php endif; ?>

            <dialog class="profile-social-dialog" data-social-dialog>
                <div class="profile-social-dialog-header">
                    <h2 data-social-dialog-title></h2>
                    <button type="button" data-social-close aria-label="Cerrar">
                        &times;
                    </button>
                </div>

                <div class="profile-social-dialog-content">
                    <?php foreach ([
                        'followers' => $followers,
                        'following' => $following,
                    ] as $listType => $socialUsers): ?>
                        <div data-social-panel="<?= e($listType) ?>" hidden>
                            <?php if ($socialUsers): ?>
                                <ul>
                                    <?php foreach ($socialUsers as $socialUser): ?>
                                        <?php
                                        $socialAvatar = trim((string)($socialUser['avatar_path'] ?? ''));
                                        $socialAvatarValid = $socialAvatar !== '' &&
                                            (
                                                (str_starts_with($socialAvatar, '/') && !str_starts_with($socialAvatar, '//')) ||
                                                str_starts_with($socialAvatar, 'https://')
                                            );

                                        $socialName = trim((string)($socialUser['display_name'] ?? ''));
                                        if ($socialName === '') {
                                            $socialName = (string)$socialUser['username'];
                                        }
                                        ?>

                                        <li>
                                            <a href="/?page=profile&amp;user=<?= e(rawurlencode((string)$socialUser['username'])) ?>">
                                                <span class="profile-social-avatar">
                                                    <?php if ($socialAvatarValid): ?>
                                                        <img src="<?= e($socialAvatar) ?>" alt="">
                                                    <?php else: ?>
                                                        <svg viewBox="0 0 24 24" aria-hidden="true">
                                                            <circle cx="12" cy="8" r="4"></circle>
                                                            <path d="M4 21a8 8 0 0 1 16 0"></path>
                                                        </svg>
                                                    <?php endif; ?>
                                                </span>

                                                <span class="profile-social-user-info">
                                                    <strong><?= e($socialName) ?></strong>
                                                    <small>@<?= e($socialUser['username']) ?></small>
                                                </span>
                                            </a>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php else: ?>
                                <p class="profile-empty">Todavía no hay usuarios en esta lista.</p>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </dialog>
        </section>

        <section class="profile-activity">
            <div class="profile-tabs" role="tablist" aria-label="Actividad del perfil">
                <?php foreach ($profileTabs as $tabId => $tab): ?>
                    <a
                        class="profile-tab <?= $profileTab === $tabId ? 'active' : '' ?>"
                        href="/?page=profile<?= $isOwnProfile ? '' : '&amp;user=' . e(rawurlencode($username)) ?>&amp;tab=<?= e($tabId) ?>"
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
                    require APP_ROOT . '/Views/Content/cards.php';
                    ?>
                <?php else: ?>
                    <div class="empty-state">
                        <span class="empty-icon">✦</span>

                        <?php if ($profileTab === 'posts'): ?>
                            <h3>
                                <?= $isOwnProfile
                                    ? 'Todavía no tenés publicaciones'
                                    : 'Todavía no hay publicaciones' ?>
                            </h3>
                            <p>Las publicaciones de este usuario aparecerán acá.</p>
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
            <p>No pudimos cargar los datos de esta cuenta.</p>
            <a class="button button-primary" href="/">Volver al inicio</a>
        </div>
    <?php endif; ?>
</section>
