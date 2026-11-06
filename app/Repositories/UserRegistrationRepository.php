<?php

declare(strict_types=1);

namespace FoxCraft\Repositories;

use PDO;
use Throwable;

final class UserRegistrationRepository
{
    public function __construct(
        private readonly PDO $pdo
    ) {
    }

    public function register(
        string $username,
        string $email,
        string $password
    ): string {
        try {
            $this->pdo->beginTransaction();

            $check = $this->pdo->prepare(
                'SELECT id
                 FROM users
                 WHERE username = :username OR email = :email
                 LIMIT 1'
            );

            $check->execute([
                'username' => $username,
                'email' => $email,
            ]);

            if ($check->fetch()) {
                throw new \DomainException(
                    'Ese nombre de usuario o correo ya está registrado.'
                );
            }

            $insert = $this->pdo->prepare(
                "INSERT INTO users (username, email, password_hash, role, status)
                 VALUES (:username, :email, :password_hash, 'user', 'pending')"
            );

            $insert->execute([
                'username' => $username,
                'email' => $email,
                'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            ]);

            $userId = (int) $this->pdo->lastInsertId();

            $profile = $this->pdo->prepare(
                'INSERT INTO profiles (user_id, display_name)
                 VALUES (:id, :name)'
            );

            $profile->execute([
                'id' => $userId,
                'name' => $username,
            ]);

            $token = bin2hex(random_bytes(32));
            $tokenHash = hash('sha256', $token);

            $tokenInsert = $this->pdo->prepare(
                'INSERT INTO email_verification_tokens
                    (user_id, token_hash, expires_at)
                 VALUES
                    (:user_id, :token_hash, DATE_ADD(NOW(), INTERVAL 24 HOUR))'
            );

            $tokenInsert->execute([
                'user_id' => $userId,
                'token_hash' => $tokenHash,
            ]);

            $this->pdo->commit();

            return $token;
        } catch (Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            throw $exception;
        }
    }
}