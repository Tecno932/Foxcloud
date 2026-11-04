<?php

declare(strict_types=1);

namespace FoxCraft\Repositories;

use PDO;

final class CategoryRepository
{
    public function __construct(
        private readonly PDO $pdo
    ) {
    }

    public function findActive(): array
    {
        $query = $this->pdo->query(
            'SELECT id, name, slug, description
             FROM categories
             WHERE is_active = 1
             ORDER BY sort_order, name'
        );

        return $query->fetchAll();
    }

    public function findActiveIdBySlug(string $slug): int|false
    {
        $query = $this->pdo->prepare(
            'SELECT id
             FROM categories
             WHERE slug = :slug AND is_active = 1
             LIMIT 1'
        );

        $query->execute(['slug' => $slug]);

        $id = $query->fetchColumn();

        return $id === false ? false : (int) $id;
    }


    public function isActiveById(int $categoryId): bool
    {
        $query = $this->pdo->prepare(
            'SELECT id
             FROM categories
             WHERE id = :id AND is_active = 1
             LIMIT 1'
        );

        $query->execute(['id' => $categoryId]);

        return $query->fetchColumn() !== false;
    }

}
