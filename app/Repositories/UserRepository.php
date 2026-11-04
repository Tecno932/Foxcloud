<?php

declare(strict_types=1);

namespace FoxCraft\Repositories;

use PDO;

final class UserRepository
{
    public function __construct(
        private readonly PDO $pdo
    ) {
    }

    public function isActiveAdmin(int $userId): bool
    {
        $query = $this->pdo->prepare(
            "SELECT role
             FROM users
             WHERE id = :id
               AND status = 'active'
             LIMIT 1"
        );

        $query->execute(['id' => $userId]);

        return $query->fetchColumn() === 'admin';
    }
}
