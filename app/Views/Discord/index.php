<?php

$discordTabs = [
    'account' => 'Cuenta',
    'servers' => 'Servidores',
    'admin' => 'Administración',
];
?>

<section class="page-banner">
    <span class="eyebrow">FOXCRAFT INTEGRATION</span>
    <h1>Panel de <span>Discord.</span></h1>
    <p>Administrá tu cuenta, tus servidores y la integración con Foxcraftservice.</p>
</section>

<section class="discord-dashboard">
    <nav class="discord-dashboard-tabs" aria-label="Secciones de Discord">
        <?php foreach ($discordTabs as $tabKey => $tabLabel): ?>
            <a
                class="<?= $discordActiveTab === $tabKey ? 'active' : '' ?>"
                href="/?page=discord&amp;tab=<?= e($tabKey) ?>"
                <?= $discordActiveTab === $tabKey ? 'aria-current="page"' : '' ?>
            >
                <?= e($tabLabel) ?>
            </a>
        <?php endforeach; ?>
    </nav>

    <div class="discord-dashboard-content">
        <?php if ($discordActiveTab === 'account'): ?>
            <section class="discord-account-card">
                <h2>Cuenta Discord</h2>

                <?php if ($discordAccount): ?>
                    <p>
                        Cuenta vinculada:
                        <strong><?= e((string)
                        $discordAccount['global_name'] ?: $discordAccount['username']
                        ) ?></strong>
                    </p>

                    <p>Tu cuenta Discord está conectada con FoxCraft.</p>

                    <form method="POST" action="/?page=discord-unlink">
                        <input
                            type="hidden"
                            name="csrf_token"
                            value="<?= e((string)$_SESSION['csrf_token']) ?>"
                        >

                        <button class="button button-outline" type="submit">
                            Desvincular Discord
                        </button>
                    </form>
                <?php else: ?>
                    <p>Tu cuenta FoxCraft todavía no está vinculada con Discord.</p>

                    <a class="button button-primary" href="/?page=discord-link">
                        Vincular cuenta Discord
                    </a>
                <?php endif; ?>
            </section>

        <?php elseif ($discordActiveTab === 'servers'): ?>
            <section class="discord-guilds-card">
                <h2>Mis servidores</h2>

                <?php if (!$discordAccount): ?>
                    <p>Primero tenés que vincular tu cuenta Discord.</p>
                    <a class="button button-primary" href="/?page=discord-link">
                        Vincular Discord
                    </a>
                <?php elseif (!$discordGuildsChecked): ?>
                    <p>Necesitamos consultar tus servidores para mostrar cuáles administrás.</p>
                    <a class="button button-primary" href="/?page=discord-guilds">
                        Consultar servidores
                    </a>
                <?php else: ?>
                    <p>Servidores administrables encontrados: <?= count($discordManagedGuilds) ?></p>

                    <a class="button button-outline" href="/?page=discord-guilds">
                        Actualizar servidores
                    </a>

                    <?php foreach ($discordManagedGuilds as $guild): ?>
                        <?php if (!is_array($guild) || empty($guild['id'])) continue; ?>

                        <div class="discord-guild-item">
                            <strong><?= e((string)($guild['name'] ?? 'Servidor')) ?></strong>

                            <?php if (isset($discordInstalledGuilds[(string)$guild['id']])): ?>
                                <span>Foxcraftservice conectado</span>
                                <a
                                    class="button button-primary"
                                    href="/?page=discord&amp;tab=admin&amp;id=<?= rawurlencode((string)$guild['id']) ?>"
                                >
                                    Administrar
                                </a>
                            <?php else: ?>
                                <span>Foxcraftservice no está conectado</span>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </section>

        <?php else: ?>
            <section class="discord-admin-section">
                <h2>Administración de servidores</h2>

                <?php if (!$discordAccount): ?>
                    <p>Vinculá tu cuenta Discord para continuar.</p>
                <?php elseif (!$discordGuildsChecked): ?>
                    <p>Primero necesitás consultar tus servidores.</p>
                    <a class="button button-primary" href="/?page=discord&amp;tab=servers">
                        Ir a servidores
                    </a>
                <?php elseif (isset($_GET['id'])): ?>
                    <?php require APP_ROOT . '/Views/Discord/manage.php'; ?>
                <?php else: ?>
                    <p>Seleccioná un servidor desde la pestaña Servidores.</p>
                    <a class="button button-primary" href="/?page=discord&amp;tab=servers">
                        Ver servidores
                    </a>
                <?php endif; ?>
            </section>
        <?php endif; ?>
    </div>
</section>