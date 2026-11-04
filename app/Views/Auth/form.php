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
