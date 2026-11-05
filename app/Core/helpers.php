<?php

declare(strict_types=1);

if (!function_exists('e')) {
    function e(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

if (!function_exists('image_display_url')) {
    function image_display_url(string $imageUrl): string
    {
        $parts = parse_url($imageUrl);

        if (!is_array($parts) || empty($parts['path'])) {
            return $imageUrl;
        }

        $path = $parts['path'];

        if (!str_starts_with($path, '/assets/images/')) {
            return $imageUrl;
        }

        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        if (!in_array($extension, ['png', 'jpg', 'jpeg'], true)) {
            return $imageUrl;
        }

        $webpPath = '/assets/images-webp/' .
            substr($path, strlen('/assets/images/'));

        $webpPath = substr($webpPath, 0, -strlen($extension)) . 'webp';

        $webpFile = PROJECT_ROOT . '/public' . rawurldecode($webpPath);

        if (!is_file($webpFile)) {
            return $imageUrl;
        }

        if (isset($parts['query'])) {
            $webpPath .= '?' . $parts['query'];
        }

        if (isset($parts['fragment'])) {
            $webpPath .= '#' . $parts['fragment'];
        }

        return $webpPath;
    }
}
