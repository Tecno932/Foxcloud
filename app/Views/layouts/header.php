<header class="site-header">
    <div class="nav-wrap">
        <a class="brand" href="/">
            <span class="brand-mark">F</span>
            <span>FOX<span class="brand-accent">CRAFT</span></span>
        </a>

        <button
            class="menu-toggle"
            type="button"
            aria-label="Abrir menú"
            aria-expanded="false"
            aria-controls="main-nav"
        >
            <span></span>
            <span></span>
            <span></span>
        </button>

        <nav class="main-nav" id="main-nav" aria-label="Navegación principal">
            <a href="/" class="<?= $page === 'home' ? 'active' : '' ?>">Inicio</a>
            <a href="#categorias">Explorar</a>
            <a href="/?page=mods">Mods</a>
            <a href="/?page=maps">Mapas</a>
            <a href="/?page=texture-packs">Texture</a>
            <a href="/?page=skins">Skin</a>
            <a href="/?page=holographic">Holographic</a>

            <div class="mobile-account">
                <?php if (!empty($_SESSION['user_id'])): ?>
                    <?php
                    $mobileAvatar = trim((string)($profileData['avatar_path'] ?? ''));
                    $mobileUsername = (string)($_SESSION['username'] ?? 'Usuario');
                    $mobileDisplayName = trim((string)($profileData['display_name'] ?? ''));
                    $mobileAccountName = $mobileDisplayName !== '' ? $mobileDisplayName : $mobileUsername;

                    $mobileAvatarValid = $mobileAvatar !== '' &&
                        (str_starts_with($mobileAvatar, '/') || str_starts_with($mobileAvatar, 'https://'));
                    ?>

                    <a class="mobile-account-link" href="/?page=profile">
                        <span class="mobile-account-avatar">
                            <?php if ($mobileAvatarValid): ?>
                                <img src="<?= e($mobileAvatar) ?>" alt="">
                            <?php else: ?>
                                <svg viewBox="0 0 24 24" aria-hidden="true">
                                    <circle cx="12" cy="8" r="4"></circle>
                                    <path d="M4 21a8 8 0 0 1 16 0"></path>
                                </svg>
                            <?php endif; ?>
                        </span>
                        <span class="mobile-account-copy">
                            <strong><?= e($mobileAccountName) ?></strong>
                            <small>Ver perfil</small>
                        </span>
                    </a>
                <?php else: ?>
                    <a class="mobile-account-link" href="/?page=login">
                        <span class="mobile-account-avatar">
                            <svg viewBox="0 0 24 24" aria-hidden="true">
                                <circle cx="12" cy="8" r="4"></circle>
                                <path d="M4 21a8 8 0 0 1 16 0"></path>
                            </svg>
                        </span>
                        <span class="mobile-account-copy">
                            <strong>Iniciar sesión</strong>
                            <small>Ingresá a tu cuenta</small>
                        </span>
                    </a>
                <?php endif; ?>
            </div>
        </nav>

        <div class="nav-actions">
            <?php if (!empty($_SESSION['user_id'])): ?>
                <a class="button button-quiet" href="/?page=profile">
                    <?= e((string)$_SESSION['username']) ?>
                </a>

                <form method="post" action="/?page=logout" class="inline-form">
                    <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token']) ?>">
                    <button
                        class="button button-quiet logout-button"
                        type="submit"
                        name="logout"
                        value="1"
                        aria-label="Cerrar sesión"
                        title="Cerrar sesión"
                    >
                        <svg viewBox="0 0 24 24" aria-hidden="true">
                            <path d="M10 17l5-5-5-5"></path>
                            <path d="M15 12H3"></path>
                            <path d="M12 3h6a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-6"></path>
                        </svg>
                    </button>
                </form>
            <?php else: ?>
                <a class="button button-quiet login-link" href="/?page=login">Ingresar</a>
                <a class="button button-primary" href="/?page=register">
                    Crear cuenta <span>↗</span>
                </a>
            <?php endif; ?>
        </div>
    </div>

    <div class="mobile-menu-backdrop" data-menu-close></div>
</header>