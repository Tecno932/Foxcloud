<?php


session_start();

header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('X-Frame-Options: DENY');

function e(string $value): string {
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

$pages = [
    'home' => ['Inicio', 'Todo el contenido de Minecraft en un solo lugar.'],
    'mods' => ['Mods', 'Descubre nuevos mods y complementos.'],
    'maps' => ['Mapas', 'Explora nuevos mundos y aventuras.'],
    'shaders' => ['Shaders', 'Mejora el apartado visual de Minecraft.'],
    'resource-packs' => ['Resource Packs', 'Personaliza tu experiencia.'],
    'texture-packs' => ['Texture Packs', 'Nuevas texturas para tu mundo.'],
    'ui-packs' => ['UI Packs', 'Personaliza la interfaz del juego.'],
    'skins' => ['Skins', 'Encuentra tu próxima apariencia.'],
    'armor-trims' => ['Armor Trims', 'Personaliza tus armaduras.'],
    'banners' => ['Banners', 'Diseños para decorar tu mundo.'],
    'schematics-java' => ['Schematics Java', 'Construcciones para Minecraft Java.'],
    'schematics-bedrock' => ['Schematics Bedrock', 'Construcciones para Minecraft Bedrock.'],
    'holographic' => ['Holographic', 'Modelos y texturas holográficas.'],
    'login' => ['Iniciar sesión', 'Accede a tu cuenta FoxCraft.'],
    'register' => ['Crear cuenta', 'Únete a la comunidad FoxCraft.'],
    'profile' => ['Mi perfil', 'Tu cuenta FoxCraft.'],
];

$page = (string)($_GET['page'] ?? 'home');
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
    require_once '/var/www/foxcraft/app/Core/Database.php';
    $pdo = \FoxCraft\Core\Database::connection();
} catch (Throwable $exception) {
    error_log('FoxCraft database connection: ' . $exception->getMessage());
    $dbError = true;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = (string)($_POST['csrf_token'] ?? '');

    if (!hash_equals((string)$_SESSION['csrf_token'], $token)) {
        $errors[] = 'El formulario venció. Recargá la página e intentá nuevamente.';
    } elseif (!$pdo) {
        $errors[] = 'El servicio no está disponible en este momento.';
    } elseif ($page === 'register') {
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
                $check = $pdo->prepare(
                    'SELECT id FROM users WHERE username = :username OR email = :email LIMIT 1'
                );
                $check->execute(['username' => $username, 'email' => $email]);

                if ($check->fetch()) {
                    $errors[] = 'Ese nombre de usuario o correo ya está registrado.';
                } else {
                    $pdo->beginTransaction();

                    $insert = $pdo->prepare(
                        "INSERT INTO users (username, email, password_hash, role, status)
                         VALUES (:username, :email, :password_hash, 'user', 'active')"
                    );
                    $insert->execute([
                        'username' => $username,
                        'email' => $email,
                        'password_hash' => password_hash($password, PASSWORD_DEFAULT),
                    ]);

                    $userId = (int)$pdo->lastInsertId();
                    $profile = $pdo->prepare(
                        'INSERT INTO profiles (user_id, display_name) VALUES (:id, :name)'
                    );
                    $profile->execute(['id' => $userId, 'name' => $username]);
                    $pdo->commit();

                    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
                    $_SESSION['flash'] = 'Cuenta creada correctamente. Ya podés iniciar sesión.';
                    header('Location: /?page=login', true, 303);
                    exit;
                }
            } catch (Throwable $exception) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                error_log('FoxCraft registration: ' . $exception->getMessage());
                $errors[] = 'No pudimos crear tu cuenta. Revisá los datos e intentá nuevamente.';
            }
        }
    } elseif ($page === 'login') {
        $identity = trim((string)($_POST['identity'] ?? ''));
        $password = (string)($_POST['password'] ?? '');

        try {
            $query = $pdo->prepare(
                'SELECT id, username, password_hash, status
                 FROM users WHERE username = :identity OR email = :identity LIMIT 1'
            );
            $query->execute(['identity' => $identity]);
            $user = $query->fetch();

            if (!$user || !password_verify($password, $user['password_hash'])) {
                $errors[] = 'Usuario, correo o contraseña incorrectos.';
            } elseif ($user['status'] !== 'active') {
                $errors[] = 'Esta cuenta no está habilitada para iniciar sesión.';
            } else {
                session_regenerate_id(true);
                $_SESSION['user_id'] = (int)$user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

                $update = $pdo->prepare('UPDATE users SET last_login_at = NOW() WHERE id = :id');
                $update->execute(['id' => $user['id']]);

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

if ($page === 'profile' && empty($_SESSION['user_id'])) {
    header('Location: /?page=login', true, 303);
    exit;
}

$categories = [];
$contents = [];
$categoryId = null;

if ($pdo) {
    try {
        $categories = $pdo->query(
            'SELECT name, slug, description FROM categories
             WHERE is_active = 1 ORDER BY sort_order, name'
        )->fetchAll();

        if (in_array($page, array_column($categories, 'slug'), true)) {
            $findCategory = $pdo->prepare(
                'SELECT id FROM categories WHERE slug = :slug AND is_active = 1 LIMIT 1'
            );
            $findCategory->execute(['slug' => $page]);
            $categoryId = $findCategory->fetchColumn();
        }

        if ($page === 'home') {
            $contents = $pdo->query(
                "SELECT c.id, c.title, c.slug, c.description, c.image_url,
                        c.downloads_count, c.likes_count, cat.name AS category_name,
                        cat.slug AS category_slug
                 FROM contents c
                 JOIN categories cat ON cat.id = c.category_id
                 WHERE c.status = 'published'
                 ORDER BY c.is_featured DESC, c.created_at DESC LIMIT 8"
            )->fetchAll();
        } elseif ($categoryId !== null) {
            $list = $pdo->prepare(
                "SELECT c.id, c.title, c.slug, c.description, c.image_url,
                        c.downloads_count, c.likes_count, cat.name AS category_name,
                        cat.slug AS category_slug
                 FROM contents c
                 JOIN categories cat ON cat.id = c.category_id
                 WHERE c.status = 'published' AND c.category_id = :category
                 ORDER BY c.created_at DESC LIMIT 48"
            );
            $list->execute(['category' => $categoryId]);
            $contents = $list->fetchAll();
        }
    } catch (Throwable $exception) {
        error_log('FoxCraft listing: ' . $exception->getMessage());
        $dbError = true;
    }
}

function renderCards(array $contents): void {
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
        echo '<a class="content-image" href="/?page=' . e((string)$item['category_slug']) . '">';
        if ($validImage) {
            echo '<img loading="lazy" src="' . e($image) . '" alt="' . e((string)$item['title']) . '">';
        } else {
            echo '<span class="image-placeholder">FOXCRAFT</span>';
        }
        echo '</a><div class="content-info">';
        echo '<span class="content-category">' . e((string)$item['category_name']) . '</span>';
        echo '<h3>' . e((string)$item['title']) . '</h3>';
        echo '<p>' . e(mb_strimwidth((string)$item['description'], 0, 115, '…', 'UTF-8')) . '</p>';
        echo '<div class="content-meta"><span>↓ ' . number_format((int)$item['downloads_count']) . '</span>';
        echo '<span>♥ ' . number_format((int)$item['likes_count']) . '</span></div>';
        echo '</div></article>';
    }
    echo '</div>';
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
    <link rel="stylesheet" href="/assets/css/style.css">
    <script src="/assets/js/app.js" defer></script>
</head>
<body>
<header class="site-header">
    <div class="nav-wrap">
        <a class="brand" href="/"><span class="brand-mark">F</span><span>FOX<span class="brand-accent">CRAFT</span></span></a>
        <button class="menu-toggle" type="button" aria-label="Abrir menú" aria-expanded="false">☰</button>
        <nav class="main-nav" id="main-nav">
            <a class="<?= $page === 'home' ? 'active' : '' ?>" href="/">Inicio</a>
            <a href="/?page=mods">Mods</a>
            <a href="/?page=maps">Mapas</a>
            <a href="/?page=shaders">Shaders</a>
            <a href="#categorias">Categorías</a>
        </nav>
        <div class="nav-actions">
            <?php if (!empty($_SESSION['user_id'])): ?>
                <a class="button button-quiet" href="/?page=profile"><?= e((string)$_SESSION['username']) ?></a>
                <form method="post" action="/?page=logout" class="inline-form">
                    <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token']) ?>">
                    <button class="button button-quiet" type="submit" name="logout" value="1">Salir</button>
                </form>
            <?php else: ?>
                <a class="button button-quiet login-link" href="/?page=login">Ingresar</a>
                <a class="button button-primary" href="/?page=register">Crear cuenta <span>↗</span></a>
            <?php endif; ?>
        </div>
    </div>
</header>

<main>
<?php if ($success !== ''): ?>
    <div class="notice notice-success"><?= e($success) ?></div>
<?php endif; ?>
<?php if ($dbError): ?>
    <div class="notice notice-error">No se pudo consultar la base de datos. Revisá el registro de errores del servidor.</div>
<?php endif; ?>
<?php if ($errors): ?>
    <div class="notice notice-error"><ul><?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul></div>
<?php endif; ?>

<?php if ($page === 'home'): ?>
    <section class="hero">
        <div class="hero-glow"></div>
        <div class="hero-content">
            <span class="eyebrow"><span class="status-dot"></span> TU UNIVERSO DE MINECRAFT</span>
            <h1>Todo el contenido<br>de Minecraft en <span>un solo lugar.</span></h1>
            <p>Descubrí mods, mapas, shaders, skins y mucho más. Todo organizado para que encuentres tu próxima aventura.</p>
            <div class="hero-actions">
                <a class="button button-primary button-large" href="#contenido">Explorar contenido <span>↘</span></a>
                <a class="button button-outline button-large" href="/?page=mods">Ver mods</a>
            </div>
            <div class="hero-stats">
                <div><strong><?= count($categories) ?></strong><span>Categorías</span></div>
                <div><strong><?= count($contents) ?></strong><span>Publicaciones visibles</span></div>
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

    <section class="section categories-section" id="categorias">
        <div class="section-heading">
            <div><span class="eyebrow">EXPLORÁ A TU MANERA</span><h2>Categorías <span>destacadas</span></h2></div>
            <span class="section-count"><?= count($categories) ?> secciones</span>
        </div>
        <div class="category-grid">
        <?php foreach ($categories as $category): ?>
            <a class="category-card" href="/?page=<?= e((string)$category['slug']) ?>">
                <span class="category-icon">
                    <img loading="lazy" src="/assets/icons/categories/<?= e(match ($category['slug']) {
                        'mods' => 'mods.png',
                        'maps' => 'maps.png',
                        'shaders' => 'shaders.png',
                        'skins' => 'skins.png',
                        'schematics-java', 'schematics-bedrock' => 'schematics.png',
                        default => 'mods.png',
                    }) ?>" alt="">
                </span>
                <span class="category-copy"><strong><?= e((string)$category['name']) ?></strong><small><?= e((string)($category['description'] ?: 'Explorá contenido para Minecraft.')) ?></small></span>
                <span class="category-arrow">↗</span>
            </a>
        <?php endforeach; ?>
        </div>
    </section>

    <section class="section" id="contenido">
        <div class="section-heading">
            <div><span class="eyebrow">SELECCIÓN FOXCRAFT</span><h2>Contenido <span>reciente</span></h2></div>
            <a class="text-link" href="/?page=mods">Explorar secciones ↗</a>
        </div>
        <?php renderCards($contents); ?>
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
        <?php renderCards($contents); ?>
    </section>

<?php elseif ($page === 'register' || $page === 'login'): ?>
    <section class="auth-section">
        <div class="auth-card">
            <a class="brand auth-brand" href="/"><span class="brand-mark">F</span><span>FOX<span class="brand-accent">CRAFT</span></span></a>
            <span class="eyebrow"><?= $page === 'register' ? 'UNITE A LA COMUNIDAD' : 'QUÉ BUENO VERTE' ?></span>
            <h1><?= $page === 'register' ? 'Creá tu cuenta.' : 'Bienvenido de nuevo.' ?></h1>
            <p><?= e($description) ?></p>
            <form method="post" action="/?page=<?= e($page) ?>" class="auth-form">
                <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token']) ?>">
                <?php if ($page === 'register'): ?>
                    <label for="username">Nombre de usuario</label>
                    <input id="username" name="username" type="text" minlength="3" maxlength="30" pattern="[A-Za-z0-9_]+" autocomplete="username" required value="<?= e((string)($_POST['username'] ?? '')) ?>">
                    <label for="email">Correo electrónico</label>
                    <input id="email" name="email" type="email" maxlength="190" autocomplete="email" required value="<?= e((string)($_POST['email'] ?? '')) ?>">
                    <label for="password">Contraseña</label>
                    <input id="password" name="password" type="password" minlength="12" autocomplete="new-password" required>
                    <small class="form-hint">Usá al menos 12 caracteres.</small>
                <?php else: ?>
                    <label for="identity">Usuario o correo electrónico</label>
                    <input id="identity" name="identity" type="text" autocomplete="username" required>
                    <label for="password">Contraseña</label>
                    <input id="password" name="password" type="password" autocomplete="current-password" required>
                <?php endif; ?>
                <button class="button button-primary button-large submit-button" type="submit"><?= $page === 'register' ? 'Crear cuenta' : 'Iniciar sesión' ?> <span>↗</span></button>
            </form>
            <p class="auth-switch">
                <?= $page === 'register' ? '¿Ya tenés una cuenta?' : '¿Todavía no tenés cuenta?' ?>
                <a href="/?page=<?= $page === 'register' ? 'login' : 'register' ?>"><?= $page === 'register' ? 'Iniciá sesión' : 'Registrate' ?></a>
            </p>
        </div>
    </section>

<?php elseif ($page === 'profile'): ?>
    <section class="page-banner">
        <span class="eyebrow">TU ESPACIO PERSONAL</span>
        <h1>Hola, <?= e((string)($_SESSION['username'] ?? 'usuario')) ?>.</h1>
        <p>Tu cuenta FoxCraft está activa.</p>
        <a class="button button-primary" href="/">Volver al inicio</a>
    </section>
<?php endif; ?>
</main>

<footer class="site-footer">
    <a class="brand" href="/"><span class="brand-mark">F</span><span>FOX<span class="brand-accent">CRAFT</span></span></a>
    <p>Una comunidad, infinitas formas de jugar.</p>
    <span class="footer-copy">© <?= date('Y') ?> FoxCraft</span>
</footer>
</body>
</html>
PHP

sudo php -l /var/www/foxcraft/public/index.php
