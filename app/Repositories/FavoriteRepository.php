<?php

declare(strict_types=1);

namespace FoxCraft\Repositories;

use PDO;

final class FavoriteRepository
{
    public function __construct(
        private readonly PDO $pdo
    ) {
    }

    public function add(int $userId, int $contentId): bool
    {
        $query = $this->pdo->prepare(
            "INSERT IGNORE INTO favorites (user_id, content_id)
             SELECT :user_id, c.id
             FROM contents c
             JOIN categories cat ON cat.id = c.category_id
             WHERE c.id = :content_id
               AND c.status = 'published'
               AND cat.is_active = 1"
        );

        $query->execute([
            'user_id' => $userId,
            'content_id' => $contentId,
        ]);

        return $query->rowCount() === 1;
    }

    public function remove(int $userId, int $contentId): bool
    {
        $query = $this->pdo->prepare(
            'DELETE FROM favorites
             WHERE user_id = :user_id
               AND content_id = :content_id'
        );

        $query->execute([
            'user_id' => $userId,
            'content_id' => $contentId,
        ]);

        return $query->rowCount() === 1;
    }

    public function isFavorite(int $userId, int $contentId): bool
    {
        $query = $this->pdo->prepare(
            'SELECT 1
             FROM favorites
             WHERE user_id = :user_id
               AND content_id = :content_id
             LIMIT 1'
        );

        $query->execute([
            'user_id' => $userId,
            'content_id' => $contentId,
        ]);

        return $query->fetchColumn() !== false;
    }

    public function findByUserId(int $userId, int $limit = 100): array
    {
        $limit = max(1, min($limit, 100));

        $query = $this->pdo->prepare(
            "SELECT c.id, c.title, c.slug, c.description, c.image_url,
                    c.downloads_count, c.likes_count,
                    cat.name AS category_name,
                    cat.slug AS category_slug
             FROM favorites f
             JOIN contents c ON c.id = f.content_id
             JOIN categories cat ON cat.id = c.category_id
             WHERE f.user_id = :user_id
               AND c.status = 'published'
               AND cat.is_active = 1
             ORDER BY f.created_at DESC
             LIMIT {$limit}"
        );

        $query->execute(['user_id' => $userId]);

        return $query->fetchAll();
    }
}
