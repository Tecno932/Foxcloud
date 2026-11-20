<?php

declare(strict_types=1);

namespace FoxCraft\Services;

use RuntimeException;

final class ChatTokenService
{
    private const CONFIG_FILE = '/etc/foxcraft/chat.env';
    private const TOKEN_TTL = 60;

    public static function create(int $userId): string
    {
        if ($userId < 1) {
            throw new RuntimeException('Usuario inválido.');
        }

        $config = parse_ini_file(
            self::CONFIG_FILE,
            false,
            INI_SCANNER_RAW
        );

        if (
            !is_array($config)
            || empty($config['CHAT_TOKEN_SECRET'])
        ) {
            throw new RuntimeException('Configuración de chat inválida.');
        }

        $secret = (string) $config['CHAT_TOKEN_SECRET'];

        $payload = self::base64UrlEncode(
            json_encode([
                'user_id' => (string) $userId,
                'exp' => time() + self::TOKEN_TTL,
            ], JSON_THROW_ON_ERROR)
        );

        $signature = self::base64UrlEncode(
            hash_hmac('sha256', $payload, $secret, true)
        );

        return $payload . '.' . $signature;
    }

    private static function base64UrlEncode(string $value): string
    {
        return rtrim(
            strtr(base64_encode($value), '+/', '-_'),
            '='
        );
    }
}
