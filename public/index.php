<?php

define('PROJECT_ROOT', dirname(__DIR__));
define('APP_ROOT', PROJECT_ROOT . '/app');

require_once PROJECT_ROOT . '/vendor/autoload.php';
require_once APP_ROOT . '/Core/helpers.php';

// Protección de sesiones
ini_set('session.use_strict_mode', '1');
ini_set('session.use_only_cookies', '1');

$isHttps = isset($_SERVER['HTTPS'])
    && $_SERVER['HTTPS'] !== ''
    && strtolower((string)$_SERVER['HTTPS']) !== 'off';

session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'secure' => $isHttps,
    'httponly' => true,
    'samesite' => 'Lax',
]);

session_start();

header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('X-Frame-Options: DENY');
header('Permissions-Policy: camera=(), microphone=(), geolocation=(), payment=(), usb=()');

$csp = "default-src 'self'; "
    . "base-uri 'self'; "
    . "object-src 'none'; "
    . "frame-ancestors 'none'; "
    . "form-action 'self'; "
    . "script-src 'self' 'wasm-unsafe-eval' https://al5sm.com; "
    . "style-src 'self'; "
    . "img-src 'self' https://foxcraft-web1-249i.vercel.app; "
    . "font-src 'self'; "
    . "connect-src 'self' https://255md.com https://ay267.com http://ay267.com";

header('Content-Security-Policy: ' . $csp);

$pages = require APP_ROOT . '/Config/pages.php';

$page = (string)($_GET['page'] ?? 'home');
$routeContentId = 0;
$routeSlug = '';
$routeCategory = '';

$requestPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';

if ($requestPath === '/skins' || $requestPath === '/skins/') {
    $page = 'skins';
}

if (preg_match('~^/([a-z0-9-]+)/([0-9]+)/([a-z0-9-]+)/?$~i', $requestPath, $routeMatch)) {
    $routeCategory = strtolower($routeMatch[1]);
    $routeContentId = (int)$routeMatch[2];
    $routeSlug = $routeMatch[3];
    $page = 'content-detail';
}

if (!isset($pages[$page])) {
    http_response_code(404);
    $page = 'home';
}

[$title, $description] = $pages[$page];

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$errors = [];
$success = (string)($_SESSION['flash'] ?? '');
unset($_SESSION['flash']);

$pdo = null;
$dbError = false;

if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_POST['logout'])
    && hash_equals(
        (string)($_SESSION['csrf_token'] ?? ''),
        (string)($_POST['csrf_token'] ?? '')
    )
) {
    $_SESSION = [];
    session_regenerate_id(true);
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    header('Location: /', true, 303);
    exit;
}

try {
    require_once APP_ROOT . '/Core/Database.php';
    $pdo = \FoxCraft\Core\Database::connection();
} catch (Throwable $exception) {
    error_log('FoxCraft database connection: ' . $exception->getMessage());
    $dbError = true;
}

if ($page === 'verify-email' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    if (!$pdo) {
        http_response_code(503);
        $verificationResult = 'error';
    } else {
        try {
            require_once APP_ROOT . '/Repositories/EmailVerificationRepository.php';

            $verificationRepository = new \FoxCraft\Repositories\EmailVerificationRepository($pdo);

            $verificationToken = (string)($_GET['token'] ?? '');

            if ($verificationRepository->verify($verificationToken)) {
                $verificationResult = 'success';
            } else {
                $verificationResult = 'invalid';
            }
        } catch (Throwable $exception) {
            error_log('FoxCraft email verification: ' . $exception->getMessage());
            $verificationResult = 'error';
        }
    }
}

if ($page === 'prepare-download' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    $contentId = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT);

    if (!$contentId || $contentId < 1 || !$pdo) {
        http_response_code($pdo ? 404 : 503);
        exit('La descarga no está disponible.');
    }

    try {
        require_once APP_ROOT . '/Repositories/ContentRepository.php';

        $downloadRepository = new \FoxCraft\Repositories\ContentRepository($pdo);
        $downloadUrl = $downloadRepository->findPublishedDownloadUrl($contentId);
        $downloadParts = is_string($downloadUrl) ? parse_url($downloadUrl) : false;

        if (
            !$downloadUrl
            || !filter_var($downloadUrl, FILTER_VALIDATE_URL)
            || !is_array($downloadParts)
            || strtolower((string)($downloadParts['scheme'] ?? '')) !== 'https'
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

if ($page === 'download' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    $contentId = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT);

    if (!$contentId || $contentId < 1 || !$pdo) {
        http_response_code($pdo ? 404 : 503);
        exit('La descarga no está disponible.');
    }

    try {
        require_once APP_ROOT . '/Repositories/ContentRepository.php';
        $downloadRepository = new \FoxCraft\Repositories\ContentRepository($pdo);
        $downloadUrl = $downloadRepository->findPublishedDownloadUrl($contentId);
        $downloadParts = is_string($downloadUrl) ? parse_url($downloadUrl) : false;

        if (
            !$downloadUrl
            || !filter_var($downloadUrl, FILTER_VALIDATE_URL)
            || !is_array($downloadParts)
            || strtolower((string)($downloadParts['scheme'] ?? '')) !== 'https'
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

$isAdmin = false;
if ($pdo && !empty($_SESSION['user_id'])) {
    try {
        require_once APP_ROOT . '/Repositories/UserRepository.php';
        $userRepository = new \FoxCraft\Repositories\UserRepository($pdo);
        $isAdmin = $userRepository->isActiveAdmin((int)$_SESSION['user_id']);
    } catch (Throwable $exception) {
        error_log('FoxCraft admin authorization: ' . $exception->getMessage());
    }
}

if ($page === 'admin' && !$isAdmin) {
    if (empty($_SESSION['user_id'])) {
        header('Location: /?page=login', true, 303);
        exit;
    }
    http_response_code(403);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = (string)($_POST['csrf_token'] ?? '');

    if (!hash_equals((string)$_SESSION['csrf_token'], $token)) {
        $errors[] = 'El formulario venció. Recargá la página e intentá nuevamente.';
    } elseif (!$pdo) {
        $errors[] = 'El servicio no está disponible en este momento.';
    } elseif ($page === 'favorites') {
        if (empty($_SESSION['user_id'])) {
            header('Location: /?page=login', true, 303);
            exit;
        }

        $contentId = filter_var($_POST['content_id'] ?? null, FILTER_VALIDATE_INT);
        $action = (string)($_POST['favorite_action'] ?? '');

        if (!$contentId || $contentId < 1) {
            $errors[] = 'La publicación seleccionada no es válida.';
        } elseif (!in_array($action, ['add', 'remove'], true)) {
            $errors[] = 'La acción de favoritos no es válida.';
        } else {
            try {
                require_once APP_ROOT . '/Repositories/FavoriteRepository.php';
                $favoriteRepository = new \FoxCraft\Repositories\FavoriteRepository($pdo);

                if ($action === 'add') {
                    if ($favoriteRepository->isFavorite((int)$_SESSION['user_id'], $contentId)) {
                        $_SESSION['flash'] = 'Esta publicación ya está en tus favoritos.';
                    } elseif ($favoriteRepository->add((int)$_SESSION['user_id'], $contentId)) {
                        $_SESSION['flash'] = 'Publicación agregada a tus favoritos.';
                    } else {
                        $errors[] = 'No se pudo agregar la publicación. Puede que ya no esté disponible.';
                    }
                } else {
                    if ($favoriteRepository->remove((int)$_SESSION['user_id'], $contentId)) {
                        $_SESSION['flash'] = 'Publicación eliminada de tus favoritos.';
                    } else {
                        $_SESSION['flash'] = 'La publicación ya no estaba en tus favoritos.';
                    }
                }

                if (!$errors) {
                    $returnTo = (string)($_POST['return_to'] ?? '');

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
    } elseif ($page === 'likes') {
        if (empty($_SESSION['user_id'])) {
            header('Location: /?page=login', true, 303);
            exit;
        }

        $contentId = filter_var($_POST['content_id'] ?? null, FILTER_VALIDATE_INT);
        $action = (string)($_POST['like_action'] ?? '');

        if (!$contentId || $contentId < 1) {
            $errors[] = 'La publicación seleccionada no es válida.';
        } elseif (!in_array($action, ['add', 'remove'], true)) {
            $errors[] = 'La acción de Me gusta no es válida.';
        } else {
            try {
                require_once APP_ROOT . '/Repositories/LikeRepository.php';
                $likeRepository = new \FoxCraft\Repositories\LikeRepository($pdo);

                if ($action === 'add') {
                    if ($likeRepository->isLiked((int)$_SESSION['user_id'], $contentId)) {
                        $_SESSION['flash'] = 'Ya te gusta esta publicación.';
                    } elseif ($likeRepository->add((int)$_SESSION['user_id'], $contentId)) {
                        $_SESSION['flash'] = 'Marcaste que te gusta esta publicación.';
                    } else {
                        $errors[] = 'No se pudo registrar tu Me gusta. Puede que la publicación ya no esté disponible.';
                    }
                } else {
                    if ($likeRepository->remove((int)$_SESSION['user_id'], $contentId)) {
                        $_SESSION['flash'] = 'Quitaste tu Me gusta de esta publicación.';
                    } else {
                        $_SESSION['flash'] = 'No habías marcado que te gusta esta publicación.';
                    }
                }

                if (!$errors) {
                    $returnTo = (string)($_POST['return_to'] ?? '');
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
    } elseif ($page === 'admin' && $isAdmin) {
        $contentId = filter_var($_POST['content_id'] ?? '0', FILTER_VALIDATE_INT);
        $contentId = $contentId && $contentId > 0 ? $contentId : 0;
        $categoryIdInput = filter_var($_POST['category_id'] ?? '', FILTER_VALIDATE_INT);
        $contentTitle = trim((string)($_POST['title'] ?? ''));
        $contentSlug = strtolower(trim((string)($_POST['slug'] ?? '')));
        $contentSlug = trim((string)preg_replace('/[^a-z0-9]+/', '-', $contentSlug), '-');
        $contentDescription = trim((string)($_POST['description'] ?? ''));
        $imageUrl = trim((string)($_POST['image_url'] ?? ''));
        $downloadUrl = trim((string)($_POST['download_url'] ?? ''));
        $contentType = 'download';
        $platform = (string)($_POST['platform'] ?? 'both');
        $status = (string)($_POST['status'] ?? 'draft');
        $featured = isset($_POST['is_featured']) ? 1 : 0;
        $versionInput = trim((string)($_POST['game_versions'] ?? ''));

        if (!$categoryIdInput || $categoryIdInput < 1) {
            $errors[] = 'Seleccioná una categoría válida.';
        }
        if ($contentTitle === '' || mb_strlen($contentTitle) > 180) {
            $errors[] = 'El título es obligatorio y no puede superar los 180 caracteres.';
        }
        if ($contentSlug === '' || strlen($contentSlug) > 200) {
            $errors[] = 'El slug debe contener letras, números y guiones.';
        }
        if ($contentType !== 'download') {
            $errors[] = 'El tipo de contenido no es válido.';
        }
        if (!in_array($platform, ['java', 'bedrock', 'both', 'other'], true)) {
            $errors[] = 'La plataforma seleccionada no es válida.';
        }
        if (!in_array($status, ['draft', 'published', 'archived'], true)) {
            $errors[] = 'El estado seleccionado no es válido.';
        }

        foreach (['image_url' => $imageUrl, 'download_url' => $downloadUrl] as $field => $url) {
            if ($url === '') {
                continue;
            }

            $parts = parse_url($url);
            $localImage = $field === 'image_url'
                && str_starts_with($url, '/')
                && !str_starts_with($url, '//')
                && !str_contains($url, '\\');

            $httpsUrl = filter_var($url, FILTER_VALIDATE_URL)
                && is_array($parts)
                && strtolower((string)($parts['scheme'] ?? '')) === 'https'
                && !empty($parts['host']);

            $isImageOptimizer = $field === 'download_url'
                && is_array($parts)
                && strtolower((string)($parts['path'] ?? '')) === '/_next/image';

            if ($isImageOptimizer) {
                $errors[] = 'La descarga debe apuntar al archivo descargable, no al optimizador de imágenes.';
            } elseif (!$localImage && !$httpsUrl) {
                $errors[] = $field === 'image_url'
                    ? 'La imagen debe usar una ruta local o una URL HTTPS.'
                    : 'La descarga debe usar una URL HTTPS.';
            }
        }

        $versions = array_values(array_unique(array_filter(
            array_map('trim', explode(',', $versionInput)),
            static fn($value) => $value !== ''
        )));
        $versionsJson = json_encode($versions, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

        if (!$errors) {
            try {
                require_once APP_ROOT . '/Repositories/CategoryRepository.php';
                $categoryRepository = new \FoxCraft\Repositories\CategoryRepository($pdo);

                if (!$categoryRepository->isActiveById((int) $categoryIdInput)) {
                    $errors[] = 'La categoría no existe o está desactivada.';
                }
            } catch (Throwable $exception) {
                error_log('FoxCraft admin category validation: ' . $exception->getMessage());
                $errors[] = 'No se pudo validar la categoría.';
            }
        }

        if (!$errors) {
            try {
                require_once APP_ROOT . '/Repositories/AdminContentRepository.php';
                $adminContentRepository = new \FoxCraft\Repositories\AdminContentRepository($pdo);

                $adminContentRepository->save(
                    $contentId,
                    (int) $categoryIdInput,
                    (int) $_SESSION['user_id'],
                    $contentTitle,
                    $contentSlug,
                    $contentDescription,
                    $imageUrl !== '' ? $imageUrl : null,
                    $downloadUrl !== '' ? $downloadUrl : null,
                    $contentType,
                    $platform,
                    $versionsJson,
                    $featured,
                    $status
                );

                $_SESSION['flash'] = $contentId > 0
                    ? 'Publicación actualizada correctamente.'
                    : 'Publicación creada correctamente.';
                header('Location: /?page=admin', true, 303);
                exit;
            } catch (Throwable $exception) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                error_log('FoxCraft admin save: ' . $exception->getMessage());
                $errors[] = 'No se pudo guardar. Revisá que el slug no esté repetido dentro de la categoría.';
            }
        }

    } elseif ($page === 'edit-profile') {
        if (empty($_SESSION['user_id'])) {
            header('Location: /?page=login', true, 303);
            exit;
        }

        $userId = (int) $_SESSION['user_id'];
        $displayName = trim((string)($_POST['display_name'] ?? ''));
        $bio = trim((string)($_POST['bio'] ?? ''));
        $websiteUrl = trim((string)($_POST['website_url'] ?? ''));

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
                || strtolower((string)($parts['scheme'] ?? '')) !== 'https'
                || empty($parts['host'])
            ) {
                $errors[] = 'El sitio web debe ser una URL válida que use HTTPS.';
            }
        }

        $avatar = $_FILES['avatar'] ?? null;

        if (is_array($avatar) && ($avatar['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            $uploadError = (int)($avatar['error'] ?? UPLOAD_ERR_NO_FILE);

            if ($uploadError !== UPLOAD_ERR_OK) {
                $errors[] = match ($uploadError) {
                    UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'La imagen supera el tamaño máximo permitido.',
                    UPLOAD_ERR_PARTIAL => 'La imagen no se recibió completa.',
                    UPLOAD_ERR_NO_TMP_DIR => 'No está disponible la carpeta temporal de PHP.',
                    UPLOAD_ERR_CANT_WRITE => 'No se pudo escribir el archivo subido.',
                    default => 'No se pudo recibir la imagen.',
                };
            } elseif (
                !isset($avatar['tmp_name'], $avatar['size'])
                || !is_uploaded_file((string)$avatar['tmp_name'])
                || (int)$avatar['size'] <= 0
                || (int)$avatar['size'] > 2 * 1024 * 1024
            ) {
                $errors[] = 'La imagen debe pesar como máximo 2 MB.';
            } else {
                $imageInfo = @getimagesize((string)$avatar['tmp_name']);
                $finfo = new \finfo(FILEINFO_MIME_TYPE);
                $mimeType = $finfo->file((string)$avatar['tmp_name']);

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
                } elseif (!is_writable($avatarDirectory)) {
                    $errors[] = 'El servidor no puede guardar imágenes actualmente.';
                } else {
                    $safeUsername = preg_replace(
                        '/[^a-zA-Z0-9_-]/',
                        '',
                        (string)($_SESSION['username'] ?? 'user')
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

                    if (!move_uploaded_file((string)$avatar['tmp_name'], $destination)) {
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
                $oldAvatarPath = trim((string)($currentProfile['avatar_path'] ?? ''));

                $profileRepository->save(
                    $userId,
                    $displayName,
                    $bio,
                    $websiteUrl !== '' ? $websiteUrl : null,
                    $newAvatarPath
                );

                if (
                    $newAvatarPath !== null
                    && str_starts_with($oldAvatarPath, '/uploads/avatars/')
                    && preg_match(
                        '/\A(?:[a-zA-Z0-9_-]+-[a-f0-9]{16}|[a-f0-9]{48})\.(jpg|png|webp)\z/',
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
    }   elseif ($page === 'register') {
        $username = trim((string)($_POST['username'] ?? ''));
        $email = strtolower(trim((string)($_POST['email'] ?? '')));
        $password = (string)($_POST['password'] ?? '');

        if (!preg_match('/\A[a-zA-Z0-9_]{3,30}\z/', $username)) {
            $errors[] = 'El usuario debe tener entre 3 y 30 caracteres: letras, números o guion bajo.';
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 190) {
            $errors[] = 'Ingresá un correo electrónico válido.';
        }
        if (strlen($password) < 12 || strlen($password) > 4096) {
            $errors[] = 'La contraseña debe tener al menos 12 caracteres.';
        }

        if (!$errors) {
            try {
                require_once APP_ROOT . '/Repositories/UserRegistrationRepository.php';
                require_once APP_ROOT . '/Core/MailService.php';

                $registrationRepository = new \FoxCraft\Repositories\UserRegistrationRepository($pdo);
                $verificationToken = $registrationRepository->register(
                    $username,
                    $email,
                    $password
                );

                $appConfig = require APP_ROOT . '/Config/app.php';
                $baseUrl = rtrim((string)$appConfig['base_url'], '/');

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

                $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
                header('Location: /?page=verify-pending', true, 303);
                exit;
            } catch (\DomainException $exception) {
                $errors[] = $exception->getMessage();
            } catch (Throwable $exception) {
                error_log('FoxCraft registration: ' . $exception->getMessage());
                $errors[] = 'No se pudo completar el registro. Contactá con soporte si tu cuenta ya fue creada.';
            }
        }
    } elseif ($page === 'login') {
        $identity = trim((string)($_POST['identity'] ?? ''));
        $password = (string)($_POST['password'] ?? '');

        try {
            require_once APP_ROOT . '/Repositories/AuthenticationRepository.php';
            $authenticationRepository = new \FoxCraft\Repositories\AuthenticationRepository($pdo);
            $authentication = $authenticationRepository->authenticate($identity, $password);

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
    } elseif ($page === 'logout') {
        // El cierre de sesión se procesa abajo mediante una ruta POST dedicada.
        $errors[] = 'Acción no válida.';
    }
}

if ($page === 'logout' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    $page = 'home';
    [$title, $description] = $pages[$page];
}

if (in_array($page, ['profile', 'edit-profile', 'favorites'], true) && empty($_SESSION['user_id'])) {
    header('Location: /?page=login', true, 303);
    exit;
}

$categories = [];
$contents = [];
$adminContents = [];
$editingContent = null;
$categoryId = null;
$contentDetail = null;
$profileData = null;
$favoriteContents = [];
$isFavorite = false;
$isLiked = false;

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

$searchQuery = trim((string)($_GET['q'] ?? ''));
$filterPlatform = (string)($_GET['platform'] ?? '');
$filterVersion = (string)($_GET['version'] ?? '');

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

if ($pdo) {
    try {
        require_once APP_ROOT . '/Repositories/CategoryRepository.php';
        $categoryRepository = new \FoxCraft\Repositories\CategoryRepository($pdo);
        $categories = $categoryRepository->findActive();

        require_once APP_ROOT . '/Repositories/ContentRepository.php';
        $contentRepository = new \FoxCraft\Repositories\ContentRepository($pdo);

        if ($page === 'favorites' && !empty($_SESSION['user_id'])) {
            require_once APP_ROOT . '/Repositories/FavoriteRepository.php';
            $favoriteRepository = new \FoxCraft\Repositories\FavoriteRepository($pdo);
            $favoriteContents = $favoriteRepository->findByUserId((int) $_SESSION['user_id']);
        }

        if (in_array($page, ['profile', 'edit-profile'], true) && !empty($_SESSION['user_id'])) {
            $userId = (int) $_SESSION['user_id'];

            require_once APP_ROOT . '/Repositories/ProfileRepository.php';
            $profileRepository = new \FoxCraft\Repositories\ProfileRepository($pdo);
            $profileData = $profileRepository->findByUserId($userId);

            if (!$profileData) {
                http_response_code(404);
            } elseif ($page === 'profile') {
                require_once APP_ROOT . '/Repositories/FavoriteRepository.php';
                require_once APP_ROOT . '/Repositories/LikeRepository.php';

                $favoriteRepository = new \FoxCraft\Repositories\FavoriteRepository($pdo);
                $likeRepository = new \FoxCraft\Repositories\LikeRepository($pdo);

                $profileContents = $contentRepository->findPublishedByAuthor($userId);
                $favoriteContents = $favoriteRepository->findByUserId($userId);
                $likedContents = $likeRepository->findByUserId($userId);
            }
        }

        if ($page === 'admin' && $isAdmin) {
            $adminContents = $contentRepository->findAdminList();

            $editId = filter_var($_GET['edit'] ?? '', FILTER_VALIDATE_INT);
            if ($editId && $editId > 0) {
                $editingContent = $contentRepository->findById($editId);

                if (!$editingContent) {
                    $errors[] = 'La publicación seleccionada no existe.';
                }
            }
        }

        if (in_array($page, array_column($categories, 'slug'), true)) {
            $categoryId = $categoryRepository->findActiveIdBySlug($page);
        }

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

        if (
            ($page === 'home' || $page === 'explorer' || $categoryId !== null)
            && $hasCatalogFilters
        ) {
            $catalogCategoryId = $page === 'skins' ? $categoryId : ($filterCategory ?? $categoryId);

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
        } elseif ($page === 'home') {
            $contents = [];
        } elseif ($categoryId !== null) {
            $totalContents = $contentRepository->countPublishedByCategory(
                (int) $categoryId
            );

            $totalPages = max(
                1,
                (int) ceil($totalContents / $itemsPerPage)
            );

            $currentPage = min($currentPage, $totalPages);
            $offset = ($currentPage - 1) * $itemsPerPage;

            $contents = $contentRepository->findPublishedByCategory(
                (int) $categoryId,
                $itemsPerPage,
                $offset
            );
        }

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

                $title = (string)$contentDetail['title'];
                $description = (string)$contentDetail['description'];
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
            <span class="eyebrow"><span class="status-dot"></span> TU UNIVERSO DE MINECRAFT</span>
            <h1>Todo el contenido<br>de Minecraft en <span>un solo lugar.</span></h1>
            <p>Descubrí mods, mapas, texturas, skins, armor trims y construcciones para Minecraft.</p>
            <div class="hero-actions">
                <a class="button button-primary button-large" href="#contenido">Explorar contenido <span>↘</span></a>
                <a class="button button-outline button-large" href="/?page=mods">Ver mods</a>
            </div>
            <div class="hero-stats">
                <div><strong><?= count($categories) ?></strong><span>Categorías</span></div>
                
                <div><strong>∞</strong><span>Nuevas aventuras</span></div>
            </div>
        </div>
        <div class="hero-art" aria-hidden="true">
            <div class="art-orbit orbit-one"></div><div class="art-orbit orbit-two"></div>
            <div class="art-core"><span>F</span></div>
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
        <p>Descubrí todos los mods, mapas y resource packs disponibles en FoxCraft.</p>
    </section>

    <?php require APP_ROOT . '/Views/Content/search.php'; ?>

    <section class="section listing-section">
        <div class="section-heading">
            <div>
                <span class="eyebrow">CATÁLOGO COMPLETO</span>
                <h2>Todo el <span>contenido</span></h2>
            </div>
            <span class="section-count"><?= $totalContents ?> publicaciones</span>
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
            <span class="section-count"><?= $totalContents ?> skins</span>
        </div>

        <?php require APP_ROOT . '/Views/Content/cards.php'; ?>
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
        <div class="section-heading"><div><span class="eyebrow">BIBLIOTECA</span><h2>Explorar <span><?= e($title) ?></span></h2></div></div>
        <?php require APP_ROOT . '/Views/Content/cards.php'; ?>
        <?php require APP_ROOT . '/Views/Content/pagination.php'; ?>
    </section>

<?php elseif ($page === 'content-detail'): ?>
    <?php require APP_ROOT . '/Views/Content/detail.php'; ?>

<?php elseif ($page === 'register' || $page === 'login'): ?>
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
        <a class="button button-primary" href="/">Volver al inicio</a>
    </section>

<?php elseif ($page === 'verify-email'): ?>
    <section class="page-banner">
        <?php if ($verificationResult === 'success'): ?>
            <h1>Correo <span>verificado.</span></h1>
            <p>Tu cuenta FoxCraft ya está activa. Ya podés iniciar sesión.</p>
            <a class="button button-primary" href="/?page=login">Iniciar sesión</a>
        <?php elseif ($verificationResult === 'invalid'): ?>
            <h1>Enlace <span>inválido.</span></h1>
            <p>El enlace venció, ya fue utilizado o no es válido.</p>
            <a class="button button-primary" href="/?page=register">Crear cuenta</a>
        <?php else: ?>
            <h1>Error de <span>verificación.</span></h1>
            <p>No pudimos verificar tu correo en este momento. Intentá nuevamente más tarde.</p>
        <?php endif; ?>
    </section>

<?php elseif ($page === 'admin' && !$isAdmin): ?>
    <section class="page-banner">
        <span class="eyebrow">ACCESO RESTRINGIDO</span>
        <h1>Acceso <span>denegado.</span></h1>
        <p>No tenés permisos para acceder al panel de administración.</p>
        <a class="button button-primary" href="/">Volver al inicio</a>
    </section>

<?php elseif ($page === 'admin' && $isAdmin): ?>
    <?php require APP_ROOT . '/Views/Admin/content.php'; ?>

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
</body>
</html>
