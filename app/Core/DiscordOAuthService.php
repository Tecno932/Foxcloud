<?php

declare(strict_types=1);

namespace FoxCraft\Core;

use RuntimeException;

final class DiscordOAuthService
{
    private const AUTHORIZE_URL = 'https://discord.com/oauth2/authorize';
    private const TOKEN_URL = 'https://discord.com/api/oauth2/token';
    private const USER_URL = 'https://discord.com/api/users/@me';
    private const GUILDS_URL = 'https://discord.com/api/users/@me/guilds';

    private const PERMISSION_ADMINISTRATOR = 0x8;
    private const PERMISSION_MANAGE_GUILD = 0x20;

    public function __construct(
        private readonly array $config
    ) {
        foreach (['client_id', 'client_secret', 'redirect_uri'] as $key) {
            if (
                !isset($this->config[$key])
                || !is_string($this->config[$key])
                || trim($this->config[$key]) === ''
            ) {
                throw new RuntimeException(
                    'La configuración OAuth de Discord está incompleta.'
                );
            }
        }
    }

    public function getAuthorizationUrl(
        string $state,
        string $scope = 'identify'
    ): string {
        if (!in_array($scope, ['identify', 'identify guilds'], true)) {
            throw new RuntimeException('El permiso OAuth solicitado no es válido.');
        }

        return self::AUTHORIZE_URL . '?' . http_build_query([
            'client_id' => $this->config['client_id'],
            'response_type' => 'code',
            'redirect_uri' => $this->config['redirect_uri'],
            'scope' => $scope,
            'state' => $state,
        ], '', '&', PHP_QUERY_RFC3986);
    }

    /**
     * Mantiene la compatibilidad con la vinculación actual de Discord.
     */
    public function getIdentity(string $authorizationCode): array
    {
        $accessToken = $this->exchangeAuthorizationCode($authorizationCode);

        $user = $this->request(
            self::USER_URL,
            null,
            $accessToken
        );

        return $this->normalizeIdentity($user);
    }

    /**
     * Obtiene la identidad y los servidores administrables
     * utilizando una sola autorización OAuth.
     *
     * Devuelve:
     * [
     *     'identity' => [...],
     *     'guilds' => [...]
     * ]
     */
    public function getIdentityAndGuilds(string $authorizationCode): array
    {
        $accessToken = $this->exchangeAuthorizationCode($authorizationCode);

        $user = $this->request(
            self::USER_URL,
            null,
            $accessToken
        );

        $identity = $this->normalizeIdentity($user);

        $response = $this->request(
            self::GUILDS_URL,
            null,
            $accessToken
        );

        if (!array_is_list($response)) {
            throw new RuntimeException(
                'Discord devolvió una lista de servidores no válida.'
            );
        }

        $managedGuilds = [];

        foreach ($response as $guild) {
            if (!is_array($guild)) {
                continue;
            }

            $guildId = (string)($guild['id'] ?? '');
            $guildName = $guild['name'] ?? null;

            if (
                !preg_match('/^[0-9]{17,20}$/', $guildId)
                || !is_string($guildName)
                || $guildName === ''
                || mb_strlen($guildName) > 100
            ) {
                continue;
            }

            $isOwner = ($guild['owner'] ?? false) === true;

            $permissions = $guild['permissions'] ?? '0';

            if (
                !is_string($permissions)
                || !preg_match('/^[0-9]{1,19}$/', $permissions)
                || (
                    strlen($permissions) === strlen((string)PHP_INT_MAX)
                    && strcmp($permissions, (string)PHP_INT_MAX) > 0
                )
                || strlen($permissions) > strlen((string)PHP_INT_MAX)
            ) {
                $permissions = '0';
            }

            $permissionValue = (int)$permissions;

            $canManage = $isOwner || (
                ($permissionValue & self::PERMISSION_ADMINISTRATOR) !== 0
                || ($permissionValue & self::PERMISSION_MANAGE_GUILD) !== 0
            );

            if (!$canManage) {
                continue;
            }

            $icon = $guild['icon'] ?? null;

            if (!is_string($icon) || $icon === '') {
                $icon = null;
            }

            $managedGuilds[] = [
                'id' => $guildId,
                'name' => $guildName,
                'icon' => $icon,
                'owner' => $isOwner,
                'permissions' => $permissions,
            ];
        }

        return [
            'identity' => $identity,
            'guilds' => $managedGuilds,
        ];
    }

    private function exchangeAuthorizationCode(string $authorizationCode): string
    {
        if (
            $authorizationCode === ''
            || strlen($authorizationCode) > 2048
        ) {
            throw new RuntimeException(
                'El código de autorización no es válido.'
            );
        }

        $tokenData = $this->request(
            self::TOKEN_URL,
            [
                'grant_type' => 'authorization_code',
                'code' => $authorizationCode,
                'redirect_uri' => $this->config['redirect_uri'],
                'client_id' => $this->config['client_id'],
                'client_secret' => $this->config['client_secret'],
            ]
        );

        $accessToken = $tokenData['access_token'] ?? null;

        if (!is_string($accessToken) || $accessToken === '') {
            throw new RuntimeException(
                'Discord no devolvió un token de acceso válido.'
            );
        }

        return $accessToken;
    }

    private function normalizeIdentity(array $user): array
    {
        $discordUserId = (string)($user['id'] ?? '');
        $username = (string)($user['username'] ?? '');

        if (
            !preg_match('/^[0-9]{17,20}$/', $discordUserId)
            || $username === ''
            || mb_strlen($username) > 100
        ) {
            throw new RuntimeException(
                'Discord devolvió una identidad no válida.'
            );
        }

        $globalName = $user['global_name'] ?? null;
        $avatarHash = $user['avatar'] ?? null;

        if (!is_string($globalName) || $globalName === '') {
            $globalName = null;
        }

        if (!is_string($avatarHash) || $avatarHash === '') {
            $avatarHash = null;
        }

        return [
            'id' => $discordUserId,
            'username' => $username,
            'global_name' => $globalName,
            'avatar_hash' => $avatarHash,
        ];
    }

    private function request(
        string $url,
        ?array $postData = null,
        ?string $accessToken = null
    ): array {
        if (!function_exists('curl_init')) {
            throw new RuntimeException(
                'La extensión cURL de PHP no está disponible.'
            );
        }

        $headers = [
            'Accept: application/json',
        ];

        if ($accessToken !== null) {
            $headers[] = 'Authorization: Bearer ' . $accessToken;
        }

        $curl = curl_init($url);

        if ($curl === false) {
            throw new RuntimeException(
                'No se pudo iniciar la conexión con Discord.'
            );
        }

        $options = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_HTTPHEADER => $headers,
        ];

        if ($postData !== null) {
            $options[CURLOPT_POST] = true;
            $options[CURLOPT_POSTFIELDS] = http_build_query(
                $postData,
                '',
                '&',
                PHP_QUERY_RFC3986
            );

            $options[CURLOPT_HTTPHEADER][] =
                'Content-Type: application/x-www-form-urlencoded';
        }

        curl_setopt_array($curl, $options);

        $response = curl_exec($curl);
        $status = (int)curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $curlError = curl_error($curl);

        curl_close($curl);

        if (!is_string($response)) {
            error_log('FoxCraft Discord OAuth cURL: ' . $curlError);

            throw new RuntimeException(
                'No se pudo conectar con Discord.'
            );
        }

        $data = json_decode($response, true);

        if ($status < 200 || $status >= 300 || !is_array($data)) {
            $safeError = [];

            if (is_array($data)) {
                foreach (
                    ['error', 'error_description', 'message', 'code'] as $key
                ) {
                    if (
                        isset($data[$key])
                        && (
                            is_string($data[$key])
                            || is_numeric($data[$key])
                        )
                    ) {
                        $safeError[$key] = (string)$data[$key];
                    }
                }
            }

            error_log(
                'FoxCraft Discord OAuth HTTP ' . $status
                . ' response: '
                . json_encode(
                    $safeError,
                    JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE
                )
            );

            throw new RuntimeException(
                'Discord rechazó la solicitud de autorización.'
            );
        }

        return $data;
    }
}
