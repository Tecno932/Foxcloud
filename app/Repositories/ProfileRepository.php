<?php

declare(strict_types=1);

namespace FoxCraft\Repositories;

use PDO;

final class ProfileRepository
{
    public function __construct(
        private readonly PDO $pdo
    ) {
    }

    public function findByUserId(int $userId): ?array
    {
        $query = $this->pdo->prepare(
            'SELECT
                u.id,
                u.username,
                u.email,
                p.display_name,
                p.bio,
                p.avatar_path,
                p.website_url,
                p.created_at,
                p.updated_at
             FROM users u
             LEFT JOIN profiles p ON p.user_id = u.id
             WHERE u.id = :user_id
             LIMIT 1'
        );

        $query->execute(['user_id' => $userId]);
        $profile = $query->fetch(PDO::FETCH_ASSOC);

        return $profile ?: null;
    }

    public function findByUsername(string $username): ?array
    {
        $query = $this->pdo->prepare(
            'SELECT
                u.id,
                u.username,
                u.email,
                p.display_name,
                p.bio,
                p.avatar_path,
                p.website_url,
                p.created_at,
                p.updated_at
            FROM users u
            LEFT JOIN profiles p ON p.user_id = u.id
            WHERE u.username = :username
            AND u.status = \'active\'
            LIMIT 1'
        );

        $query->execute(['username' => $username]);
        $profile = $query->fetch(PDO::FETCH_ASSOC);

        return $profile ?: null;
    }

    public function save(
        int $userId,
        string $displayName,
        string $bio,
        ?string $websiteUrl,
        ?string $avatarPath = null
    ): void {
        $query = $this->pdo->prepare(
            'INSERT INTO profiles
                (user_id, display_name, bio, website_url, avatar_path)
             VALUES
                (:user_id, :display_name, :bio, :website_url, :avatar_path)
             ON DUPLICATE KEY UPDATE
                display_name = VALUES(display_name),
                bio = VALUES(bio),
                website_url = VALUES(website_url),
                avatar_path = COALESCE(VALUES(avatar_path), avatar_path)'
        );

        $query->execute([
            'user_id' => $userId,
            'display_name' => $displayName !== '' ? $displayName : null,
            'bio' => $bio !== '' ? $bio : null,
            'website_url' => $websiteUrl,
            'avatar_path' => $avatarPath,
        ]);
    }
}
