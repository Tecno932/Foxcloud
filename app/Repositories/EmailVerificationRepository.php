<?php
declare(strict_types=1);

namespace FoxCraft\Repositories;

use PDO;

final class EmailVerificationRepository
{
    public function __construct(
        private readonly PDO $pdo
    ) {
    }

    public function verify(string $token): bool
    {
        if (!preg_match('/\A[a-f0-9]{64}\z/', $token)) {
            return false;
        }

        $tokenHash = hash('sha256', $token);

        try {
            $this->pdo->beginTransaction();

            $query = $this->pdo->prepare(
                'SELECT user_id
                 FROM email_verification_tokens
                 WHERE token_hash = :token_hash
                   AND expires_at > NOW()
                 LIMIT 1
                 FOR UPDATE'
            );

            $query->execute([
                'token_hash' => $tokenHash,
            ]);

            $record = $query->fetch(PDO::FETCH_ASSOC);

            if (!$record) {
                $this->pdo->rollBack();
                return false;
            }

            $userId = (int) $record['user_id'];

            $update = $this->pdo->prepare(
                "UPDATE users
                 SET status = 'active',
                     email_verified_at = NOW()
                 WHERE id = :user_id
                   AND status = 'pending'"
            );

            $update->execute([
                'user_id' => $userId,
            ]);

            if ($update->rowCount() !== 1) {
                $this->pdo->rollBack();
                return false;
            }

            $delete = $this->pdo->prepare(
                'DELETE FROM email_verification_tokens
                 WHERE user_id = :user_id'
            );

            $delete->execute([
                'user_id' => $userId,
            ]);

            $this->pdo->commit();

            return true;
        } catch (\Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            throw $exception;
        }
    }

    public function resend(string $email): ?array
    {
        $email = strtolower(trim($email));

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return null;
        }

        try {
            $this->pdo->beginTransaction();

            $query = $this->pdo->prepare(
                "SELECT u.id, u.username, t.created_at
                 FROM users u
                 INNER JOIN email_verification_tokens t
                    ON t.user_id = u.id
                 WHERE u.email = :email
                   AND u.status = 'pending'
                   AND u.email_verified_at IS NULL
                 LIMIT 1
                 FOR UPDATE"
            );

            $query->execute([
                'email' => $email,
            ]);

            $user = $query->fetch(PDO::FETCH_ASSOC);

            if (!$user) {
                $this->pdo->rollBack();
                return null;
            }

            $createdAt = strtotime((string) $user['created_at']);

            if ($createdAt !== false && $createdAt > time() - 60) {
                $this->pdo->rollBack();
                return null;
            }

            $token = bin2hex(random_bytes(32));
            $tokenHash = hash('sha256', $token);

            $update = $this->pdo->prepare(
                'UPDATE email_verification_tokens
                 SET token_hash = :token_hash,
                     expires_at = DATE_ADD(NOW(), INTERVAL 24 HOUR),
                     created_at = CURRENT_TIMESTAMP
                 WHERE user_id = :user_id'
            );

            $update->execute([
                'token_hash' => $tokenHash,
                'user_id' => (int) $user['id'],
            ]);

            $this->pdo->commit();

            return [
                'username' => (string) $user['username'],
                'email' => $email,
                'token' => $token,
            ];
        } catch (\Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            throw $exception;
        }
    }
}
