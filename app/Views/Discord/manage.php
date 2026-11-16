
<?php

$manageGuild = $discordManageData['guild'] ?? null;
$manageSettings = $discordManageData['settings'] ?? [];
$canEditDiscordSettings = !empty($discordManageData['can_edit']);

$guildIconUrl = '';

if (is_array($manageGuild)) {
    $candidateIconUrl = (string)($manageGuild['icon'] ?? '');
    $iconParts = $candidateIconUrl !== '' ? parse_url($candidateIconUrl) : false;

    if (
        is_array($iconParts)
        && strtolower((string)($iconParts['scheme'] ?? '')) === 'https'
        && in_array(
            strtolower((string)($iconParts['host'] ?? '')),
            ['cdn.discordapp.com', 'media.discordapp.net'],
            true
        )
    ) {
        $guildIconUrl = $candidateIconUrl;
    }
}

$guildJoinedTimestamp = is_array($manageGuild)
    ? strtotime((string)($manageGuild['joined_at'] ?? ''))
    : false;

$guildJoinedLabel = $guildJoinedTimestamp !== false
    ? date('d/m/Y H:i', $guildJoinedTimestamp)
    : 'No disponible';

$currentLanguage = (string)($manageSettings['language'] ?? 'es');
$currentTimezone = (string)($manageSettings['timezone'] ?? 'America/Argentina/Buenos_Aires');

$timezoneOptions = [
    'America/Argentina/Buenos_Aires',
    'America/Argentina/Cordoba',
    'America/Argentina/Mendoza',
    'America/Santiago',
    'America/Sao_Paulo',
    'America/Montevideo',
    'America/Mexico_City',
    'America/Bogota',
    'America/Lima',
    'America/New_York',
    'America/Los_Angeles',
    'Europe/Madrid',
    'Europe/London',
    'Europe/Paris',
    'Europe/Berlin',
    'Asia/Tokyo',
    'UTC',
];

if (!in_array($currentTimezone, $timezoneOptions, true)) {
    $timezoneOptions[] = $currentTimezone;
}

sort($timezoneOptions);
?>

<section class="page-banner discord-manage-banner">
    <span class="eyebrow">INTEGRACIÓN CON DISCORD</span>
    <h1>Administrar <span>servidor.</span></h1>
    <p>Información y configuración de tu servidor conectado a Foxcraftservice.</p>
</section>

<section class="discord-manage-page">
    <?php if (!is_array($manageGuild) || $discordManageError !== null): ?>
        <div class="discord-manage-error">
            <h2>No se puede abrir el panel</h2>
            <p><?= e($discordManageError ?? 'No hay información disponible para este servidor.') ?></p>

            <a class="button button-primary" href="/?page=profile">
                Volver a mi perfil
            </a>

            <a class="button button-outline" href="/?page=discord-guilds">
                Actualizar servidores
            </a>
        </div>
    <?php else: ?>
        <div class="discord-manage-card">
            <div class="discord-manage-header">
                <div class="discord-manage-icon">
                    <?php if ($guildIconUrl !== ''): ?>
                        <img src="<?= e($guildIconUrl) ?>" alt="" loading="lazy">
                    <?php else: ?>
                        <?= e(mb_strtoupper(mb_substr((string)$manageGuild['name'], 0, 1))) ?>
                    <?php endif; ?>
                </div>

                <div class="discord-manage-title">
                    <span class="eyebrow">SERVIDOR CONECTADO</span>
                    <h2><?= e((string)$manageGuild['name']) ?></h2>
                    <p>ID: <?= e((string)$manageGuild['guild_id']) ?></p>
                    <span class="discord-manage-status">Foxcraftservice conectado</span>
                </div>
            </div>

            <div class="discord-manage-grid">
                <div class="discord-manage-detail">
                    <span>Tu rol en Discord</span>
                    <strong><?= e((string)($discordManageData['role'] ?? 'No disponible')) ?></strong>
                </div>

                <div class="discord-manage-detail">
                    <span>Bot</span>
                    <strong>Activo</strong>
                </div>

                <div class="discord-manage-detail">
                    <span>Idioma del servidor</span>
                    <strong><?= e($currentLanguage) ?></strong>
                </div>

                <div class="discord-manage-detail">
                    <span>Zona horaria</span>
                    <strong><?= e($currentTimezone) ?></strong>
                </div>

                <div class="discord-manage-detail">
                    <span>Conectado desde</span>
                    <strong><?= e($guildJoinedLabel) ?></strong>
                </div>

                <div class="discord-manage-detail">
                    <span>Estado</span>
                    <strong>Conectado y activo</strong>
                </div>
            </div>

            <div class="discord-manage-section">
                <h3>Configuración general</h3>

                <?php if ($canEditDiscordSettings): ?>
                    <p>Configurá el idioma y la zona horaria que utilizará este servidor.</p>

                    <form
                        class="discord-manage-form"
                        method="POST"
                        action="/?page=discord-manage&amp;id=<?= rawurlencode((string)$manageGuild['guild_id']) ?>"
                    >
                        <input
                            type="hidden"
                            name="csrf_token"
                            value="<?= e((string)$_SESSION['csrf_token']) ?>"
                        >

                        <div class="discord-manage-field">
                            <label for="discord-language">Idioma</label>

                            <select id="discord-language" name="language" required>
                                <option value="es" <?= $currentLanguage === 'es' ? 'selected' : '' ?>>
                                    Español
                                </option>
                                <option value="en" <?= $currentLanguage === 'en' ? 'selected' : '' ?>>
                                    English
                                </option>
                            </select>
                        </div>

                        <div class="discord-manage-field">
                            <label for="discord-timezone">Zona horaria</label>

                            <select id="discord-timezone" name="timezone" required>
                                <?php foreach ($timezoneOptions as $timezoneOption): ?>
                                    <option
                                        value="<?= e($timezoneOption) ?>"
                                        <?= $currentTimezone === $timezoneOption ? 'selected' : '' ?>
                                    >
                                        <?= e($timezoneOption) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="discord-manage-actions">
                            <button class="button button-primary" type="submit">
                                Guardar configuración
                            </button>
                        </div>
                    </form>
                <?php else: ?>
                    <p>
                        Tu cuenta puede consultar este servidor, pero necesitás permisos
                        de propietario, administrador o gestionar servidor para modificar
                        su configuración.
                    </p>
                <?php endif; ?>
            </div>

            <div class="discord-manage-actions">
                <a class="button button-outline" href="/?page=profile">
                    Volver a mi perfil
                </a>

                <a class="button button-primary" href="/?page=discord-guilds">
                    Actualizar permisos de Discord
                </a>
            </div>
        </div>
    <?php endif; ?>
</section>
