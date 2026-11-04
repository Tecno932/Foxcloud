<?php

declare(strict_types=1);

namespace FoxCraft\Repositories;

use PDO;

final class LikeRepository
{
    public function __construct(
        private readonly PDO $pdo
    ) {
    }

    public function add(int $userId, int $contentId): bool
    {
        $this->pdo->beginTransaction();

        try {
            $query = $this->pdo->prepare(
                "INSERT IGNORE INTO likes (user_id, content_id)
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

            $inserted = $query->rowCount() === 1;

            if ($inserted) {
                $update = $this->pdo->prepare(
                    'UPDATE contents
                     SET likes_count = likes_count + 1
                     WHERE id = :content_id'
                );

                $update->execute(['content_id' => $contentId]);
            }

            $this->pdo->commit();

            return $inserted;
        } catch (\Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            throw $exception;
        }
    }

    public function remove(int $userId, int $contentId): bool
    {
        $this->pdo->beginTransaction();

        try {
            $query = $this->pdo->prepare(
                'DELETE FROM likes
                 WHERE user_id = :user_id
                   AND content_id = :content_id'
            );

            $query->execute([
                'user_id' => $userId,
                'content_id' => $contentId,
            ]);

            $removed = $query->rowCount() === 1;

            if ($removed) {
                $update = $this->pdo->prepare(
                    'UPDATE contents
                     SET likes_count = GREATEST(0, likes_count - 1)
                     WHERE id = :content_id'
                );

                $update->execute(['content_id' => $contentId]);
            }

            $this->pdo->commit();

            return $removed;
        } catch (\Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            throw $exception;
        }
    }

    public function isLiked(int $userId, int $contentId): bool
    {
        $query = $this->pdo->prepare(
            'SELECT 1
             FROM likes
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
            FROM likes l
            JOIN contents c ON c.id = l.content_id
            JOIN categories cat ON cat.id = c.category_id
            WHERE l.user_id = :user_id
            AND c.status = 'published'
            AND cat.is_active = 1
            ORDER BY l.created_at DESC
            LIMIT {$limit}"
        );

        $query->execute(['user_id' => $userId]);

        return $query->fetchAll();
    }

    public function countByContentId(int $contentId): int
    {
        $query = $this->pdo->prepare(
            'SELECT COUNT(*)
             FROM likes
             WHERE content_id = :content_id'
        );

        $query->execute(['content_id' => $contentId]);

        return (int) $query->fetchColumn();
    }
}
