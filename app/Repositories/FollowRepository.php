<?php

declare(strict_types=1);

namespace FoxCraft\Repositories;

use PDO;

final class FollowRepository
{
    public function __construct(
        private readonly PDO $pdo
    ) {
    }

    public function follow(int $followerId, int $followedId): bool
    {
        if ($followerId < 1 || $followedId < 1 || $followerId === $followedId) {
            return false;
        }

        $query = $this->pdo->prepare(
            "INSERT IGNORE INTO user_follows (follower_id, followed_id)
            SELECT :follower_id, u.id
            FROM users u
            WHERE u.id = :followed_id
            AND u.status = 'active'
            AND NOT EXISTS (
                SELECT 1
                FROM user_blocks b
                WHERE (
                    b.blocker_id = :blocker_1
                    AND b.blocked_id = :blocked_1
                )
                OR (
                    b.blocker_id = :blocker_2
                    AND b.blocked_id = :blocked_2
                )
            )"
        );

        $query->execute([
            'follower_id' => $followerId,
            'followed_id' => $followedId,
            'blocker_1' => $followerId,
            'blocked_1' => $followedId,
            'blocker_2' => $followedId,
            'blocked_2' => $followerId,
        ]);

        return $query->rowCount() === 1;
    }

    public function unfollow(int $followerId, int $followedId): bool
    {
        $query = $this->pdo->prepare(
            'DELETE FROM user_follows
             WHERE follower_id = :follower_id
               AND followed_id = :followed_id'
        );

        $query->execute([
            'follower_id' => $followerId,
            'followed_id' => $followedId,
        ]);

        return $query->rowCount() === 1;
    }

    public function isFollowing(int $followerId, int $followedId): bool
    {
        $query = $this->pdo->prepare(
            'SELECT 1
             FROM user_follows
             WHERE follower_id = :follower_id
               AND followed_id = :followed_id
             LIMIT 1'
        );

        $query->execute([
            'follower_id' => $followerId,
            'followed_id' => $followedId,
        ]);

        return $query->fetchColumn() !== false;
    }

    public function countFollowers(int $userId): int
    {
        $query = $this->pdo->prepare(
            'SELECT COUNT(*)
             FROM user_follows
             WHERE followed_id = :user_id'
        );

        $query->execute(['user_id' => $userId]);

        return (int) $query->fetchColumn();
    }

    public function countFollowing(int $userId): int
    {
        $query = $this->pdo->prepare(
            'SELECT COUNT(*)
             FROM user_follows
             WHERE follower_id = :user_id'
        );

        $query->execute(['user_id' => $userId]);

        return (int) $query->fetchColumn();
    }

    public function findFollowers(int $userId, int $limit = 50): array
    {
        $limit = max(1, min($limit, 100));

        $query = $this->pdo->prepare(
            "SELECT u.id, u.username, p.display_name, p.avatar_path
             FROM user_follows f
             JOIN users u ON u.id = f.follower_id
             LEFT JOIN profiles p ON p.user_id = u.id
             WHERE f.followed_id = :user_id
               AND u.status = 'active'
             ORDER BY f.created_at DESC
             LIMIT {$limit}"
        );

        $query->execute(['user_id' => $userId]);

        return $query->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findFollowing(int $userId, int $limit = 50): array
    {
        $limit = max(1, min($limit, 100));

        $query = $this->pdo->prepare(
            "SELECT u.id, u.username, p.display_name, p.avatar_path
             FROM user_follows f
             JOIN users u ON u.id = f.followed_id
             LEFT JOIN profiles p ON p.user_id = u.id
             WHERE f.follower_id = :user_id
               AND u.status = 'active'
             ORDER BY f.created_at DESC
             LIMIT {$limit}"
        );

        $query->execute(['user_id' => $userId]);

        return $query->fetchAll(PDO::FETCH_ASSOC);
    }
}
