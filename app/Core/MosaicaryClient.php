<?php

declare(strict_types=1);

namespace FoxCraft\Core;

use RuntimeException;

final class MosaicaryClient
{
    private const API_URL = 'https://mosaicary.com/api/skins';

    public function search(string $query, int $perPage, int $page): array
    {
        $configFile = '/etc/foxcraft/mosaicary.php';

        if (!is_readable($configFile)) {
            throw new RuntimeException('La configuración de Mosaicary no está disponible.');
        }

        $config = require $configFile;
        $apiKey = $config['api_key'] ?? '';

        if (!is_string($apiKey) || $apiKey === '') {
            throw new RuntimeException('La API key de Mosaicary no está configurada.');
        }

        $params = [
            'source' => 'all',
            'per' => max(1, min(100, $perPage)),
            'page' => max(1, $page),
        ];

        if ($query !== '') {
            $params['q'] = $query;
        }

        $url = self::API_URL . '?' . http_build_query($params);

        $curl = curl_init($url);

        if ($curl === false) {
            throw new RuntimeException('No se pudo iniciar la conexión con Mosaicary.');
        }

        curl_setopt_array($curl, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_CONNECTTIMEOUT => 8,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $apiKey,
                'Accept: application/json',
            ],
        ]);

        $response = curl_exec($curl);
        $status = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $error = curl_error($curl);

        curl_close($curl);

        if ($response === false) {
            throw new RuntimeException('Error de conexión con Mosaicary: ' . $error);
        }

        if ($status !== 200) {
            throw new RuntimeException('Mosaicary respondió con HTTP ' . $status . '.');
        }

        try {
            $data = json_decode($response, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            throw new RuntimeException('Mosaicary devolvió una respuesta JSON inválida.');
        }

        if (!is_array($data) || !isset($data['skins']) || !is_array($data['skins'])) {
            throw new RuntimeException('La respuesta de Mosaicary no tiene el formato esperado.');
        }

        return [
            'skins' => $data['skins'],
            'total' => max(0, (int) ($data['total'] ?? count($data['skins']))),
        ];
    }
}
