<?php

declare(strict_types=1);

namespace FoxCraft\Repositories;

use PDO;
use Throwable;

final class BlockRepository
{
    public function __construct(
        private readonly PDO $pdo
    ) {
    }

    public function block(int $blockerId, int $blockedId): bool
    {
        if (
            $blockerId < 1 ||
            $blockedId < 1 ||
            $blockerId === $blockedId
        ) {
            return false;
        }

        try {
            $this->pdo->beginTransaction();

            $query = $this->pdo->prepare(
                "INSERT IGNORE INTO user_blocks (blocker_id, blocked_id)
                 SELECT :blocker_id, u.id
                 FROM users u
                 WHERE u.id = :blocked_id
                   AND u.status = 'active'"
            );

            $query->execute([
                'blocker_id' => $blockerId,
                'blocked_id' => $blockedId,
            ]);

            $created = $query->rowCount() === 1;

            $query = $this->pdo->prepare(
                'DELETE FROM user_follows
                 WHERE (follower_id = :blocker_1
                    AND followed_id = :blocked_1)
                    OR (follower_id = :blocked_2
                    AND followed_id = :blocker_2)'
            );

            $query->execute([
                'blocker_1' => $blockerId,
                'blocked_1' => $blockedId,
                'blocked_2' => $blockedId,
                'blocker_2' => $blockerId,
            ]);

            $this->pdo->commit();

            return $created;
        } catch (Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            throw $exception;
        }
    }

    public function unblock(int $blockerId, int $blockedId): bool
    {
        $query = $this->pdo->prepare(
            'DELETE FROM user_blocks
             WHERE blocker_id = :blocker_id
               AND blocked_id = :blocked_id'
        );

        $query->execute([
            'blocker_id' => $blockerId,
            'blocked_id' => $blockedId,
        ]);

        return $query->rowCount() === 1;
    }

    public function isBlocked(int $blockerId, int $blockedId): bool
    {
        $query = $this->pdo->prepare(
            'SELECT 1
             FROM user_blocks
             WHERE blocker_id = :blocker_id
               AND blocked_id = :blocked_id
             LIMIT 1'
        );

        $query->execute([
            'blocker_id' => $blockerId,
            'blocked_id' => $blockedId,
        ]);

        return $query->fetchColumn() !== false;
    }

    public function hasBlockBetween(int $userId, int $otherUserId): bool
    {
        $query = $this->pdo->prepare(
            'SELECT 1
             FROM user_blocks
             WHERE (blocker_id = :user_1 AND blocked_id = :other_1)
                OR (blocker_id = :other_2 AND blocked_id = :user_2)
             LIMIT 1'
        );

        $query->execute([
            'user_1' => $userId,
            'other_1' => $otherUserId,
            'other_2' => $otherUserId,
            'user_2' => $userId,
        ]);

        return $query->fetchColumn() !== false;
    }
}