
<section class="auth-section">
    <div class="auth-card">
        <a class="brand auth-brand" href="/">
            <span class="brand-mark">F</span>
            <span>FOX<span class="brand-accent">CRAFT</span></span>
        </a>

        <span class="eyebrow">
            <?= match ($page) {
                'register' => 'UNITE A LA COMUNIDAD',
                'forgot-password' => 'RECUPERAR CUENTA',
                'reset-password' => 'NUEVA CONTRASEÑA',
                default => 'QUÉ BUENO VERTE',
            } ?>
        </span>

        <h1>
            <?= match ($page) {
                'register' => 'Creá tu cuenta.',
                'forgot-password' => '¿Olvidaste tu contraseña?',
                'reset-password' => 'Elegí una contraseña nueva.',
                default => 'Bienvenido de nuevo.',
            } ?>
        </h1>

        <p><?= e($description) ?></p>

        <form method="post" action="/?page=<?= e($page) ?>" class="auth-form">
            <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token']) ?>">

            <?php if ($page === 'register'): ?>

                <label for="username">Nombre de usuario</label>
                <input
                    id="username"
                    name="username"
                    type="text"
                    minlength="3"
                    maxlength="30"
                    pattern="[A-Za-z0-9_]+"
                    autocomplete="username"
                    required
                    value="<?= e((string)($_POST['username'] ?? '')) ?>"
                >

                <label for="email">Correo electrónico</label>
                <input
                    id="email"
                    name="email"
                    type="email"
                    maxlength="190"
                    autocomplete="email"
                    required
                    value="<?= e((string)($_POST['email'] ?? '')) ?>"
                >

                <label for="password">Contraseña</label>
                <div class="password-field">
                    <input
                        id="password"
                        name="password"
                        type="password"
                        minlength="8"
                        maxlength="4096"
                        autocomplete="new-password"
                        required
                    >
                    <button
                        type="button"
                        class="password-toggle"
                        data-target="password"
                        aria-label="Mostrar contraseña"
                    >Mostrar</button>
                </div>

                <label for="password_confirmation">Confirmar contraseña</label>
                <div class="password-field">
                    <input
                        id="password_confirmation"
                        name="password_confirmation"
                        type="password"
                        minlength="8"
                        maxlength="4096"
                        autocomplete="new-password"
                        required
                    >
                    <button
                        type="button"
                        class="password-toggle"
                        data-target="password_confirmation"
                        aria-label="Mostrar contraseña"
                    >Mostrar</button>
                </div>

                <small class="form-hint">Usá al menos 8 caracteres.</small>

            <?php elseif ($page === 'forgot-password'): ?>

                <label for="email">Correo electrónico de tu cuenta</label>
                <input
                    id="email"
                    name="email"
                    type="email"
                    maxlength="190"
                    autocomplete="email"
                    required
                >

            <?php elseif ($page === 'reset-password'): ?>

                <input
                    type="hidden"
                    name="token"
                    value="<?= e((string)($_GET['token'] ?? '')) ?>"
                >

                <label for="password">Nueva contraseña</label>
                <div class="password-field">
                    <input
                        id="password"
                        name="password"
                        type="password"
                        minlength="8"
                        maxlength="4096"
                        autocomplete="new-password"
                        required
                    >
                    <button
                        type="button"
                        class="password-toggle"
                        data-target="password"
                        aria-label="Mostrar contraseña"
                    >Mostrar</button>
                </div>

                <label for="password_confirmation">Confirmar contraseña</label>
                <div class="password-field">
                    <input
                        id="password_confirmation"
                        name="password_confirmation"
                        type="password"
                        minlength="8"
                        maxlength="4096"
                        autocomplete="new-password"
                        required
                    >
                    <button
                        type="button"
                        class="password-toggle"
                        data-target="password_confirmation"
                        aria-label="Mostrar contraseña"
                    >Mostrar</button>
                </div>

                <small class="form-hint">Usá al menos 8 caracteres.</small>

            <?php else: ?>

                <label for="identity">Usuario o correo electrónico</label>
                <input
                    id="identity"
                    name="identity"
                    type="text"
                    autocomplete="username"
                    required
                >

                <label for="password">Contraseña</label>
                <div class="password-field">
                    <input
                        id="password"
                        name="password"
                        type="password"
                        autocomplete="current-password"
                        required
                    >
                    <button
                        type="button"
                        class="password-toggle"
                        data-target="password"
                        aria-label="Mostrar contraseña"
                    >Mostrar</button>
                </div>

                <p class="auth-switch">
                    <a href="/?page=forgot-password">¿Olvidaste tu contraseña?</a>
                </p>

            <?php endif; ?>

            <button class="button button-primary button-large submit-button" type="submit">
                <?= match ($page) {
                    'register' => 'Crear cuenta',
                    'forgot-password' => 'Enviar enlace',
                    'reset-password' => 'Cambiar contraseña',
                    default => 'Iniciar sesión',
                } ?>
                <span>↗</span>
            </button>
        </form>

        <p class="auth-switch">
            <?php if ($page === 'register'): ?>
                ¿Ya tenés una cuenta?
                <a href="/?page=login">Iniciá sesión</a>
            <?php elseif ($page === 'login'): ?>
                ¿Todavía no tenés cuenta?
                <a href="/?page=register">Registrate</a>
            <?php else: ?>
                <a href="/?page=login">Volver a iniciar sesión</a>
            <?php endif; ?>
        </p>
    </div>
</section>
