<?php

define('PROJECT_ROOT', dirname(__DIR__));
define('APP_ROOT', PROJECT_ROOT . '/app');

require_once PROJECT_ROOT . '/vendor/autoload.php';
require_once APP_ROOT . '/Core/helpers.php';

// ============================================================
// PROTECCIÓN DE SESIONES
// ============================================================

ini_set('session.use_strict_mode', '1');
ini_set('session.use_only_cookies', '1');

$isHttps = isset($_SERVER['HTTPS'])
    && $_SERVER['HTTPS'] !== ''
    && strtolower((string) $_SERVER['HTTPS']) !== 'off';

session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'secure' => $isHttps,
    'httponly' => true,
    'samesite' => 'Lax',
]);

session_start();

// ============================================================
// CABECERAS DE SEGURIDAD
// ============================================================

header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('X-Frame-Options: DENY');

header(
    'Permissions-Policy: camera=(), microphone=(), geolocation=(), payment=(), usb=()'
);

$csp = "default-src 'self'; "
    . "base-uri 'self'; "
    . "object-src 'none'; "
    . "frame-ancestors 'none'; "
    . "form-action 'self'; "
    . "script-src 'self' 'wasm-unsafe-eval' https://al5sm.com; "
    . "style-src 'self'; "
    . "img-src 'self' blob: https://foxcraft-web1-249i.vercel.app https://mosaicary.com; "
    . "font-src 'self'; "
    . "connect-src 'self' https://255md.com https://ay267.com http://ay267.com";

header('Content-Security-Policy: ' . $csp);

// ============================================================
// CONFIGURACIÓN DE PÁGINAS Y RUTAS
// ============================================================

$pages = require APP_ROOT . '/Config/pages.php';

$page = (string) ($_GET['page'] ?? 'home');

$routeContentId = 0;
$routeSlug = '';
$routeCategory = '';

$requestPath = parse_url(
    $_SERVER['REQUEST_URI'] ?? '/',
    PHP_URL_PATH
) ?: '/';

if ($requestPath === '/skins' || $requestPath === '/skins/') {
    $page = 'skins';
}

if (
    preg_match(
        '~^/([a-z0-9-]+)/([0-9]+)/([a-z0-9-]+)/?$~i',
        $requestPath,
        $routeMatch
    )
) {
    $routeCategory = strtolower($routeMatch[1]);
    $routeContentId = (int) $routeMatch[2];
    $routeSlug = $routeMatch[3];
    $page = 'content-detail';
}

if (!isset($pages[$page])) {
    http_response_code(404);
    $page = 'home';
}

[$title, $description] = $pages[$page];

// ============================================================
// TOKEN CSRF Y MENSAJES
// ============================================================

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$errors = [];

$success = (string) ($_SESSION['flash'] ?? '');
unset($_SESSION['flash']);

// ============================================================
// CONEXIÓN A LA BASE DE DATOS
// ============================================================

$pdo = null;
$dbError = false;

try {
    require_once APP_ROOT . '/Core/Database.php';

    $pdo = \FoxCraft\Core\Database::connection();
} catch (Throwable $exception) {
    error_log('FoxCraft database connection: ' . $exception->getMessage());
    $dbError = true;
}

// ============================================================
// CIERRE DE SESIÓN
// ============================================================

if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_POST['logout'])
    && hash_equals(
        (string) ($_SESSION['csrf_token'] ?? ''),
        (string) ($_POST['csrf_token'] ?? '')
    )
) {
    $_SESSION = [];

    session_regenerate_id(true);

    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

    header('Location: /', true, 303);
    exit;
}

// ============================================================
// DISCORD OAUTH2
// ============================================================
// Desarrollo local: http://localhost:8080
// Producción: configurar redirect_uri en /etc/foxcraft/discord.php
// y registrar la URL HTTPS en Discord Developer Portal.

// ------------------------------------------------------------
// INICIAR VINCULACIÓN DE CUENTA DISCORD
// ------------------------------------------------------------

if (
    $page === 'discord-link'
    && $_SERVER['REQUEST_METHOD'] === 'GET'
) {
    if (empty($_SESSION['user_id'])) {
        header('Location: /?page=login', true, 303);
        exit;
    }

    if (!$pdo) {
        $_SESSION['flash'] = 'No se pudo conectar con el servicio.';

        header('Location: /?page=discord&tab=account', true, 303);
        exit;
    }

    try {
        $discordConfigPath = '/etc/foxcraft/discord.php';

        if (!is_readable($discordConfigPath)) {
            throw new RuntimeException(
                'No se puede leer la configuración privada de Discord.'
            );
        }

        $discordConfig = require $discordConfigPath;

        if (!is_array($discordConfig)) {
            throw new RuntimeException(
                'La configuración de Discord no es válida.'
            );
        }

        require_once APP_ROOT . '/Core/DiscordOAuthService.php';

        $discordOAuth = new \FoxCraft\Core\DiscordOAuthService($discordConfig);

        $state = bin2hex(random_bytes(32));

        $_SESSION['discord_oauth'] = [
            'state' => $state,
            'user_id' => (int) $_SESSION['user_id'],
            'expires_at' => time() + 600,
        ];

        header(
            'Location: ' . $discordOAuth->getAuthorizationUrl($state),
            true,
            302
        );

        exit;
    } catch (Throwable $exception) {
        error_log('FoxCraft Discord OAuth start: ' . $exception->getMessage());

        unset($_SESSION['discord_oauth']);

        $_SESSION['flash'] = 'No se pudo iniciar la vinculación con Discord.';

        header('Location: /?page=discord&tab=account', true, 303);
        exit;
    }
}

// ------------------------------------------------------------
// CONSULTAR SERVIDORES DISCORD
// ------------------------------------------------------------

if (
    $page === 'discord-guilds'
    && $_SERVER['REQUEST_METHOD'] === 'GET'
) {
    if (empty($_SESSION['user_id'])) {
        header('Location: /?page=login', true, 303);
        exit;
    }

    if (!$pdo) {
        $_SESSION['flash'] = 'No se pudo conectar con el servicio.';

        header('Location: /?page=discord&tab=servers', true, 303);
        exit;
    }

    try {
        require_once APP_ROOT . '/Repositories/DiscordAccountRepository.php';
        require_once APP_ROOT . '/Core/DiscordOAuthService.php';

        $discordRepository = new \FoxCraft\Repositories\DiscordAccountRepository($pdo);

        $discordAccount = $discordRepository->findByUserId(
            (int) $_SESSION['user_id']
        );

        if (!$discordAccount) {
            $_SESSION['flash'] = 'Primero tenés que vincular tu cuenta Discord con FoxCraft.';

            header('Location: /?page=discord&tab=account', true, 303);
            exit;
        }

        $discordConfigPath = '/etc/foxcraft/discord.php';

        if (!is_readable($discordConfigPath)) {
            throw new RuntimeException(
                'No se puede leer la configuración privada de Discord.'
            );
        }

        $discordConfig = require $discordConfigPath;

        if (!is_array($discordConfig)) {
            throw new RuntimeException(
                'La configuración de Discord no es válida.'
            );
        }

        $discordOAuth = new \FoxCraft\Core\DiscordOAuthService($discordConfig);

        $state = bin2hex(random_bytes(32));

        $_SESSION['discord_guilds_oauth'] = [
            'state' => $state,
            'user_id' => (int) $_SESSION['user_id'],
            'expires_at' => time() + 600,
        ];

        header(
            'Location: ' . $discordOAuth->getAuthorizationUrl(
                $state,
                'identify guilds'
            ),
            true,
            302
        );

        exit;
    } catch (Throwable $exception) {
        error_log('FoxCraft Discord guild OAuth start: ' . $exception->getMessage());

        unset($_SESSION['discord_guilds_oauth']);

        $_SESSION['flash'] = 'No se pudo iniciar la consulta de servidores Discord.';

        header('Location: /?page=discord&tab=servers', true, 303);
        exit;
    }
}

// ------------------------------------------------------------
// CALLBACK DISCORD OAUTH2
// ------------------------------------------------------------

if (
    $page === 'discord-callback'
    && $_SERVER['REQUEST_METHOD'] === 'GET'
) {
    $receivedState = (string) ($_GET['state'] ?? '');

    $matchedSessionKey = null;
    $oauthSession = null;

    // Cada flujo OAuth consume su propio estado.
    foreach (['discord_oauth', 'discord_guilds_oauth'] as $sessionKey) {
        $candidate = $_SESSION[$sessionKey] ?? null;

        if (
            is_array($candidate)
            && isset($candidate['state'])
            && is_string($candidate['state'])
            && $candidate['state'] !== ''
            && $receivedState !== ''
            && hash_equals($candidate['state'], $receivedState)
        ) {
            $matchedSessionKey = $sessionKey;
            $oauthSession = $candidate;

            unset($_SESSION[$sessionKey]);

            break;
        }
    }

    $oauthUserId = is_array($oauthSession)
        ? (int) ($oauthSession['user_id'] ?? 0)
        : 0;

    $discordReturnTab = $matchedSessionKey === 'discord_guilds_oauth'
        ? 'servers'
        : 'account';

    $expiresAt = is_array($oauthSession)
        ? (int) ($oauthSession['expires_at'] ?? 0)
        : 0;

    if (
        $matchedSessionKey === null
        || $expiresAt < time()
        || $oauthUserId < 1
        || empty($_SESSION['user_id'])
        || (int) $_SESSION['user_id'] !== $oauthUserId
    ) {
        $_SESSION['flash'] = 'La autorización de Discord venció o no es válida. Intentá nuevamente.';

        header(
            'Location: /?page=discord&tab=' . $discordReturnTab,
            true,
            303
        );
        exit;
    }

    if (!$pdo) {
        $_SESSION['flash'] = 'No se pudo completar la autorización. El servicio no está disponible.';

        header(
            'Location: /?page=discord&tab=' . $discordReturnTab,
            true,
            303
        );
        exit;
    }

    if (
        isset($_GET['error'])
        || !isset($_GET['code'])
        || !is_string($_GET['code'])
        || $_GET['code'] === ''
    ) {
        $_SESSION['flash'] = 'Cancelaste o rechazaste la autorización de Discord.';

        header(
            'Location: /?page=discord&tab=' . $discordReturnTab,
            true,
            303
        );
        exit;
    }

    try {
        $discordConfigPath = '/etc/foxcraft/discord.php';

        if (!is_readable($discordConfigPath)) {
            throw new RuntimeException(
                'No se puede leer la configuración privada de Discord.'
            );
        }

        $discordConfig = require $discordConfigPath;

        if (!is_array($discordConfig)) {
            throw new RuntimeException(
                'La configuración de Discord no es válida.'
            );
        }

        require_once APP_ROOT . '/Core/DiscordOAuthService.php';
        require_once APP_ROOT . '/Repositories/DiscordAccountRepository.php';

        $discordOAuth = new \FoxCraft\Core\DiscordOAuthService($discordConfig);

        $discordRepository = new \FoxCraft\Repositories\DiscordAccountRepository($pdo);

        if ($matchedSessionKey === 'discord_guilds_oauth') {
            $result = $discordOAuth->getIdentityAndGuilds(
                (string) $_GET['code']
            );

            $identity = $result['identity'];
            $managedGuilds = $result['guilds'];

            $linkedAccount = $discordRepository->findByUserId($oauthUserId);

            if (
                !$linkedAccount
                || !isset($linkedAccount['discord_user_id'])
                || !hash_equals(
                    (string) $linkedAccount['discord_user_id'],
                    (string) $identity['id']
                )
            ) {
                unset($_SESSION['discord_managed_guilds']);

                throw new RuntimeException(
                    'La cuenta Discord autorizada no coincide con la cuenta vinculada a FoxCraft.'
                );
            }

            // Se guarda información pública, nunca el token OAuth.
            $_SESSION['discord_managed_guilds'] = [
                'user_id' => $oauthUserId,
                'discord_user_id' => (string) $identity['id'],
                'fetched_at' => time(),
                'guilds' => $managedGuilds,
            ];

            $_SESSION['flash'] = 'La lista de servidores administrables se actualizó correctamente.';
        } else {
            // Flujo original de vinculación.
            $identity = $discordOAuth->getIdentity(
                (string) $_GET['code']
            );

            $discordRepository->link(
                $oauthUserId,
                $identity['id'],
                $identity['username'],
                $identity['global_name'],
                $identity['avatar_hash']
            );

            $_SESSION['flash'] = 'Tu cuenta Discord fue vinculada correctamente.';
        }
    } catch (Throwable $exception) {
        error_log('FoxCraft Discord OAuth callback: ' . $exception->getMessage());

        $_SESSION['flash'] = $exception instanceof RuntimeException
            ? $exception->getMessage()
            : 'No se pudo completar la autorización con Discord.';
    }

    header(
        'Location: /?page=discord&tab=' . $discordReturnTab,
        true,
        303
    );
    exit;
}

// ============================================================
// VERIFICACIÓN DE CORREO ELECTRÓNICO
// ============================================================

if (
    $page === 'verify-email'
    && $_SERVER['REQUEST_METHOD'] === 'GET'
) {
    if (!$pdo) {
        http_response_code(503);
        $verificationResult = 'error';
    } else {
        try {
            require_once APP_ROOT . '/Repositories/EmailVerificationRepository.php';

            $verificationRepository = new \FoxCraft\Repositories\EmailVerificationRepository($pdo);

            $verificationToken = (string) ($_GET['token'] ?? '');

            if ($verificationRepository->verify($verificationToken)) {
                $verificationResult = 'success';

                unset($_SESSION['pending_verification_email']);
            } else {
                $verificationResult = 'invalid';
            }
        } catch (Throwable $exception) {
            error_log('FoxCraft email verification: ' . $exception->getMessage());

            $verificationResult = 'error';
        }
    }
}

// ============================================================
// PREPARAR DESCARGA
// ============================================================

if (
    $page === 'prepare-download'
    && $_SERVER['REQUEST_METHOD'] === 'GET'
) {
    $contentId = filter_var(
        $_GET['id'] ?? null,
        FILTER_VALIDATE_INT
    );

    if (!$contentId || $contentId < 1 || !$pdo) {
        http_response_code($pdo ? 404 : 503);
        exit('La descarga no está disponible.');
    }

    try {
        require_once APP_ROOT . '/Repositories/ContentRepository.php';

        $downloadRepository = new \FoxCraft\Repositories\ContentRepository($pdo);

        $downloadUrl = $downloadRepository->findPublishedDownloadUrl($contentId);

        $downloadParts = is_string($downloadUrl)
            ? parse_url($downloadUrl)
            : false;

        if (
            !$downloadUrl
            || !filter_var($downloadUrl, FILTER_VALIDATE_URL)
            || !is_array($downloadParts)
            || strtolower((string) ($downloadParts['scheme'] ?? '')) !== 'https'
            || empty($downloadParts['host'])
        ) {
            http_response_code(404);
            exit('La descarga no está disponible.');
        }

        $prepareDownloadId = $contentId;
    } catch (Throwable $exception) {
        error_log('FoxCraft prepare download: ' . $exception->getMessage());

        http_response_code(500);
        exit('No se pudo preparar la descarga.');
    }
}

// ============================================================
// EJECUTAR DESCARGA
// ============================================================

if (
    $page === 'download'
    && $_SERVER['REQUEST_METHOD'] === 'GET'
) {
    $contentId = filter_var(
        $_GET['id'] ?? null,
        FILTER_VALIDATE_INT
    );

    if (!$contentId || $contentId < 1 || !$pdo) {
        http_response_code($pdo ? 404 : 503);
        exit('La descarga no está disponible.');
    }

    try {
        require_once APP_ROOT . '/Repositories/ContentRepository.php';

        $downloadRepository = new \FoxCraft\Repositories\ContentRepository($pdo);

        $downloadUrl = $downloadRepository->findPublishedDownloadUrl($contentId);

        $downloadParts = is_string($downloadUrl)
            ? parse_url($downloadUrl)
            : false;

        if (
            !$downloadUrl
            || !filter_var($downloadUrl, FILTER_VALIDATE_URL)
            || !is_array($downloadParts)
            || strtolower((string) ($downloadParts['scheme'] ?? '')) !== 'https'
            || empty($downloadParts['host'])
        ) {
            http_response_code(404);
            exit('La descarga no está disponible.');
        }

        if (!$downloadRepository->incrementDownloads($contentId)) {
            http_response_code(404);
            exit('La descarga no está disponible.');
        }

        header('Location: ' . $downloadUrl, true, 302);
        exit;
    } catch (Throwable $exception) {
        error_log('FoxCraft download: ' . $exception->getMessage());

        http_response_code(500);
        exit('No se pudo iniciar la descarga.');
    }
}

// ============================================================
// AUTORIZACIÓN DE ADMINISTRADOR
// ============================================================

$isAdmin = false;

if ($pdo && !empty($_SESSION['user_id'])) {
    try {
        require_once APP_ROOT . '/Repositories/UserRepository.php';

        $userRepository = new \FoxCraft\Repositories\UserRepository($pdo);

        $isAdmin = $userRepository->isActiveAdmin(
            (int) $_SESSION['user_id']
        );
    } catch (Throwable $exception) {
        error_log('FoxCraft admin authorization: ' . $exception->getMessage());
    }
}

// ============================================================
// ADMINISTRACIÓN DE SERVIDORES DISCORD
// ============================================================

$discordManageData = null;
$discordManageError = null;

$isDiscordManageRequest = (
    $page === 'discord-manage'
    || (
        $page === 'discord'
        && ($_GET['tab'] ?? '') === 'admin'
        && isset($_GET['id'])
    )
);

if ($isDiscordManageRequest && $_SERVER['REQUEST_METHOD'] === 'GET') 
    {
    if (empty($_SESSION['user_id'])) {
        header('Location: /?page=login', true, 303);
        return;
    }

    if (!$pdo) {
        http_response_code(503);

        $discordManageError = 'El servicio no está disponible en este momento.';
    } else {
        try {
            require_once APP_ROOT . '/Repositories/DiscordAccountRepository.php';
            require_once APP_ROOT . '/Core/BotDatabase.php';

            $manageUserId = (int) $_SESSION['user_id'];

            $discordRepository = new \FoxCraft\Repositories\DiscordAccountRepository($pdo);

            $discordAccount = $discordRepository->findByUserId($manageUserId);

            if (!$discordAccount) {
                header('Location: /?page=discord&tab=account', true, 303);
                return;
            }

            $guildCache = $_SESSION['discord_managed_guilds'] ?? null;

            $validCache = (
                is_array($guildCache)
                && (int) ($guildCache['user_id'] ?? 0) === $manageUserId
                && isset($guildCache['discord_user_id'])
                && hash_equals(
                    (string) $discordAccount['discord_user_id'],
                    (string) $guildCache['discord_user_id']
                )
                && (int) ($guildCache['fetched_at'] ?? 0) >= time() - 900
                && is_array($guildCache['guilds'] ?? null)
            );

            if (!$validCache) {
                header('Location: /?page=discord&tab=servers', true, 303);
                return;
            }

            $requestedGuildId = (string) ($_GET['id'] ?? '');

            if (!preg_match('/\A[0-9]{17,20}\z/', $requestedGuildId)) {
                http_response_code(404);

                $discordManageError = 'El servidor solicitado no es válido.';
            } else {
                $managedGuild = null;

                foreach ($guildCache['guilds'] as $candidateGuild) {
                    if (
                        is_array($candidateGuild)
                        && isset($candidateGuild['id'])
                        && is_string($candidateGuild['id'])
                        && hash_equals($requestedGuildId, $candidateGuild['id'])
                    ) {
                        $managedGuild = $candidateGuild;
                        break;
                    }
                }

                if ($managedGuild === null) {
                    http_response_code(403);

                    $discordManageError = 'No tenés permisos para administrar este servidor.';
                } else {
                    $permissionsRaw = (string) ($managedGuild['permissions'] ?? '0');

                    $permissions = (
                        PHP_INT_SIZE >= 8
                        && preg_match('/\A[0-9]{1,18}\z/', $permissionsRaw)
                    ) ? (int) $permissionsRaw : 0;

                    $isGuildOwner = !empty($managedGuild['owner']);

                    $canManageGuild = (
                        ($permissions & 0x8) === 0x8
                        || ($permissions & 0x20) === 0x20
                    );

                    $botPdo = \FoxCraft\Core\BotDatabase::connection();

                    $guildStatement = $botPdo->prepare(
                        'SELECT guild_id, name, owner_id, icon, is_active, joined_at
                         FROM discord_guilds
                         WHERE guild_id = ?
                           AND is_active = 1
                         LIMIT 1'
                    );

                    $guildStatement->execute([$requestedGuildId]);

                    $guildRecord = $guildStatement->fetch(PDO::FETCH_ASSOC);

                    if (!$guildRecord) {
                        http_response_code(404);

                        $discordManageError = 'Foxcraftservice ya no está conectado a este servidor.';
                    } else {
                        $settingsStatement = $botPdo->prepare(
                            'SELECT language, timezone, updated_at
                             FROM guild_settings
                             WHERE guild_id = ?
                             LIMIT 1'
                        );

                        $settingsStatement->execute([$requestedGuildId]);

                        $guildSettings = $settingsStatement->fetch(PDO::FETCH_ASSOC) ?: [];

                        $discordManageData = [
                            'guild' => $guildRecord,

                            'settings' => [
                                'language' => (string) ($guildSettings['language'] ?? 'es'),
                                'timezone' => (string) ($guildSettings['timezone'] ?? 'America/Argentina/Buenos_Aires'),
                                'updated_at' => $guildSettings['updated_at'] ?? null,
                            ],

                            'role' => $isGuildOwner
                                ? 'Propietario'
                                : ($canManageGuild ? 'Administrador' : 'Sin permisos'),

                            'can_edit' => $isGuildOwner || $canManageGuild,

                            'managed_guild' => $managedGuild,
                        ];
                    }
                }
            }
        } catch (Throwable $exception) {
            error_log('FoxCraft Discord manage: ' . $exception->getMessage());

            http_response_code(503);

            $discordManageError = 'No se pudo cargar el panel del servidor. Intentá nuevamente más tarde.';
        }
    }
}

// ============================================================
// PROTECCIÓN DEL PANEL ADMINISTRATIVO
// ============================================================

if ($page === 'admin' && !$isAdmin) {
    if (empty($_SESSION['user_id'])) {
        header('Location: /?page=login', true, 303);
        exit;
    }

    http_response_code(403);
}

// ============================================================
// PROCESAMIENTO DE FORMULARIOS POST
// ============================================================

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = (string) ($_POST['csrf_token'] ?? '');

    if (
        !hash_equals(
            (string) ($_SESSION['csrf_token'] ?? ''),
            $token
        )
    ) {
        if ($isDiscordManageRequest) {
            $_SESSION['flash'] = 'El formulario venció. Recargá la página e intentá nuevamente.';

            $guildId = (string) ($_GET['id'] ?? '');

            $csrfReturnUrl = $page === 'discord'
                ? '/?page=discord&tab=admin&id=' . rawurlencode($guildId)
                : '/?page=discord-manage&id=' . rawurlencode($guildId);

            header('Location: ' . $csrfReturnUrl, true, 303);

            return;
        }

        $errors[] = 'El formulario venció. Recargá la página e intentá nuevamente.';
    } elseif (!$pdo) {
        $errors[] = 'El servicio no está disponible en este momento.';

    // --------------------------------------------------------
    // ACTUALIZAR CONFIGURACIÓN DEL SERVIDOR DISCORD
    // --------------------------------------------------------

    } elseif ($isDiscordManageRequest) {
        if (empty($_SESSION['user_id'])) {
            header('Location: /?page=login', true, 303);
            return;
        }

        $requestedGuildId = (string) ($_GET['id'] ?? '');

        $discordManageReturnUrl = $page === 'discord'
            ? '/?page=discord&tab=admin&id=' . rawurlencode($requestedGuildId)
            : '/?page=discord-manage&id=' . rawurlencode($requestedGuildId);

        if (!preg_match('/\A[0-9]{17,20}\z/', $requestedGuildId)) {
            $errors[] = 'El servidor seleccionado no es válido.';
        } else {
            try {
                require_once APP_ROOT . '/Repositories/DiscordAccountRepository.php';
                require_once APP_ROOT . '/Core/BotDatabase.php';

                $manageUserId = (int) $_SESSION['user_id'];

                $discordRepository = new \FoxCraft\Repositories\DiscordAccountRepository($pdo);

                $discordAccount = $discordRepository->findByUserId($manageUserId);

                if (!$discordAccount) {
                    $_SESSION['flash'] = 'Primero tenés que vincular tu cuenta Discord.';

                    header(
                        'Location: ' . ($page === 'discord'
                            ? '/?page=discord&tab=account'
                            : '/?page=discord-link'),
                        true,
                        303
                    );
                    return;
                }

                $guildCache = $_SESSION['discord_managed_guilds'] ?? null;

                $validCache = (
                    is_array($guildCache)
                    && (int) ($guildCache['user_id'] ?? 0) === $manageUserId
                    && isset($guildCache['discord_user_id'])
                    && hash_equals(
                        (string) $discordAccount['discord_user_id'],
                        (string) $guildCache['discord_user_id']
                    )
                    && (int) ($guildCache['fetched_at'] ?? 0) >= time() - 900
                    && is_array($guildCache['guilds'] ?? null)
                );

                if (!$validCache) {
                    $_SESSION['flash'] = 'La autorización de Discord venció. Actualizá tus servidores.';

                    header(
                        'Location: ' . ($page === 'discord'
                            ? '/?page=discord&tab=servers'
                            : '/?page=discord-guilds'),
                        true,
                        303
                    );
                    return;
                }

                $managedGuild = null;

                foreach ($guildCache['guilds'] as $candidateGuild) {
                    if (
                        is_array($candidateGuild)
                        && isset($candidateGuild['id'])
                        && is_string($candidateGuild['id'])
                        && hash_equals($requestedGuildId, $candidateGuild['id'])
                    ) {
                        $managedGuild = $candidateGuild;
                        break;
                    }
                }

                if ($managedGuild === null) {
                    http_response_code(403);

                    $errors[] = 'No tenés permisos para administrar este servidor.';
                } else {
                    $permissionsRaw = (string) ($managedGuild['permissions'] ?? '0');

                    $permissions = (
                        PHP_INT_SIZE >= 8
                        && preg_match('/\A[0-9]{1,18}\z/', $permissionsRaw)
                    ) ? (int) $permissionsRaw : 0;

                    $isGuildOwner = !empty($managedGuild['owner']);

                    $canManageGuild = (
                        ($permissions & 0x8) === 0x8
                        || ($permissions & 0x20) === 0x20
                    );

                    if (!$isGuildOwner && !$canManageGuild) {
                        http_response_code(403);

                        $errors[] = 'No tenés permisos suficientes para modificar este servidor.';
                    } else {
                        $language = (string) ($_POST['language'] ?? '');
                        $timezone = (string) ($_POST['timezone'] ?? '');

                        $allowedLanguages = ['es', 'en'];

                        $allowedTimezones = DateTimeZone::listIdentifiers(
                            DateTimeZone::ALL
                        );

                        $allowedTimezones[] = 'UTC';

                        if (!in_array($language, $allowedLanguages, true)) {
                            $errors[] = 'El idioma seleccionado no es válido.';
                        } elseif (!in_array($timezone, $allowedTimezones, true)) {
                            $errors[] = 'La zona horaria seleccionada no es válida.';
                        } else {
                            $botPdo = \FoxCraft\Core\BotDatabase::connection();

                            $guildStatement = $botPdo->prepare(
                                'SELECT guild_id
                                 FROM discord_guilds
                                 WHERE guild_id = ?
                                   AND is_active = 1
                                 LIMIT 1'
                            );

                            $guildStatement->execute([$requestedGuildId]);

                            if (!$guildStatement->fetch(PDO::FETCH_ASSOC)) {
                                $_SESSION['flash'] = 'Foxcraftservice ya no está conectado a ese servidor.';

                                header('Location: /?page=discord&tab=servers', true, 303);
                                return;
                            }

                            $botPdo->beginTransaction();

                            try {
                                $previousStatement = $botPdo->prepare(
                                    'SELECT language, timezone
                                     FROM guild_settings
                                     WHERE guild_id = ?
                                     LIMIT 1
                                     FOR UPDATE'
                                );

                                $previousStatement->execute([$requestedGuildId]);

                                $previousSettings = $previousStatement->fetch(PDO::FETCH_ASSOC) ?: [
                                    'language' => 'es',
                                    'timezone' => 'America/Argentina/Buenos_Aires',
                                ];

                                $updateStatement = $botPdo->prepare(
                                    'INSERT INTO guild_settings
                                        (guild_id, language, timezone)
                                     VALUES (?, ?, ?)
                                     ON DUPLICATE KEY UPDATE
                                        language = VALUES(language),
                                        timezone = VALUES(timezone)'
                                );

                                $updateStatement->execute([
                                    $requestedGuildId,
                                    $language,
                                    $timezone,
                                ]);

                                $auditDetails = json_encode([
                                    'before' => [
                                        'language' => (string) $previousSettings['language'],
                                        'timezone' => (string) $previousSettings['timezone'],
                                    ],
                                    'after' => [
                                        'language' => $language,
                                        'timezone' => $timezone,
                                    ],
                                ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);

                                $auditStatement = $botPdo->prepare(
                                    'INSERT INTO audit_logs
                                        (guild_id, actor_discord_id, action, target_id, details)
                                     VALUES (?, ?, ?, NULL, ?)'
                                );

                                $auditStatement->execute([
                                    $requestedGuildId,
                                    (string) $discordAccount['discord_user_id'],
                                    'guild.settings.updated',
                                    $auditDetails,
                                ]);

                                $botPdo->commit();

                                $_SESSION['flash'] = 'La configuración del servidor se guardó correctamente.';

                                header('Location: ' . $discordManageReturnUrl, true, 303);

                                return;
                            } catch (Throwable $exception) {
                                if ($botPdo->inTransaction()) {
                                    $botPdo->rollBack();
                                }

                                throw $exception;
                            }
                        }
                    }
                }
            } catch (Throwable $exception) {
                error_log('FoxCraft Discord settings update: ' . $exception->getMessage());

                $errors[] = 'No se pudo guardar la configuración del servidor.';
            }
        }

        if ($errors) {
            $_SESSION['flash'] = implode(' ', $errors);

            header('Location: ' . $discordManageReturnUrl, true, 303);

            return;
        }

    // --------------------------------------------------------
    // DESVINCULAR DISCORD
    // --------------------------------------------------------

    } elseif ($page === 'discord-unlink') {
        if (empty($_SESSION['user_id'])) {
            header('Location: /?page=login', true, 303);
            exit;
        }

        try {
            require_once APP_ROOT . '/Repositories/DiscordAccountRepository.php';

            $discordRepository = new \FoxCraft\Repositories\DiscordAccountRepository($pdo);

            if ($discordRepository->unlink((int) $_SESSION['user_id'])) {
                $_SESSION['flash'] = 'Tu cuenta Discord fue desvinculada.';
            } else {
                $_SESSION['flash'] = 'No tenías una cuenta Discord vinculada.';
            }

            header('Location: /?page=discord&tab=servers', true, 303);
            exit;
        } catch (Throwable $exception) {
            error_log('FoxCraft Discord unlink: ' . $exception->getMessage());

            $errors[] = 'No se pudo desvincular tu cuenta Discord.';
        }

    // --------------------------------------------------------
    // FAVORITOS
    // --------------------------------------------------------

    } elseif ($page === 'favorites') {
        if (empty($_SESSION['user_id'])) {
            header('Location: /?page=login', true, 303);
            exit;
        }

        $contentId = filter_var(
            $_POST['content_id'] ?? null,
            FILTER_VALIDATE_INT
        );

        $action = (string) ($_POST['favorite_action'] ?? '');

        if (!$contentId || $contentId < 1) {
            $errors[] = 'La publicación seleccionada no es válida.';
        } elseif (!in_array($action, ['add', 'remove'], true)) {
            $errors[] = 'La acción de favoritos no es válida.';
        } else {
            try {
                require_once APP_ROOT . '/Repositories/FavoriteRepository.php';

                $favoriteRepository = new \FoxCraft\Repositories\FavoriteRepository($pdo);

                if ($action === 'add') {
                    if ($favoriteRepository->isFavorite((int) $_SESSION['user_id'], $contentId)) {
                        $_SESSION['flash'] = 'Esta publicación ya está en tus favoritos.';
                    } elseif ($favoriteRepository->add((int) $_SESSION['user_id'], $contentId)) {
                        $_SESSION['flash'] = 'Publicación agregada a tus favoritos.';
                    } else {
                        $errors[] = 'No se pudo agregar la publicación. Puede que ya no esté disponible.';
                    }
                } else {
                    if ($favoriteRepository->remove((int) $_SESSION['user_id'], $contentId)) {
                        $_SESSION['flash'] = 'Publicación eliminada de tus favoritos.';
                    } else {
                        $_SESSION['flash'] = 'La publicación ya no estaba en tus favoritos.';
                    }
                }

                if (!$errors) {
                    $returnTo = (string) ($_POST['return_to'] ?? '');

                    if (
                        $returnTo === ''
                        || !str_starts_with($returnTo, '/')
                        || str_starts_with($returnTo, '//')
                        || str_contains($returnTo, "\r")
                        || str_contains($returnTo, "\n")
                        || str_contains($returnTo, '\\')
                    ) {
                        $returnTo = '/?page=favorites';
                    }

                    header('Location: ' . $returnTo, true, 303);
                    exit;
                }
            } catch (Throwable $exception) {
                error_log('FoxCraft favorites action: ' . $exception->getMessage());

                $errors[] = 'No se pudo actualizar tu lista de favoritos.';
            }
        }

    // --------------------------------------------------------
    // ME GUSTA
    // --------------------------------------------------------

    } elseif ($page === 'likes') {
        if (empty($_SESSION['user_id'])) {
            header('Location: /?page=login', true, 303);
            exit;
        }

        $contentId = filter_var(
            $_POST['content_id'] ?? null,
            FILTER_VALIDATE_INT
        );

        $action = (string) ($_POST['like_action'] ?? '');

        if (!$contentId || $contentId < 1) {
            $errors[] = 'La publicación seleccionada no es válida.';
        } elseif (!in_array($action, ['add', 'remove'], true)) {
            $errors[] = 'La acción de Me gusta no es válida.';
        } else {
            try {
                require_once APP_ROOT . '/Repositories/LikeRepository.php';

                $likeRepository = new \FoxCraft\Repositories\LikeRepository($pdo);

                if ($action === 'add') {
                    if ($likeRepository->isLiked((int) $_SESSION['user_id'], $contentId)) {
                        $_SESSION['flash'] = 'Ya te gusta esta publicación.';
                    } elseif ($likeRepository->add((int) $_SESSION['user_id'], $contentId)) {
                        $_SESSION['flash'] = 'Marcaste que te gusta esta publicación.';
                    } else {
                        $errors[] = 'No se pudo registrar tu Me gusta. Puede que la publicación ya no esté disponible.';
                    }
                } else {
                    if ($likeRepository->remove((int) $_SESSION['user_id'], $contentId)) {
                        $_SESSION['flash'] = 'Quitaste tu Me gusta de esta publicación.';
                    } else {
                        $_SESSION['flash'] = 'No habías marcado que te gusta esta publicación.';
                    }
                }

                if (!$errors) {
                    $returnTo = (string) ($_POST['return_to'] ?? '');

                    if (
                        $returnTo === ''
                        || !str_starts_with($returnTo, '/')
                        || str_starts_with($returnTo, '//')
                        || str_contains($returnTo, "\r")
                        || str_contains($returnTo, "\n")
                        || str_contains($returnTo, '\\')
                    ) {
                        $returnTo = '/';
                    }

                    header('Location: ' . $returnTo, true, 303);
                    exit;
                }
            } catch (Throwable $exception) {
                error_log('FoxCraft likes action: ' . $exception->getMessage());

                $errors[] = 'No se pudo actualizar tu Me gusta.';
            }
        }

    // --------------------------------------------------------
    // ACCIONES SOCIALES DEL PERFIL
    // --------------------------------------------------------

    } elseif ($page === 'profile') {
        if (empty($_SESSION['user_id'])) {
            header('Location: /?page=login', true, 303);
            exit;
        }

        $userId = (int) $_SESSION['user_id'];

        $targetUserId = filter_var(
            $_POST['target_user_id'] ?? null,
            FILTER_VALIDATE_INT
        );

        $action = (string) ($_POST['social_action'] ?? '');

        if (
            !$targetUserId
            || $targetUserId < 1
            || $targetUserId === $userId
        ) {
            $errors[] = 'El usuario seleccionado no es válido.';
        } elseif (!in_array($action, ['follow', 'unfollow', 'block', 'unblock'], true)) {
            $errors[] = 'La acción seleccionada no es válida.';
        } else {
            try {
                require_once APP_ROOT . '/Repositories/FollowRepository.php';
                require_once APP_ROOT . '/Repositories/BlockRepository.php';

                $followRepository = new \FoxCraft\Repositories\FollowRepository($pdo);
                $blockRepository = new \FoxCraft\Repositories\BlockRepository($pdo);

                if ($action === 'follow') {
                    if ($blockRepository->hasBlockBetween($userId, $targetUserId)) {
                        $errors[] = 'No podés seguir a este usuario porque existe un bloqueo.';
                    } elseif ($followRepository->follow($userId, $targetUserId)) {
                        $_SESSION['flash'] = 'Ahora seguís a este usuario.';
                    } else {
                        $_SESSION['flash'] = 'No se pudo seguir al usuario. Puede que ya lo sigas o que no esté disponible.';
                    }
                } elseif ($action === 'unfollow') {
                    $followRepository->unfollow($userId, $targetUserId);

                    $_SESSION['flash'] = 'Dejaste de seguir a este usuario.';
                } elseif ($action === 'block') {
                    if ($blockRepository->block($userId, $targetUserId)) {
                        $_SESSION['flash'] = 'Usuario bloqueado correctamente.';
                    } else {
                        $_SESSION['flash'] = 'Este usuario ya está bloqueado o no está disponible.';
                    }
                } elseif ($action === 'unblock') {
                    if ($blockRepository->unblock($userId, $targetUserId)) {
                        $_SESSION['flash'] = 'Usuario desbloqueado correctamente.';
                    } else {
                        $_SESSION['flash'] = 'Este usuario no estaba bloqueado.';
                    }
                }

                $returnTo = (string) ($_POST['return_to'] ?? '');

                if (
                    $returnTo === ''
                    || !str_starts_with($returnTo, '/')
                    || str_starts_with($returnTo, '//')
                    || str_contains($returnTo, "\r")
                    || str_contains($returnTo, "\n")
                    || str_contains($returnTo, '\\')
                ) {
                    $returnTo = '/?page=profile';
                }

                if (!$errors) {
                    header('Location: ' . $returnTo, true, 303);
                    exit;
                }
            } catch (Throwable $exception) {
                error_log('FoxCraft social action: ' . $exception->getMessage());

                $errors[] = 'No se pudo completar la acción social.';
            }
        }

    // --------------------------------------------------------
    // CREAR Y EDITAR CONTENIDO
    // --------------------------------------------------------

    } elseif (($page === 'admin' && $isAdmin) || $page === 'create-content') {
        $postedContent = [
            'id' => $page === 'create-content'
                ? 0
                : (int) ($_POST['content_id'] ?? 0),

            'title' => trim((string) ($_POST['title'] ?? '')),

            'introduction' => trim((string) ($_POST['introduction'] ?? '')),

            'slug' => trim(
                preg_replace(
                    '/[\s-]+/u',
                    '-',
                    strtolower(trim((string) ($_POST['slug'] ?? '')))
                ) ?? ''
            ),

            'category_id' => (int) ($_POST['category_id'] ?? 0),

            'description' => trim((string) ($_POST['description'] ?? '')),

            'image_url' => trim((string) ($_POST['image_url'] ?? '')),

            'download_url' => trim((string) ($_POST['download_url'] ?? '')),

            'platform' => (string) ($_POST['platform'] ?? 'both'),

            'game_versions' => trim((string) ($_POST['game_versions'] ?? '')),

            'status' => $page === 'create-content'
                ? 'draft'
                : (string) ($_POST['status'] ?? 'draft'),

            'is_featured' => $page === 'create-content'
                ? 0
                : (isset($_POST['is_featured']) ? 1 : 0),
        ];

        $editingContent = $postedContent;

        $editingContent['game_versions'] = json_encode(
            array_values(
                array_filter(
                    array_map('trim', explode(',', $postedContent['game_versions']))
                )
            ),
            JSON_UNESCAPED_UNICODE
        );

        $createdFiles = [];

        try {
            require_once APP_ROOT . '/Repositories/AdminContentRepository.php';
            require_once APP_ROOT . '/Repositories/CategoryRepository.php';

            $isPublicSubmission = $page === 'create-content';

            $sessionUserId = (int) ($_SESSION['user_id'] ?? 0);

            $userId = $sessionUserId > 0
                ? $sessionUserId
                : null;

            $contentId = $postedContent['id'];
            $categoryId = $postedContent['category_id'];
            $title = $postedContent['title'];
            $slug = $postedContent['slug'];
            $description = $postedContent['description'];
            $introduction = trim(strip_tags($postedContent['introduction']));

            // Configuración HTMLPurifier.
            $config = \HTMLPurifier_Config::createDefault();

            $config->set('Core.Encoding', 'UTF-8');
            $config->set('HTML.Doctype', 'HTML 4.01 Transitional');

            $config->set('HTML.Allowed', implode(',', [
                'p[style]',
                'br',
                'strong',
                'b',
                'em',
                'i',
                'u',
                's',
                'h1',
                'h2',
                'h3',
                'h4',
                'ul',
                'ol',
                'li',
                'blockquote',
                'a[href|title|target]',
                'span[style]',
                'div[style]',
                'details',
                'summary',
            ]));

            $config->set('CSS.AllowedProperties', [
                'color',
                'background-color',
                'font-family',
                'font-size',
                'text-align',
            ]);

            $config->set('Attr.AllowedFrameTargets', ['_blank']);
            $config->set('HTML.Nofollow', true);

            $config->set(
                'Cache.SerializerPath',
                PROJECT_ROOT . '/var/cache/htmlpurifier'
            );

            $purifier = new \HTMLPurifier($config);

            $description = $purifier->purify($description);

            $imageUrl = $isPublicSubmission
                ? null
                : $postedContent['image_url'];

            $downloadUrl = $postedContent['download_url'];
            $platform = $postedContent['platform'];
            $status = $postedContent['status'];

            if (!$isPublicSubmission && $userId <= 0) {
                throw new RuntimeException('Tu sesión no es válida.');
            }

            if ($contentId < 0) {
                throw new RuntimeException('La publicación seleccionada no es válida.');
            }

            if ($title === '' || mb_strlen($title) > 180) {
                throw new RuntimeException('El título es obligatorio y admite hasta 180 caracteres.');
            }

            if (!preg_match('/\A[a-z0-9-]{1,200}\z/', $slug)) {
                throw new RuntimeException('El slug solo puede contener letras minúsculas, números y guiones.');
            }

            if ($description === '') {
                throw new RuntimeException('La descripción es obligatoria.');
            }

            $categoryRepository = new \FoxCraft\Repositories\CategoryRepository($pdo);

            if (!$categoryRepository->isActiveById($categoryId)) {
                throw new RuntimeException('Seleccioná una categoría válida.');
            }

            if (!in_array($platform, ['both', 'java', 'bedrock', 'other'], true)) {
                throw new RuntimeException('La plataforma seleccionada no es válida.');
            }

            if (!in_array($status, ['draft', 'published', 'archived'], true)) {
                throw new RuntimeException('El estado seleccionado no es válido.');
            }

            if (mb_strlen($introduction) > 300) {
                throw new RuntimeException('La introducción admite hasta 300 caracteres.');
            }

            if ($isPublicSubmission && $introduction === '') {
                throw new RuntimeException('La introducción es obligatoria.');
            }

            // Validar URL de descarga.
            if ($downloadUrl !== '') {
                $parts = parse_url($downloadUrl);

                if (
                    !filter_var($downloadUrl, FILTER_VALIDATE_URL)
                    || !is_array($parts)
                    || strtolower((string) ($parts['scheme'] ?? '')) !== 'https'
                ) {
                    throw new RuntimeException('La URL de descarga debe ser HTTPS.');
                }
            } else {
                $downloadUrl = null;
            }

            // Validar URL o ruta de imagen existente.
            if ($imageUrl !== null && $imageUrl !== '') {
                $parts = parse_url($imageUrl);

                if (filter_var($imageUrl, FILTER_VALIDATE_URL)) {
                    if (
                        !is_array($parts)
                        || strtolower((string) ($parts['scheme'] ?? '')) !== 'https'
                    ) {
                        throw new RuntimeException('La imagen externa debe utilizar HTTPS.');
                    }
                } elseif (
                    !str_starts_with($imageUrl, '/assets/images/')
                    || str_contains($imageUrl, '..')
                    || str_contains($imageUrl, '\\')
                ) {
                    throw new RuntimeException('La ruta de imagen local no es válida.');
                }
            } else {
                $imageUrl = null;
            }

            // ------------------------------------------------
            // PROCESAR IMAGEN SUBIDA
            // ------------------------------------------------

            if (
                isset($_FILES['image_file'])
                && $_FILES['image_file']['error'] !== UPLOAD_ERR_NO_FILE
            ) {
                $file = $_FILES['image_file'];

                if ($file['error'] !== UPLOAD_ERR_OK) {
                    throw new RuntimeException('No se pudo recibir la imagen.');
                }

                if ($file['size'] <= 0 || $file['size'] > 2 * 1024 * 1024) {
                    throw new RuntimeException('La imagen debe pesar como máximo 2 MB.');
                }

                $finfo = new \finfo(FILEINFO_MIME_TYPE);

                $mime = $finfo->file($file['tmp_name']);

                $extensions = [
                    'image/jpeg' => 'jpg',
                    'image/png' => 'png',
                    'image/webp' => 'webp',
                ];

                if (!isset($extensions[$mime])) {
                    throw new RuntimeException('Formato no permitido. Usá JPG, PNG o WebP.');
                }

                $imageInfo = getimagesize($file['tmp_name']);

                if (
                    !$imageInfo
                    || $imageInfo[0] > 4096
                    || $imageInfo[1] > 4096
                    || ($imageInfo[0] * $imageInfo[1]) > 16000000
                    || $imageInfo['mime'] !== $mime
                ) {
                    throw new RuntimeException('La imagen tiene dimensiones o contenido no válido.');
                }

                $imageDirectory = PROJECT_ROOT . '/public/assets/images/content';
                $webpDirectory = PROJECT_ROOT . '/public/assets/images-webp/content';

                if (!is_dir($imageDirectory) && !mkdir($imageDirectory, 0755, true)) {
                    throw new RuntimeException('No se pudo crear la carpeta de imágenes.');
                }

                if (!is_dir($webpDirectory) && !mkdir($webpDirectory, 0755, true)) {
                    throw new RuntimeException('No se pudo crear la carpeta de imágenes WebP.');
                }

                $filename = bin2hex(random_bytes(16));

                $extension = $extensions[$mime];

                $originalPath = $imageDirectory . '/' . $filename . '.' . $extension;

                $imageUrl = '/assets/images/content/' . $filename . '.' . $extension;

                if (!move_uploaded_file($file['tmp_name'], $originalPath)) {
                    throw new RuntimeException('No se pudo guardar la imagen original.');
                }

                $createdFiles[] = $originalPath;

                // Generar versión WebP para JPG y PNG.
                if ($mime !== 'image/webp') {
                    $source = match ($mime) {
                        'image/jpeg' => imagecreatefromjpeg($originalPath),
                        'image/png' => imagecreatefrompng($originalPath),
                        default => false,
                    };

                    if (!$source) {
                        throw new RuntimeException('No se pudo procesar la imagen.');
                    }

                    if ($mime === 'image/png') {
                        imagealphablending($source, false);
                        imagesavealpha($source, true);
                    }

                    $webpPath = $webpDirectory . '/' . $filename . '.webp';

                    $createdFiles[] = $webpPath;

                    $webpSaved = imagewebp($source, $webpPath, 82);

                    imagedestroy($source);

                    if (!$webpSaved) {
                        throw new RuntimeException('No se pudo generar la versión WebP.');
                    }
                }
            }

            // ------------------------------------------------
            // VALIDACIONES FINALES Y GUARDADO
            // ------------------------------------------------

            if ($isPublicSubmission && $imageUrl === null) {
                throw new RuntimeException('Tenés que subir una imagen de portada.');
            }

            // Evitar duplicados de slug dentro de la categoría.
            $duplicate = $pdo->prepare(
                'SELECT id
                 FROM contents
                 WHERE category_id = :category_id
                   AND slug = :slug
                   AND id <> :content_id
                 LIMIT 1'
            );

            $duplicate->execute([
                'category_id' => $categoryId,
                'slug' => $slug,
                'content_id' => $contentId,
            ]);

            if ($duplicate->fetchColumn()) {
                throw new RuntimeException('Ya existe una publicación con ese slug en la categoría.');
            }

            $versions = array_values(array_unique(array_filter(
                array_map('trim', explode(',', $postedContent['game_versions'])),
                static fn (string $version): bool => $version !== ''
            )));

            $versionsJson = json_encode(
                $versions,
                JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
            );

            $repository = new \FoxCraft\Repositories\AdminContentRepository($pdo);

            $repository->save(
                $contentId,
                $categoryId,
                $userId,
                $title,
                $introduction,
                $slug,
                $description,
                $imageUrl,
                $downloadUrl,
                'download',
                $platform,
                $versionsJson,
                $postedContent['is_featured'],
                $status
            );

            $_SESSION['flash'] = $isPublicSubmission
                ? 'Tu propuesta fue enviada correctamente y será revisada por nuestro equipo.'
                : ($contentId > 0
                    ? 'Publicación actualizada correctamente.'
                    : 'Publicación creada correctamente.');

            $redirect = $isPublicSubmission
                ? '/?page=create-content'
                : '/?page=admin';

            header('Location: ' . $redirect, true, 303);
            exit;

        } catch (Throwable $exception) {
            foreach ($createdFiles as $createdFile) {
                if (is_file($createdFile)) {
                    @unlink($createdFile);
                }
            }

            error_log('FoxCraft admin content: ' . $exception->getMessage());

            $errors[] = $exception instanceof RuntimeException
                ? $exception->getMessage()
                : 'No se pudo guardar la publicación. Revisá los datos e intentá nuevamente.';
        }

    // --------------------------------------------------------
    // EDITAR PERFIL
    // --------------------------------------------------------

    } elseif ($page === 'edit-profile') {
        if (empty($_SESSION['user_id'])) {
            header('Location: /?page=login', true, 303);
            exit;
        }

        $userId = (int) $_SESSION['user_id'];

        $displayName = trim((string) ($_POST['display_name'] ?? ''));
        $bio = trim((string) ($_POST['bio'] ?? ''));
        $websiteUrl = trim((string) ($_POST['website_url'] ?? ''));

        $newAvatarPath = null;
        $oldAvatarPath = null;

        $avatarDirectory = PROJECT_ROOT . '/public/uploads/avatars';

        if (mb_strlen($displayName) > 60) {
            $errors[] = 'El nombre visible no puede superar los 60 caracteres.';
        }

        if (mb_strlen($bio) > 500) {
            $errors[] = 'La biografía no puede superar los 500 caracteres.';
        }

        if ($websiteUrl !== '') {
            $parts = parse_url($websiteUrl);

            if (
                !filter_var($websiteUrl, FILTER_VALIDATE_URL)
                || !is_array($parts)
                || strtolower((string) ($parts['scheme'] ?? '')) !== 'https'
                || empty($parts['host'])
            ) {
                $errors[] = 'El sitio web debe ser una URL válida que use HTTPS.';
            }
        }

        // Procesar avatar.
        $avatar = $_FILES['avatar'] ?? null;

        if (
            is_array($avatar)
            && ($avatar['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE
        ) {
            $uploadError = (int) ($avatar['error'] ?? UPLOAD_ERR_NO_FILE);

            if ($uploadError !== UPLOAD_ERR_OK) {
                $errors[] = match ($uploadError) {
                    UPLOAD_ERR_INI_SIZE,
                    UPLOAD_ERR_FORM_SIZE => 'La imagen supera el tamaño máximo permitido.',

                    UPLOAD_ERR_PARTIAL => 'La imagen no se recibió completa.',

                    UPLOAD_ERR_NO_TMP_DIR => 'No está disponible la carpeta temporal de PHP.',

                    UPLOAD_ERR_CANT_WRITE => 'No se pudo escribir el archivo subido.',

                    default => 'No se pudo recibir la imagen.',
                };
            } elseif (
                !isset($avatar['tmp_name'], $avatar['size'])
                || !is_uploaded_file((string) $avatar['tmp_name'])
                || (int) $avatar['size'] <= 0
                || (int) $avatar['size'] > 2 * 1024 * 1024
            ) {
                $errors[] = 'La imagen debe pesar como máximo 2 MB.';
            } else {
                $imageInfo = @getimagesize((string) $avatar['tmp_name']);

                $finfo = new \finfo(FILEINFO_MIME_TYPE);

                $mimeType = $finfo->file((string) $avatar['tmp_name']);

                $allowedImages = [
                    'image/jpeg' => 'jpg',
                    'image/png' => 'png',
                    'image/webp' => 'webp',
                ];

                if (
                    !is_array($imageInfo)
                    || !isset($allowedImages[$mimeType])
                    || ($imageInfo['mime'] ?? '') !== $mimeType
                ) {
                    $errors[] = 'La imagen debe ser JPG, PNG o WebP válida.';
                } elseif (
                    $imageInfo[0] < 1
                    || $imageInfo[1] < 1
                    || $imageInfo[0] > 2048
                    || $imageInfo[1] > 2048
                ) {
                    $errors[] = 'La imagen no puede superar los 2048 × 2048 píxeles.';
                } elseif (
                    (!is_dir($avatarDirectory) && !@mkdir($avatarDirectory, 0755, true))
                    || !is_writable($avatarDirectory)
                ) {
                    error_log(
                        'FoxCraft: avatar directory is missing or not writable: '
                        . $avatarDirectory
                    );

                    $errors[] = 'El servidor no puede guardar imágenes actualmente.';
                } else {
                    $safeUsername = preg_replace(
                        '/[^a-zA-Z0-9\_-]/',
                        '',
                        (string) ($_SESSION['username'] ?? 'user')
                    );

                    if ($safeUsername === '') {
                        $safeUsername = 'user';
                    }

                    $filename = $safeUsername
                        . '-'
                        . bin2hex(random_bytes(8))
                        . '.'
                        . $allowedImages[$mimeType];

                    $destination = $avatarDirectory . '/' . $filename;

                    if (!move_uploaded_file((string) $avatar['tmp_name'], $destination)) {
                        $errors[] = 'No se pudo guardar la imagen.';
                    } else {
                        $newAvatarPath = '/uploads/avatars/' . $filename;
                    }
                }
            }
        }

        if (!$errors) {
            try {
                require_once APP_ROOT . '/Repositories/ProfileRepository.php';

                $profileRepository = new \FoxCraft\Repositories\ProfileRepository($pdo);

                $currentProfile = $profileRepository->findByUserId($userId);

                $oldAvatarPath = trim(
                    (string) ($currentProfile['avatar_path'] ?? '')
                );

                $profileRepository->save(
                    $userId,
                    $displayName,
                    $bio,
                    $websiteUrl !== '' ? $websiteUrl : null,
                    $newAvatarPath
                );

                // Eliminar avatar anterior después de guardar el nuevo.
                if (
                    $newAvatarPath !== null
                    && str_starts_with($oldAvatarPath, '/uploads/avatars/')
                    && preg_match(
                        '/\A(?:[a-zA-Z0-9\_-]+-[a-f0-9]{16}|[a-f0-9]{48})\.(jpg|png|webp)\z/',
                        basename($oldAvatarPath)
                    )
                ) {
                    $oldAvatarFile = $avatarDirectory . '/' . basename($oldAvatarPath);

                    if (is_file($oldAvatarFile)) {
                        @unlink($oldAvatarFile);
                    }
                }

                $_SESSION['flash'] = 'Perfil actualizado correctamente.';

                header('Location: /?page=profile', true, 303);
                exit;

            } catch (Throwable $exception) {
                if ($newAvatarPath !== null) {
                    @unlink($avatarDirectory . '/' . basename($newAvatarPath));
                }

                error_log('FoxCraft profile save: ' . $exception->getMessage());

                $errors[] = 'No se pudo guardar el perfil. Intentá nuevamente.';
            }
        } elseif ($newAvatarPath !== null) {
            @unlink($avatarDirectory . '/' . basename($newAvatarPath));
        }

    // --------------------------------------------------------
    // REGISTRO
    // --------------------------------------------------------

    } elseif ($page === 'register') {
        $username = trim((string) ($_POST['username'] ?? ''));

        $email = strtolower(
            trim((string) ($_POST['email'] ?? ''))
        );

        $password = (string) ($_POST['password'] ?? '');

        if (!preg_match('/\A[a-zA-Z0-9_]{3,30}\z/', $username)) {
            $errors[] = 'El usuario debe tener entre 3 y 30 caracteres: letras, números o guion bajo.';
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 190) {
            $errors[] = 'Ingresá un correo electrónico válido.';
        }

        if (strlen($password) < 8 || strlen($password) > 4096) {
            $errors[] = 'La contraseña debe tener al menos 8 caracteres.';
        }

        if (!$errors) {
            $accountCreated = false;

            try {
                require_once APP_ROOT . '/Repositories/UserRegistrationRepository.php';
                require_once APP_ROOT . '/Core/MailService.php';

                $registrationRepository = new \FoxCraft\Repositories\UserRegistrationRepository($pdo);

                $verificationToken = $registrationRepository->register(
                    $username,
                    $email,
                    $password
                );

                $accountCreated = true;

                $_SESSION['pending_verification_email'] = $email;

                $appConfig = require APP_ROOT . '/Config/app.php';

                $baseUrl = rtrim((string) $appConfig['base_url'], '/');

                $verificationUrl = $baseUrl
                    . '/?page=verify-email&token='
                    . rawurlencode($verificationToken);

                $safeUsername = htmlspecialchars(
                    $username,
                    ENT_QUOTES | ENT_SUBSTITUTE,
                    'UTF-8'
                );

                $safeVerificationUrl = htmlspecialchars(
                    $verificationUrl,
                    ENT_QUOTES | ENT_SUBSTITUTE,
                    'UTF-8'
                );

                $htmlBody = "
                    <h2>Verificá tu correo electrónico</h2>
                    <p>Hola {$safeUsername},</p>
                    <p>Para activar tu cuenta de FoxCraft, hacé clic en el siguiente enlace:</p>
                    <p><a href=\"{$safeVerificationUrl}\">Verificar mi correo</a></p>
                    <p>Este enlace vence en 24 horas.</p>
                    <p>Si no creaste esta cuenta, podés ignorar este mensaje.</p>
                ";

                $textBody = "Hola {$username},\n\n"
                    . "Para activar tu cuenta de FoxCraft, visitá este enlace:\n"
                    . "{$verificationUrl}\n\n"
                    . "Este enlace vence en 24 horas.\n";

                $mailService = new \FoxCraft\Core\MailService();

                $mailService->send(
                    $email,
                    'Verificá tu correo - FoxCraft',
                    $htmlBody,
                    $textBody
                );

                $_SESSION['pending_verification_email'] = $email;
                $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

                header('Location: /?page=verify-pending', true, 303);
                exit;

            } catch (\DomainException $exception) {
                $errors[] = $exception->getMessage();

            } catch (Throwable $exception) {
                error_log('FoxCraft registration: ' . $exception->getMessage());

                if ($accountCreated) {
                    $_SESSION['flash'] = 'Tu cuenta fue creada, pero no pudimos enviar el correo. Podés solicitar otro enlace desde aquí.';

                    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

                    header('Location: /?page=verify-pending', true, 303);
                    exit;
                }

                $errors[] = 'No se pudo completar el registro. Contactá con soporte si tu cuenta ya fue creada.';
            }
        }

    // --------------------------------------------------------
    // REENVIAR VERIFICACIÓN
    // --------------------------------------------------------

    } elseif ($page === 'verify-pending') {
        $email = (string) ($_SESSION['pending_verification_email'] ?? '');

        $_SESSION['flash'] = 'Si tu cuenta todavía necesita verificación, recibirás un nuevo enlace en tu correo.';

        if ($email !== '') {
            try {
                require_once APP_ROOT . '/Repositories/EmailVerificationRepository.php';
                require_once APP_ROOT . '/Core/MailService.php';

                $verificationRepository = new \FoxCraft\Repositories\EmailVerificationRepository($pdo);

                $verificationData = $verificationRepository->resend($email);

                if ($verificationData !== null) {
                    $appConfig = require APP_ROOT . '/Config/app.php';

                    $baseUrl = rtrim((string) $appConfig['base_url'], '/');

                    $verificationUrl = $baseUrl
                        . '/?page=verify-email&token='
                        . rawurlencode($verificationData['token']);

                    $safeUsername = htmlspecialchars(
                        $verificationData['username'],
                        ENT_QUOTES | ENT_SUBSTITUTE,
                        'UTF-8'
                    );

                    $safeVerificationUrl = htmlspecialchars(
                        $verificationUrl,
                        ENT_QUOTES | ENT_SUBSTITUTE,
                        'UTF-8'
                    );

                    $htmlBody = "
                        <h2>Verificá tu correo electrónico</h2>
                        <p>Hola {$safeUsername},</p>
                        <p>Solicitaste un nuevo enlace para activar tu cuenta de FoxCraft.</p>
                        <p><a href=\"{$safeVerificationUrl}\">Verificar mi correo</a></p>
                        <p>Este enlace vence en 24 horas.</p>
                        <p>Si no realizaste esta solicitud, podés ignorar este mensaje.</p>
                    ";

                    $textBody = "Hola {$verificationData['username']},\n\n"
                        . "Para activar tu cuenta de FoxCraft, visitá este enlace:\n"
                        . "{$verificationUrl}\n\n"
                        . "Este enlace vence en 24 horas.\n";

                    $mailService = new \FoxCraft\Core\MailService();

                    $mailService->send(
                        $verificationData['email'],
                        'Nuevo enlace de verificación - FoxCraft',
                        $htmlBody,
                        $textBody
                    );
                }
            } catch (Throwable $exception) {
                error_log('FoxCraft resend verification: ' . $exception->getMessage());
            }
        }

        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

        header('Location: /?page=verify-pending', true, 303);
        exit;

    // --------------------------------------------------------
    // INICIAR SESIÓN
    // --------------------------------------------------------

    } elseif ($page === 'login') {
        $identity = trim((string) ($_POST['identity'] ?? ''));

        $password = (string) ($_POST['password'] ?? '');

        try {
            require_once APP_ROOT . '/Repositories/AuthenticationRepository.php';

            $authenticationRepository = new \FoxCraft\Repositories\AuthenticationRepository($pdo);

            $authentication = $authenticationRepository->authenticate(
                $identity,
                $password
            );

            if (!$authentication['success']) {
                if ($authentication['reason'] === 'inactive_account') {
                    $errors[] = 'Esta cuenta no está habilitada para iniciar sesión.';
                } else {
                    $errors[] = 'Usuario, correo o contraseña incorrectos.';
                }
            } else {
                $user = $authentication['user'];

                session_regenerate_id(true);

                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

                header('Location: /?page=profile', true, 303);
                exit;
            }
        } catch (Throwable $exception) {
            error_log('FoxCraft login: ' . $exception->getMessage());

            $errors[] = 'No se pudo iniciar sesión en este momento.';
        }

    // --------------------------------------------------------
    // RECUPERAR CONTRASEÑA
    // --------------------------------------------------------

    } elseif ($page === 'forgot-password') {
        $email = trim((string) ($_POST['email'] ?? ''));

        if (
            $email === ''
            || strlen($email) > 190
            || !filter_var($email, FILTER_VALIDATE_EMAIL)
        ) {
            $errors[] = 'Ingresá un correo electrónico válido.';
        } else {
            try {
                require_once APP_ROOT . '/Repositories/PasswordResetRepository.php';
                require_once APP_ROOT . '/Core/MailService.php';

                $passwordResetRepository = new \FoxCraft\Repositories\PasswordResetRepository($pdo);

                $resetData = $passwordResetRepository->create($email);

                if ($resetData) {
                    $baseUrl = rtrim(
                        (string) (require APP_ROOT . '/Config/app.php')['base_url'],
                        '/'
                    );

                    $resetUrl = $baseUrl
                        . '/?page=reset-password&token='
                        . rawurlencode($resetData['token']);

                    $safeUsername = htmlspecialchars(
                        $resetData['username'],
                        ENT_QUOTES | ENT_SUBSTITUTE,
                        'UTF-8'
                    );

                    $safeResetUrl = htmlspecialchars(
                        $resetUrl,
                        ENT_QUOTES | ENT_SUBSTITUTE,
                        'UTF-8'
                    );

                    $htmlBody = "
                        <h2>Recuperá tu contraseña</h2>
                        <p>Hola {$safeUsername},</p>
                        <p>Solicitaste cambiar la contraseña de tu cuenta FoxCraft.</p>
                        <p><a href=\"{$safeResetUrl}\">Cambiar mi contraseña</a></p>
                        <p>Este enlace vence en 30 minutos.</p>
                        <p>Si no realizaste esta solicitud, podés ignorar este mensaje.</p>
                    ";

                    $textBody = "Hola {$resetData['username']},\n\n"
                        . "Para cambiar tu contraseña de FoxCraft, visitá este enlace:\n"
                        . "{$resetUrl}\n\n"
                        . "Este enlace vence en 30 minutos.\n";

                    $mailService = new \FoxCraft\Core\MailService();

                    $mailService->send(
                        $resetData['email'],
                        'Recuperación de contraseña - FoxCraft',
                        $htmlBody,
                        $textBody
                    );
                }

                $_SESSION['flash'] = 'Si existe una cuenta activa y verificada con ese correo, recibirás un enlace para cambiar la contraseña.';

                $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

                header('Location: /?page=forgot-password', true, 303);
                exit;

            } catch (Throwable $exception) {
                error_log('FoxCraft password recovery: ' . $exception->getMessage());

                $_SESSION['flash'] = 'Si existe una cuenta activa y verificada con ese correo, recibirás un enlace para cambiar la contraseña.';

                $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

                header('Location: /?page=forgot-password', true, 303);
                exit;
            }
        }

    // --------------------------------------------------------
    // CAMBIAR CONTRASEÑA
    // --------------------------------------------------------

    } elseif ($page === 'reset-password') {
        $token = (string) ($_POST['token'] ?? '');

        $password = (string) ($_POST['password'] ?? '');

        $passwordConfirmation = (string) ($_POST['password_confirmation'] ?? '');

        if (strlen($password) < 8 || strlen($password) > 4096) {
            $errors[] = 'La contraseña debe tener entre 12 y 4096 caracteres.';
        } elseif ($password !== $passwordConfirmation) {
            $errors[] = 'Las contraseñas no coinciden.';
        } else {
            try {
                require_once APP_ROOT . '/Repositories/PasswordResetRepository.php';

                $passwordResetRepository = new \FoxCraft\Repositories\PasswordResetRepository($pdo);

                if ($passwordResetRepository->reset($token, $password)) {
                    $_SESSION['flash'] = 'Tu contraseña fue actualizada. Ya podés iniciar sesión.';

                    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

                    header('Location: /?page=login', true, 303);
                    exit;
                }

                $errors[] = 'El enlace no es válido o venció. Solicitá uno nuevo.';
            } catch (Throwable $exception) {
                error_log('FoxCraft password reset: ' . $exception->getMessage());

                $errors[] = 'No se pudo cambiar la contraseña en este momento.';
            }
        }

    // --------------------------------------------------------
    // LOGOUT
    // --------------------------------------------------------

    } elseif ($page === 'logout') {
        $errors[] = 'Acción no válida.';
    }
}

// ============================================================
// RUTA LOGOUT GET
// ============================================================

if (
    $page === 'logout'
    && $_SERVER['REQUEST_METHOD'] === 'GET'
) {
    $page = 'home';

    [$title, $description] = $pages[$page];
}

// ============================================================
// PROTECCIÓN DE PÁGINAS PRIVADAS
// ============================================================

if (
    in_array($page, ['edit-profile', 'favorites', 'discord'], true)
    && empty($_SESSION['user_id'])
) {
    header('Location: /?page=login', true, 303);
    exit;
}

// ============================================================
// INICIALIZACIÓN DE VARIABLES
// ============================================================

$categories = [];
$contents = [];
$adminContents = [];

$editingContent = null;
$categoryId = null;
$contentDetail = null;
$profileData = null;

$favoriteContents = [];

$discordAccount = null;
$discordManagedGuilds = [];
$discordInstalledGuilds = [];

$discordGuildsChecked = false;
$discordClientId = '';

$discordActiveTab = (string) ($_GET['tab'] ?? 'account');

if (!in_array($discordActiveTab, ['account', 'servers', 'admin'], true)) {
    $discordActiveTab = 'account';
}

$isFavorite = false;
$isLiked = false;

$profileContents = [];
$likedContents = [];

$isOwnProfile = false;
$isFollowing = false;

$isBlockedByMe = false;
$isBlockedByProfile = false;
$hasBlockBetween = false;

$followers = [];
$following = [];

$followersCount = 0;
$followingCount = 0;

// ============================================================
// PAGINACIÓN
// ============================================================

$itemsPerPage = 12;

$currentPage = filter_var(
    $_GET['p'] ?? 1,
    FILTER_VALIDATE_INT
);

$currentPage = ($currentPage !== false && $currentPage > 0)
    ? $currentPage
    : 1;

$totalContents = 0;
$totalPages = 1;

// ============================================================
// FILTROS Y BÚSQUEDA
// ============================================================

$skins = [];
$skinsError = false;

$searchQuery = trim((string) ($_GET['q'] ?? ''));

$filterPlatform = (string) ($_GET['platform'] ?? '');

$filterVersion = (string) ($_GET['version'] ?? '');

$filterCategory = filter_var(
    $_GET['category'] ?? '',
    FILTER_VALIDATE_INT
);

$filterCategory = ($filterCategory !== false && $filterCategory > 0)
    ? $filterCategory
    : null;

$searchQuery = mb_substr($searchQuery, 0, 180);

if (!in_array($filterPlatform, ['', 'java', 'bedrock'], true)) {
    $filterPlatform = '';
}

if (!in_array($filterVersion, ['', '1.20.8', '26+'], true)) {
    $filterVersion = '';
}

// ============================================================
// CARGA DE DATOS
// ============================================================

if ($pdo) {
    try {
        // ----------------------------------------------------
        // CATEGORÍAS
        // ----------------------------------------------------

        require_once APP_ROOT . '/Repositories/CategoryRepository.php';

        $categoryRepository = new \FoxCraft\Repositories\CategoryRepository($pdo);

        $categories = $page === 'create-content'
            ? $categoryRepository->findForSubmission()
            : $categoryRepository->findActive();

        // ----------------------------------------------------
        // CONTENIDO
        // ----------------------------------------------------

        require_once APP_ROOT . '/Repositories/ContentRepository.php';

        $contentRepository = new \FoxCraft\Repositories\ContentRepository($pdo);

        // ----------------------------------------------------
        // FAVORITOS
        // ----------------------------------------------------

        if ($page === 'favorites' && !empty($_SESSION['user_id'])) {
            require_once APP_ROOT . '/Repositories/FavoriteRepository.php';

            $favoriteRepository = new \FoxCraft\Repositories\FavoriteRepository($pdo);

            $favoriteContents = $favoriteRepository->findByUserId(
                (int) $_SESSION['user_id']
            );
        }

        // ----------------------------------------------------
        // PERFIL
        // ----------------------------------------------------

        if ($page === 'profile' || $page === 'edit-profile') {
            require_once APP_ROOT . '/Repositories/ProfileRepository.php';

            $profileRepository = new \FoxCraft\Repositories\ProfileRepository($pdo);

            $currentUserId = (int) ($_SESSION['user_id'] ?? 0);

            $requestedUsername = trim((string) ($_GET['user'] ?? ''));

            $isOwnProfile = false;

            if ($page === 'edit-profile') {
                $profileData = $currentUserId > 0
                    ? $profileRepository->findByUserId($currentUserId)
                    : null;

                $isOwnProfile = true;
            } elseif ($requestedUsername !== '') {
                $profileData = $profileRepository->findByUsername(
                    $requestedUsername
                );

                $isOwnProfile = $profileData !== null
                    && $currentUserId === (int) $profileData['id'];
            } else {
                $profileData = $currentUserId > 0
                    ? $profileRepository->findByUserId($currentUserId)
                    : null;

                $isOwnProfile = $profileData !== null;
            }

            if (!$profileData) {
                http_response_code(404);
            } elseif ($page === 'profile') {
                $profileUserId = (int) $profileData['id'];

                $profileContents = $contentRepository->findPublishedByAuthor(
                    $profileUserId
                );

                require_once APP_ROOT . '/Repositories/FollowRepository.php';
                require_once APP_ROOT . '/Repositories/BlockRepository.php';

                $followRepository = new \FoxCraft\Repositories\FollowRepository($pdo);

                $blockRepository = new \FoxCraft\Repositories\BlockRepository($pdo);

                $followersCount = $followRepository->countFollowers($profileUserId);

                $followingCount = $followRepository->countFollowing($profileUserId);

                $followers = $followRepository->findFollowers($profileUserId);

                $following = $followRepository->findFollowing($profileUserId);

                if (!$isOwnProfile && $currentUserId > 0) {
                    $isBlockedByMe = $blockRepository->isBlocked(
                        $currentUserId,
                        $profileUserId
                    );

                    $isBlockedByProfile = $blockRepository->isBlocked(
                        $profileUserId,
                        $currentUserId
                    );

                    $hasBlockBetween = $isBlockedByMe || $isBlockedByProfile;

                    if (!$hasBlockBetween) {
                        $isFollowing = $followRepository->isFollowing(
                            $currentUserId,
                            $profileUserId
                        );
                    }
                }

                // --------------------------------------------
                // DATOS DEL PROPIO PERFIL
                // --------------------------------------------

                if ($isOwnProfile) {
                    require_once APP_ROOT . '/Repositories/FavoriteRepository.php';
                    require_once APP_ROOT . '/Repositories/LikeRepository.php';
                    require_once APP_ROOT . '/Repositories/DiscordAccountRepository.php';

                    $favoriteRepository = new \FoxCraft\Repositories\FavoriteRepository($pdo);

                    $likeRepository = new \FoxCraft\Repositories\LikeRepository($pdo);

                    $discordRepository = new \FoxCraft\Repositories\DiscordAccountRepository($pdo);

                    $favoriteContents = $favoriteRepository->findByUserId(
                        $profileUserId
                    );

                    $likedContents = $likeRepository->findByUserId(
                        $profileUserId
                    );

                    $discordAccount = $discordRepository->findByUserId(
                        $profileUserId
                    );

                    $discordManagedGuilds = [];
                    $discordInstalledGuilds = [];
                    $discordGuildsChecked = false;
                    $discordClientId = '';

                    $guildCache = $_SESSION['discord_managed_guilds'] ?? null;

                    if (
                        is_array($guildCache)
                        && $discordAccount
                        && (int) ($guildCache['user_id'] ?? 0) === $profileUserId
                        && isset($guildCache['discord_user_id'])
                        && hash_equals(
                            (string) $discordAccount['discord_user_id'],
                            (string) $guildCache['discord_user_id']
                        )
                        && (int) ($guildCache['fetched_at'] ?? 0) >= time() - 900
                        && is_array($guildCache['guilds'] ?? null)
                    ) {
                        $discordManagedGuilds = $guildCache['guilds'];

                        $guildIds = [];

                        foreach ($discordManagedGuilds as $guild) {
                            if (
                                is_array($guild)
                                && isset($guild['id'])
                                && is_string($guild['id'])
                                && preg_match('/^[0-9]{17,20}$/', $guild['id'])
                            ) {
                                $guildIds[] = $guild['id'];
                            }
                        }

                        $guildIds = array_values(array_unique($guildIds));

                        try {
                            require_once APP_ROOT . '/Core/BotDatabase.php';

                            if ($guildIds !== []) {
                                $botPdo = \FoxCraft\Core\BotDatabase::connection();

                                $placeholders = implode(
                                    ',',
                                    array_fill(0, count($guildIds), '?')
                                );

                                $guildStatement = $botPdo->prepare(
                                    'SELECT guild_id
                                     FROM discord_guilds
                                     WHERE is_active = 1
                                       AND guild_id IN (' . $placeholders . ')'
                                );

                                $guildStatement->execute($guildIds);

                                foreach (
                                    $guildStatement->fetchAll(PDO::FETCH_COLUMN)
                                    as $installedGuildId
                                ) {
                                    $discordInstalledGuilds[(string) $installedGuildId] = true;
                                }
                            }

                            $discordGuildsChecked = true;
                        } catch (Throwable $exception) {
                            error_log(
                                'FoxCraft Discord guild installation check: '
                                . $exception->getMessage()
                            );
                        }
                    }

                    // Cargar Client ID de Discord.
                    try {
                        $discordConfigPath = '/etc/foxcraft/discord.php';

                        if (is_readable($discordConfigPath)) {
                            $discordConfig = require $discordConfigPath;

                            if (
                                is_array($discordConfig)
                                && isset($discordConfig['client_id'])
                                && is_string($discordConfig['client_id'])
                            ) {
                                $discordClientId = $discordConfig['client_id'];
                            }
                        }
                    } catch (Throwable $exception) {
                        error_log(
                            'FoxCraft Discord client configuration: '
                            . $exception->getMessage()
                        );
                    }
                }
            }
        }

        // ----------------------------------------------------
        // PANEL DISCORD
        // ----------------------------------------------------

        if ($page === 'discord' && !empty($_SESSION['user_id'])) {
            try {
                require_once APP_ROOT . '/Repositories/DiscordAccountRepository.php';

                $discordRepository = new \FoxCraft\Repositories\DiscordAccountRepository($pdo);

                $discordAccount = $discordRepository->findByUserId(
                    (int) $_SESSION['user_id']
                );

                if ($discordAccount) {
                    $guildCache = $_SESSION['discord_managed_guilds'] ?? null;

                    if (
                        is_array($guildCache)
                        && (int) ($guildCache['user_id'] ?? 0) === (int) $_SESSION['user_id']
                        && isset($guildCache['discord_user_id'])
                        && hash_equals(
                            (string) $discordAccount['discord_user_id'],
                            (string) $guildCache['discord_user_id']
                        )
                        && (int) ($guildCache['fetched_at'] ?? 0) >= time() - 900
                        && is_array($guildCache['guilds'] ?? null)
                    ) {
                        $discordManagedGuilds = $guildCache['guilds'];

                        $guildIds = [];

                        foreach ($discordManagedGuilds as $guild) {
                            if (
                                is_array($guild)
                                && isset($guild['id'])
                                && is_string($guild['id'])
                                && preg_match('/^[0-9]{17,20}$/', $guild['id'])
                            ) {
                                $guildIds[] = $guild['id'];
                            }
                        }

                        $guildIds = array_values(array_unique($guildIds));

                        require_once APP_ROOT . '/Core/BotDatabase.php';

                        if ($guildIds !== []) {
                            $botPdo = \FoxCraft\Core\BotDatabase::connection();

                            $placeholders = implode(
                                ',',
                                array_fill(0, count($guildIds), '?')
                            );

                            $guildStatement = $botPdo->prepare(
                                'SELECT guild_id
                                 FROM discord_guilds
                                 WHERE is_active = 1
                                   AND guild_id IN (' . $placeholders . ')'
                            );

                            $guildStatement->execute($guildIds);

                            foreach (
                                $guildStatement->fetchAll(PDO::FETCH_COLUMN)
                                as $installedGuildId
                            ) {
                                $discordInstalledGuilds[(string) $installedGuildId] = true;
                            }
                        }

                        $discordGuildsChecked = true;
                    }
                }

                // Client ID de Discord.
                $discordConfigPath = '/etc/foxcraft/discord.php';

                if (is_readable($discordConfigPath)) {
                    $discordConfig = require $discordConfigPath;

                    if (
                        is_array($discordConfig)
                        && isset($discordConfig['client_id'])
                        && is_string($discordConfig['client_id'])
                    ) {
                        $discordClientId = $discordConfig['client_id'];
                    }
                }
            } catch (Throwable $exception) {
                error_log('FoxCraft Discord dashboard: ' . $exception->getMessage());
            }
        }

        // ----------------------------------------------------
        // ADMINISTRACIÓN DE CONTENIDO
        // ----------------------------------------------------

        if ($page === 'admin' && $isAdmin) {
            $adminContents = $contentRepository->findAdminList();

            $editId = filter_var(
                $_GET['edit'] ?? '',
                FILTER_VALIDATE_INT
            );

            if ($editId && $editId > 0) {
                $editingContent = $contentRepository->findById($editId);

                if (!$editingContent) {
                    $errors[] = 'La publicación seleccionada no existe.';
                }
            }
        }

        // ----------------------------------------------------
        // IDENTIFICAR CATEGORÍA ACTUAL
        // ----------------------------------------------------

        if (in_array($page, array_column($categories, 'slug'), true)) {
            $categoryId = $categoryRepository->findActiveIdBySlug($page);
        }

        // Agrupaciones de categorías.
        $categoryGroups = [
            'mods' => ['mods', 'addons', 'modpacks'],

            'resource-packs' => [
                'resource-packs',
                'texture-packs',
                'shaders',
            ],
        ];

        if (isset($categoryGroups[$page])) {
            $activeCategoryIds = [];

            foreach ($categories as $category) {
                if (
                    in_array(
                        $category['slug'],
                        $categoryGroups[$page],
                        true
                    )
                ) {
                    $activeCategoryIds[] = (int) $category['id'];
                }
            }

            $categoryId = $activeCategoryIds;

            // Impedir filtros de categorías ajenas al catálogo.
            if (
                $filterCategory !== null
                && !in_array($filterCategory, $activeCategoryIds, true)
            ) {
                $filterCategory = null;
            }
        }

        // ----------------------------------------------------
        // FILTROS EXCLUSIVOS DE SKINS
        // ----------------------------------------------------

        if ($page === 'skins') {
            $filterPlatform = '';
            $filterVersion = '';
            $filterCategory = null;
        }

        $hasCatalogFilters = (
            $searchQuery !== ''
            || $filterPlatform !== ''
            || $filterVersion !== ''
            || $filterCategory !== null
        );

        // ----------------------------------------------------
        // CATÁLOGO DE SKINS EXTERNO
        // ----------------------------------------------------

        if ($page === 'skins') {
            require_once APP_ROOT . '/Core/MosaicaryClient.php';

            try {
                $mosaicaryClient = new \FoxCraft\Core\MosaicaryClient();

                $result = $mosaicaryClient->search(
                    $searchQuery,
                    $itemsPerPage,
                    $currentPage
                );

                $totalContents = $result['total'];

                $totalPages = max(
                    1,
                    (int) ceil($totalContents / $itemsPerPage)
                );

                if ($currentPage > $totalPages) {
                    $currentPage = $totalPages;

                    $result = $mosaicaryClient->search(
                        $searchQuery,
                        $itemsPerPage,
                        $currentPage
                    );
                }

                $skins = $result['skins'];

            } catch (Throwable $exception) {
                error_log('FoxCraft Mosaicary: ' . $exception->getMessage());

                $skinsError = true;
            }

        // ----------------------------------------------------
        // BÚSQUEDA EN CATÁLOGOS
        // ----------------------------------------------------

        } elseif (
            ($page === 'home' || $page === 'explorer' || $categoryId !== null)
            && $hasCatalogFilters
        ) {
            $catalogCategoryId = $filterCategory ?? $categoryId;

            $totalContents = $contentRepository->countSearchPublished(
                $searchQuery,
                $catalogCategoryId,
                $filterPlatform,
                $filterVersion,
                $page === 'explorer'
            );

            $totalPages = max(
                1,
                (int) ceil($totalContents / $itemsPerPage)
            );

            $currentPage = min($currentPage, $totalPages);

            $offset = ($currentPage - 1) * $itemsPerPage;

            $contents = $contentRepository->searchPublished(
                $searchQuery,
                $catalogCategoryId,
                $filterPlatform,
                $filterVersion,
                $itemsPerPage,
                $offset,
                $page === 'explorer'
            );

        // ----------------------------------------------------
        // EXPLORADOR SIN FILTROS
        // ----------------------------------------------------

        } elseif ($page === 'explorer') {
            $totalContents = $contentRepository->countSearchPublished(
                '',
                null,
                '',
                '',
                true
            );

            $totalPages = max(
                1,
                (int) ceil($totalContents / $itemsPerPage)
            );

            $currentPage = min($currentPage, $totalPages);

            $offset = ($currentPage - 1) * $itemsPerPage;

            $contents = $contentRepository->searchPublished(
                '',
                null,
                '',
                '',
                $itemsPerPage,
                $offset,
                true
            );

        // ----------------------------------------------------
        // PÁGINA DE INICIO
        // ----------------------------------------------------

        } elseif ($page === 'home') {
            $contents = [];

        // ----------------------------------------------------
        // CATÁLOGO DE CATEGORÍA
        // ----------------------------------------------------

        } elseif ($categoryId !== null) {
            $totalContents = $contentRepository->countPublishedByCategory(
                $categoryId
            );

            $totalPages = max(
                1,
                (int) ceil($totalContents / $itemsPerPage)
            );

            $currentPage = min($currentPage, $totalPages);

            $offset = ($currentPage - 1) * $itemsPerPage;

            $contents = $contentRepository->findPublishedByCategory(
                $categoryId,
                $itemsPerPage,
                $offset
            );
        }

        // ----------------------------------------------------
        // DETALLE DE CONTENIDO
        // ----------------------------------------------------

        if ($page === 'content-detail') {
            $contentDetail = $contentRepository->findPublishedDetail(
                $routeContentId,
                $routeSlug,
                $routeCategory
            );

            if ($contentDetail) {
                if (!empty($_SESSION['user_id'])) {
                    require_once APP_ROOT . '/Repositories/FavoriteRepository.php';

                    $favoriteRepository = new \FoxCraft\Repositories\FavoriteRepository($pdo);

                    $isFavorite = $favoriteRepository->isFavorite(
                        (int) $_SESSION['user_id'],
                        (int) $contentDetail['id']
                    );

                    require_once APP_ROOT . '/Repositories/LikeRepository.php';

                    $likeRepository = new \FoxCraft\Repositories\LikeRepository($pdo);

                    $isLiked = $likeRepository->isLiked(
                        (int) $_SESSION['user_id'],
                        (int) $contentDetail['id']
                    );
                }

                $title = (string) $contentDetail['title'];

                $description = (string) $contentDetail['description'];
            } else {
                http_response_code(404);

                $title = 'Contenido no encontrado';

                $description = 'Este contenido no existe o ya no está publicado.';
            }
        }

    } catch (Throwable $exception) {
        error_log('FoxCraft listing: ' . $exception->getMessage());

        $dbError = true;
    }
}


?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#09090b">
    <meta name="description" content="<?= e($description) ?>">

    <title><?= e($title) ?> | FoxCraft</title>

    <link rel="icon" type="image/svg+xml" href="/favicon.svg">
    <link rel="stylesheet" href="/assets/css/style.css">

    <?php if ($page === 'create-content'): ?>
        <link rel="stylesheet" href="/assets/vendor/quill/quill.snow.css">
        <script src="/assets/vendor/quill/quill.js" defer></script>
    <?php endif; ?>

    <?php if ($page === 'skins'): ?>
        <script src="/assets/vendor/skinview3d/skinview3d.bundle.js" defer></script>
        <script src="/assets/js/skin-viewer.js" defer></script>
    <?php endif; ?>

    <script src="/assets/js/app.js" defer></script>
</head>

<body<?= $page === 'prepare-download' && isset($prepareDownloadId) ? ' data-monetag-zone="11696491"' : '' ?>>

    <?php if ($page !== 'prepare-download'): ?>
        <?php require APP_ROOT . '/Views/layouts/header.php'; ?>
    <?php endif; ?>

    <main>

        <?php require APP_ROOT . '/Views/layouts/cookie-consent.php'; ?>
        <?php require APP_ROOT . '/Views/layouts/notices.php'; ?>

        <?php if ($page === 'prepare-download' && isset($prepareDownloadId)): ?>

            <?php require APP_ROOT . '/Views/Downloads/prepare.php'; ?>

        <?php elseif ($page === 'home'): ?>

            <section class="hero">
                <div class="hero-glow"></div>

                <div class="hero-content">
                    <span class="eyebrow">
                        <span class="status-dot"></span>
                        TU UNIVERSO DE MINECRAFT
                    </span>

                    <h1>
                        Todo el contenido<br>
                        de Minecraft en <span>un solo lugar.</span>
                    </h1>

                    <p>
                        Descubrí mods, mapas, texturas, skins, armor trims
                        y construcciones para Minecraft.
                    </p>

                    <div class="hero-actions">
                        <a class="button button-primary button-large" href="#contenido">
                            Explorar contenido <span>↘</span>
                        </a>

                        <a class="button button-outline button-large" href="/?page=mods">
                            Ver mods
                        </a>
                    </div>

                    <div class="hero-stats">
                        <div>
                            <strong><?= count($categories) ?></strong>
                            <span>Categorías</span>
                        </div>

                        <div>
                            <strong>∞</strong>
                            <span>Nuevas aventuras</span>
                        </div>
                    </div>
                </div>

                <div class="hero-art" aria-hidden="true">
                    <div class="art-orbit orbit-one"></div>
                    <div class="art-orbit orbit-two"></div>

                    <div class="art-core">
                        <span>F</span>
                    </div>

                    <div class="art-chip chip-one">MODS <b>↗</b></div>
                    <div class="art-chip chip-two">MAPS <b>✦</b></div>
                    <div class="art-chip chip-three">TEXTURES <b>▦</b></div>
                </div>
            </section>

            <?php require APP_ROOT . '/Views/Content/categories.php'; ?>

        <?php elseif ($page === 'explorer'): ?>

            <section class="page-banner">
                <span class="eyebrow">BIBLIOTECA FOXCRAFT</span>

                <h1>Explorá <span>Minecraft.</span></h1>

                <p>
                    Descubrí todos los mods, mapas y resource packs
                    disponibles en FoxCraft.
                </p>
            </section>

            <?php require APP_ROOT . '/Views/Content/search.php'; ?>

            <section class="section listing-section">
                <div class="section-heading">
                    <div>
                        <span class="eyebrow">CATÁLOGO COMPLETO</span>
                        <h2>Todo el <span>contenido</span></h2>
                    </div>

                    <span class="section-count">
                        <?= $totalContents ?> publicaciones
                    </span>
                </div>

                <?php require APP_ROOT . '/Views/Content/cards.php'; ?>
                <?php require APP_ROOT . '/Views/Content/pagination.php'; ?>
            </section>

        <?php elseif ($page === 'skins'): ?>

            <section class="page-banner">
                <span class="eyebrow">FOXCRAFT SKINS</span>

                <h1>Explorá <span>skins.</span></h1>

                <p>Encontrá tu próxima apariencia para Minecraft.</p>
            </section>

            <?php require APP_ROOT . '/Views/Content/skins-search.php'; ?>

            <section class="section listing-section">
                <div class="section-heading">
                    <div>
                        <span class="eyebrow">CATÁLOGO DE SKINS</span>
                        <h2>Todas las <span>skins</span></h2>
                    </div>

                    <span class="section-count">
                        <?= $totalContents ?> skins
                    </span>
                </div>

                <?php require APP_ROOT . '/Views/Content/skins-cards.php'; ?>
                <?php require APP_ROOT . '/Views/Content/pagination.php'; ?>
            </section>

        <?php elseif (isset($pages[$page]) && in_array($page, array_column($categories, 'slug'), true)): ?>

            <section class="page-banner">
                <span class="eyebrow">EXPLORÁ FOXCRAFT</span>

                <h1><?= e($title) ?></h1>
                <p><?= e($description) ?></p>

                <a class="text-link" href="/">← Volver al inicio</a>
            </section>

            <section class="section listing-section">
                <div class="section-heading">
                    <div>
                        <span class="eyebrow">BIBLIOTECA</span>
                        <h2>Explorar <span><?= e($title) ?></span></h2>
                    </div>
                </div>

                <?php require APP_ROOT . '/Views/Content/cards.php'; ?>
                <?php require APP_ROOT . '/Views/Content/pagination.php'; ?>
            </section>

        <?php elseif ($page === 'content-detail'): ?>

            <?php require APP_ROOT . '/Views/Content/detail.php'; ?>

        <?php elseif (in_array($page, ['register', 'login', 'forgot-password', 'reset-password'], true)): ?>

            <?php require APP_ROOT . '/Views/Auth/form.php'; ?>

        <?php elseif ($page === 'verify-pending'): ?>

            <section class="page-banner">
                <span class="eyebrow">UN PASO MÁS</span>

                <h1>Revisá tu <span>correo electrónico.</span></h1>

                <p>
                    Tu cuenta fue creada correctamente. Te enviamos un correo
                    con un enlace para verificar tu dirección y activar tu cuenta.
                </p>

                <p>
                    Si todavía no recibiste el mensaje, revisá tu bandeja de spam
                    o correo no deseado. El enlace tiene una validez de 24 horas.
                </p>

                <?php if ($success !== ''): ?>
                    <p><?= e($success) ?></p>
                <?php endif; ?>

                <?php if (!empty($_SESSION['pending_verification_email'])): ?>
                    <form method="post" action="/?page=verify-pending" class="auth-form">
                        <input
                            type="hidden"
                            name="csrf_token"
                            value="<?= e($_SESSION['csrf_token']) ?>"
                        >

                        <button
                            class="button button-primary button-large submit-button"
                            type="submit"
                        >
                            Reenviar correo
                        </button>
                    </form>
                <?php endif; ?>

                <a class="text-link" href="/">Volver al inicio</a>
            </section>

        <?php elseif ($page === 'verify-email'): ?>

            <section class="page-banner">

                <?php if ($verificationResult === 'success'): ?>

                    <h1>Correo <span>verificado.</span></h1>

                    <p>
                        Tu cuenta FoxCraft ya está activa.
                        Ya podés iniciar sesión.
                    </p>

                    <a class="button button-primary" href="/?page=login">
                        Iniciar sesión
                    </a>

                <?php elseif ($verificationResult === 'invalid'): ?>

                    <h1>Enlace <span>inválido.</span></h1>

                    <p>
                        El enlace venció, ya fue utilizado o no es válido.
                    </p>

                    <a class="button button-primary" href="/?page=register">
                        Crear cuenta
                    </a>

                <?php else: ?>

                    <h1>Error de <span>verificación.</span></h1>

                    <p>
                        No pudimos verificar tu correo en este momento.
                        Intentá nuevamente más tarde.
                    </p>

                <?php endif; ?>

            </section>

        <?php elseif ($page === 'create-content'): ?>

            <?php require APP_ROOT . '/Views/Content/create.php'; ?>

        <?php elseif ($page === 'admin' && !$isAdmin): ?>

            <section class="page-banner">
                <span class="eyebrow">ACCESO RESTRINGIDO</span>

                <h1>Acceso <span>denegado.</span></h1>

                <p>
                    No tenés permisos para acceder al panel de administración.
                </p>

                <a class="button button-primary" href="/">
                    Volver al inicio
                </a>
            </section>

        <?php elseif ($page === 'admin' && $isAdmin): ?>

            <?php require APP_ROOT . '/Views/Admin/content.php'; ?>

        <?php elseif ($page === 'discord'): ?>

            <?php require APP_ROOT . '/Views/Discord/index.php'; ?>

        <?php elseif ($page === 'discord-manage'): ?>

            <?php require APP_ROOT . '/Views/Discord/manage.php'; ?>

        <?php elseif ($page === 'edit-profile'): ?>

            <?php require APP_ROOT . '/Views/Profile/edit.php'; ?>

        <?php elseif ($page === 'profile'): ?>

            <?php require APP_ROOT . '/Views/Profile/index.php'; ?>

        <?php elseif ($page === 'favorites'): ?>

            <?php require APP_ROOT . '/Views/Favorites/index.php'; ?>

        <?php elseif ($page === 'privacy'): ?>

            <?php require APP_ROOT . '/Views/Legal/privacy.php'; ?>

        <?php endif; ?>

    </main>

    <?php if ($page !== 'prepare-download'): ?>
        <?php require APP_ROOT . '/Views/layouts/footer.php'; ?>
    <?php endif; ?>


    <!-- ========================================
         PASSWORD VISIBILITY
    ========================================= -->

    <script>
        document.querySelectorAll('.password-toggle').forEach(button => {
            const input = document.getElementById(button.dataset.target);

            if (!input) return;

            const eye = `
                <svg viewBox="0 0 24 24" aria-hidden="true">
                    <path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7-10-7-10-7Z"/>
                    <circle cx="12" cy="12" r="3"/>
                </svg>
            `;

            const eyeOff = `
                <svg viewBox="0 0 24 24" aria-hidden="true">
                    <path d="m3 3 18 18"/>
                    <path d="M10.6 10.6a2 2 0 0 0 2.8 2.8"/>
                    <path d="M9.9 5.2A10.8 10.8 0 0 1 12 5c6.4 0 10 7 10 7a15 15 0 0 1-3 3.8"/>
                    <path d="M6.2 6.2C3.5 8.1 2 12 2 12s3.6 7 10 7a10.8 10.8 0 0 0 3.1-.5"/>
                </svg>
            `;

            button.innerHTML = eye;
            button.setAttribute('aria-label', 'Mostrar contraseña');
            button.setAttribute('aria-pressed', 'false');

            button.addEventListener('click', () => {
                const visible = input.type === 'password';

                input.type = visible ? 'text' : 'password';
                button.innerHTML = visible ? eyeOff : eye;

                button.setAttribute(
                    'aria-label',
                    visible ? 'Ocultar contraseña' : 'Mostrar contraseña'
                );

                button.setAttribute('aria-pressed', String(visible));
            });
        });
    </script>

</body>
</html>