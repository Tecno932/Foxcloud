<?php

declare(strict_types=1);

namespace FoxCraft\Repositories;

use PDO;

final class AuthenticationRepository
{
    public function __construct(
        private readonly PDO $pdo
    ) {
    }

    public function authenticate(string $identity, string $password): array
    {
        $query = $this->pdo->prepare(
            'SELECT id, username, password_hash, status
             FROM users
             WHERE username = :username OR email = :email
             LIMIT 1'
        );

        $query->execute([
            'username' => $identity,
            'email' => $identity,
        ]);

        $user = $query->fetch();

        if (!$user || !password_verify($password, $user['password_hash'])) {
            return ['success' => false, 'reason' => 'invalid_credentials'];
        }

        if ($user['status'] !== 'active') {
            return ['success' => false, 'reason' => 'inactive_account'];
        }

        if (password_needs_rehash($user['password_hash'], PASSWORD_DEFAULT)) {
            $newPasswordHash = password_hash($password, PASSWORD_DEFAULT);

            $rehash = $this->pdo->prepare(
                'UPDATE users SET password_hash = :password_hash WHERE id = :id'
            );

            $rehash->execute([
                'password_hash' => $newPasswordHash,
                'id' => $user['id'],
            ]);
        }

        $update = $this->pdo->prepare(
            'UPDATE users SET last_login_at = NOW() WHERE id = :id'
        );
        $update->execute(['id' => $user['id']]);

        return [
            'success' => true,
            'user' => [
                'id' => (int) $user['id'],
                'username' => $user['username'],
            ],
        ];
    }
}
