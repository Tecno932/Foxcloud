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
    ): void {
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
                 VALUES (:username, :email, :password_hash, 'user', 'active')"
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

            $this->pdo->commit();
        } catch (Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            throw $exception;
        }
    }
}
