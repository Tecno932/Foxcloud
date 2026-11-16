<?php

declare(strict_types=1);

namespace FoxCraft\Repositories;

use PDO;
use RuntimeException;

final class DiscordAccountRepository
{
    public function __construct(
        private readonly PDO $pdo
    ) {
    }

    public function findByUserId(int $userId): ?array
    {
        $statement = $this->pdo->prepare(
            'SELECT
                user_id,
                discord_user_id,
                username,
                global_name,
                avatar_hash,
                linked_at
             FROM discord_accounts
             WHERE user_id = :user_id
             LIMIT 1'
        );

        $statement->execute(['user_id' => $userId]);

        $account = $statement->fetch(PDO::FETCH_ASSOC);

        return $account ?: null;
    }

    public function link(
        int $userId,
        string $discordUserId,
        string $username,
        ?string $globalName,
        ?string $avatarHash
    ): void {
        if ($userId < 1 || !preg_match('/^[0-9]{17,20}$/', $discordUserId)) {
            throw new RuntimeException('Los datos de la cuenta Discord no son válidos.');
        }

        $username = trim($username);
        $globalName = $globalName !== null ? trim($globalName) : null;

        if ($username === '' || mb_strlen($username) > 100) {
            throw new RuntimeException('El nombre de usuario de Discord no es válido.');
        }

        if ($globalName !== null && mb_strlen($globalName) > 100) {
            throw new RuntimeException('El nombre global de Discord no es válido.');
        }

        if ($avatarHash !== null && !preg_match('/^[a-zA-Z0-9_]{1,100}$/', $avatarHash)) {
            throw new RuntimeException('El avatar de Discord no es válido.');
        }

        $this->pdo->beginTransaction();

        try {
            $statement = $this->pdo->prepare(
                'SELECT discord_user_id
                 FROM discord_accounts
                 WHERE user_id = :user_id
                 LIMIT 1
                 FOR UPDATE'
            );

            $statement->execute(['user_id' => $userId]);
            $existingAccount = $statement->fetch(PDO::FETCH_ASSOC);

            if ($existingAccount) {
                if (!hash_equals(
                    (string) $existingAccount['discord_user_id'],
                    $discordUserId
                )) {
                    throw new RuntimeException(
                        'Esta cuenta FoxCraft ya tiene otro Discord vinculado. Desvinculalo primero.'
                    );
                }

                $statement = $this->pdo->prepare(
                    'UPDATE discord_accounts
                     SET username = :username,
                         global_name = :global_name,
                         avatar_hash = :avatar_hash
                     WHERE user_id = :user_id'
                );

                $statement->execute([
                    'username' => $username,
                    'global_name' => $globalName ?: null,
                    'avatar_hash' => $avatarHash,
                    'user_id' => $userId,
                ]);

                $this->pdo->commit();
                return;
            }

            $statement = $this->pdo->prepare(
                'SELECT user_id
                 FROM discord_accounts
                 WHERE discord_user_id = :discord_user_id
                 LIMIT 1'
            );

            $statement->execute(['discord_user_id' => $discordUserId]);

            if ($statement->fetch(PDO::FETCH_ASSOC)) {
                throw new RuntimeException(
                    'Esta cuenta Discord ya está vinculada a otra cuenta FoxCraft.'
                );
            }

            $statement = $this->pdo->prepare(
                'INSERT INTO discord_accounts (
                    user_id,
                    discord_user_id,
                    username,
                    global_name,
                    avatar_hash
                 ) VALUES (
                    :user_id,
                    :discord_user_id,
                    :username,
                    :global_name,
                    :avatar_hash
                 )'
            );

            $statement->execute([
                'user_id' => $userId,
                'discord_user_id' => $discordUserId,
                'username' => $username,
                'global_name' => $globalName ?: null,
                'avatar_hash' => $avatarHash,
            ]);

            $this->pdo->commit();
        } catch (\Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            if (
                $exception instanceof \PDOException
                && $exception->getCode() === '23000'
            ) {
                throw new RuntimeException(
                    'Esta cuenta Discord ya está vinculada a otra cuenta FoxCraft.',
                    0,
                    $exception
                );
            }

            throw $exception;
        }
    }

    public function unlink(int $userId): bool
    {
        $statement = $this->pdo->prepare(
            'DELETE FROM discord_accounts
             WHERE user_id = :user_id'
        );

        $statement->execute(['user_id' => $userId]);

        return $statement->rowCount() > 0;
    }
}