<?php

declare(strict_types=1);

namespace FoxCraft\Repositories;

use PDO;

final class PasswordResetRepository
{
    public function __construct(
        private readonly PDO $pdo
    ) {
    }

    public function create(string $email): ?array
    {
        $query = $this->pdo->prepare(
            "SELECT id, username, email
             FROM users
             WHERE email = :email
               AND status = 'active'
               AND email_verified_at IS NOT NULL
             LIMIT 1"
        );

        $query->execute(['email' => $email]);
        $user = $query->fetch();

        if (!$user) {
            return null;
        }

        $tokenQuery = $this->pdo->prepare(
            'SELECT created_at
             FROM password_reset_tokens
             WHERE user_id = :user_id
             LIMIT 1'
        );

        $tokenQuery->execute(['user_id' => $user['id']]);
        $existingToken = $tokenQuery->fetch();

        if (
            $existingToken
            && strtotime($existingToken['created_at']) > time() - 60
        ) {
            return null;
        }

        $token = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $token);

        $this->pdo->beginTransaction();

        try {
            $save = $this->pdo->prepare(
                'INSERT INTO password_reset_tokens
                    (user_id, token_hash, expires_at)
                 VALUES
                    (:user_id, :token_hash, DATE_ADD(NOW(), INTERVAL 30 MINUTE))
                 ON DUPLICATE KEY UPDATE
                    token_hash = VALUES(token_hash),
                    expires_at = VALUES(expires_at),
                    created_at = CURRENT_TIMESTAMP'
            );

            $save->execute([
                'user_id' => $user['id'],
                'token_hash' => $tokenHash,
            ]);

            $this->pdo->commit();
        } catch (\Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            throw $exception;
        }

        return [
            'username' => $user['username'],
            'email' => $user['email'],
            'token' => $token,
        ];
    }

    public function reset(string $token, string $password): bool
    {
        if (!preg_match('/\A[a-f0-9]{64}\z/', $token)) {
            return false;
        }

        $tokenHash = hash('sha256', $token);

        $this->pdo->beginTransaction();

        try {
            $query = $this->pdo->prepare(
                'SELECT user_id
                 FROM password_reset_tokens
                 WHERE token_hash = :token_hash
                   AND expires_at > NOW()
                 LIMIT 1
                 FOR UPDATE'
            );

            $query->execute(['token_hash' => $tokenHash]);
            $record = $query->fetch();

            if (!$record) {
                $this->pdo->rollBack();
                return false;
            }

            $passwordHash = password_hash($password, PASSWORD_DEFAULT);

            $update = $this->pdo->prepare(
                'UPDATE users
                 SET password_hash = :password_hash
                 WHERE id = :user_id
                   AND status = "active"'
            );

            $update->execute([
                'password_hash' => $passwordHash,
                'user_id' => $record['user_id'],
            ]);

            if ($update->rowCount() !== 1) {
                $this->pdo->rollBack();
                return false;
            }

            $delete = $this->pdo->prepare(
                'DELETE FROM password_reset_tokens
                 WHERE user_id = :user_id'
            );

            $delete->execute(['user_id' => $record['user_id']]);

            $this->pdo->commit();
            return true;
        } catch (\Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            throw $exception;
        }
    }
}
