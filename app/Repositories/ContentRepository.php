<?php

declare(strict_types=1);

namespace FoxCraft\Repositories;

use PDO;

final class ContentRepository
{
    public function __construct(
        private readonly PDO $pdo
    ) {
    }

    public function findPublishedHome(): array
    {

        $query = $this->pdo->query(
            "SELECT c.id, c.title, c.slug, c.description, c.image_url, c.platform,
                    c.downloads_count, c.likes_count,
                    cat.name AS category_name, cat.slug AS category_slug
             FROM contents c
             JOIN categories cat ON cat.id = c.category_id
             WHERE c.status = 'published' AND cat.is_active = 1
             ORDER BY c.is_featured DESC, c.created_at DESC"
        );

        return $query->fetchAll();
    }

    public function findPublishedByCategory(
        int $categoryId,
        int $limit = 12,
        int $offset = 0
    ): array {
        $limit = max(1, min($limit, 100));
        $offset = max(0, $offset);

        $query = $this->pdo->prepare(
            "SELECT c.id, c.title, c.slug, c.description, c.image_url, c.platform,
                    c.downloads_count, c.likes_count,
                    cat.name AS category_name, cat.slug AS category_slug
            FROM contents c
            JOIN categories cat ON cat.id = c.category_id
            WHERE c.status = 'published'
            AND c.category_id = :category_id
            AND cat.is_active = 1
            ORDER BY c.created_at DESC
            LIMIT {$limit} OFFSET {$offset}"
        );

        $query->execute(['category_id' => $categoryId]);

        return $query->fetchAll();
    }

    public function countPublishedByCategory(int $categoryId): int
    {
        $query = $this->pdo->prepare(
            "SELECT COUNT(*)
            FROM contents c
            JOIN categories cat ON cat.id = c.category_id
            WHERE c.status = 'published'
            AND c.category_id = :category_id
            AND cat.is_active = 1"
        );

        $query->execute(['category_id' => $categoryId]);

        return (int)$query->fetchColumn();
    }

    public function searchPublished(
        string $search = '',
        ?int $categoryId = null,
        string $platform = '',
        string $version = '',
        int $limit = 12,
        int $offset = 0,
        bool $explorerOnly = false
    ): array {
        $limit = max(1, min($limit, 100));
        $offset = max(0, $offset);

        $conditions = [
            "c.status = 'published'",
            "cat.is_active = 1"
        ];

        if ($explorerOnly) {
            $conditions[] = "cat.slug IN ('mods', 'maps', 'resource-packs')";
        }

        $params = [];

        if ($search !== '') {
            $conditions[] = 'c.title LIKE :search';
            $params['search'] = '%' . $search . '%';
        }

        if ($categoryId !== null && $categoryId > 0) {
            $conditions[] = 'c.category_id = :category_id';
            $params['category_id'] = $categoryId;
        }

        if (in_array($platform, ['java', 'bedrock'], true)) {
            $conditions[] = 'c.platform IN (:platform, :both)';
            $params['platform'] = $platform;
            $params['both'] = 'both';
        }

        if ($version !== '') {
            $conditions[] = 'JSON_VALID(c.game_versions) = 1';
            $conditions[] = 'JSON_CONTAINS(c.game_versions, :version) = 1';
            $params['version'] = json_encode($version, JSON_THROW_ON_ERROR);
        }

        $where = implode(' AND ', $conditions);

        $query = $this->pdo->prepare(
            "SELECT c.id, c.title, c.slug, c.description, c.image_url, c.platform,
                    c.downloads_count, c.likes_count,
                    cat.name AS category_name, cat.slug AS category_slug
             FROM contents c
             JOIN categories cat ON cat.id = c.category_id
             WHERE {$where}
             ORDER BY c.is_featured DESC, c.created_at DESC
             LIMIT {$limit} OFFSET {$offset}"
        );

        $query->execute($params);

        return $query->fetchAll();
    }

    public function countSearchPublished(
        string $search = '',
        ?int $categoryId = null,
        string $platform = '',
        string $version = '',
        bool $explorerOnly = false
    ): int {
        $conditions = [
            "c.status = 'published'",
            "cat.is_active = 1"
        ];

        if ($explorerOnly) {
            $conditions[] = "cat.slug IN ('mods', 'maps', 'resource-packs')";
        }

        $params = [];

        if ($search !== '') {
            $conditions[] = 'c.title LIKE :search';
            $params['search'] = '%' . $search . '%';
        }

        if ($categoryId !== null && $categoryId > 0) {
            $conditions[] = 'c.category_id = :category_id';
            $params['category_id'] = $categoryId;
        }

        if (in_array($platform, ['java', 'bedrock'], true)) {
            $conditions[] = 'c.platform IN (:platform, :both)';
            $params['platform'] = $platform;
            $params['both'] = 'both';
        }

        if ($version !== '') {
            $conditions[] = 'JSON_VALID(c.game_versions) = 1';
            $conditions[] = 'JSON_CONTAINS(c.game_versions, :version) = 1';
            $params['version'] = json_encode($version, JSON_THROW_ON_ERROR);
        }

        $where = implode(' AND ', $conditions);

        $query = $this->pdo->prepare(
            "SELECT COUNT(*)
             FROM contents c
             JOIN categories cat ON cat.id = c.category_id
             WHERE {$where}"
        );

        $query->execute($params);

        return (int)$query->fetchColumn();
    }

    public function findPublishedDetail(
        int $contentId,
        string $slug,
        string $categorySlug
    ): ?array {
        $query = $this->pdo->prepare(
            "SELECT c.*, cat.name AS category_name, cat.slug AS category_slug
             FROM contents c
             JOIN categories cat ON cat.id = c.category_id
             WHERE c.id = :id
               AND c.slug = :slug
               AND cat.slug = :category
               AND c.status = 'published'
               AND cat.is_active = 1
             LIMIT 1"
        );

        $query->execute([
            'id' => $contentId,
            'slug' => $slug,
            'category' => $categorySlug,
        ]);

        return $query->fetch() ?: null;
    }

    public function findPublishedDownloadUrl(int $contentId): ?string
    {
        $query = $this->pdo->prepare(
            "SELECT c.download_url
             FROM contents c
             JOIN categories cat ON cat.id = c.category_id
             WHERE c.id = :id
               AND c.status = 'published'
               AND cat.is_active = 1
             LIMIT 1"
        );

        $query->execute(['id' => $contentId]);
        $url = $query->fetchColumn();

        return is_string($url) && $url !== '' ? $url : null;
    }

    public function incrementDownloads(int $contentId): bool
    {
        $query = $this->pdo->prepare(
            "UPDATE contents
             SET downloads_count = downloads_count + 1
             WHERE id = :id
               AND status = 'published'
               AND EXISTS (
                   SELECT 1
                   FROM categories cat
                   WHERE cat.id = contents.category_id
                     AND cat.is_active = 1
               )"
        );

        $query->execute(['id' => $contentId]);

        return $query->rowCount() === 1;
    }

    public function findPublishedByAuthor(int $authorId, int $limit = 100): array
    {
        $limit = max(1, min($limit, 100));

        $query = $this->pdo->prepare(
            "SELECT c.id, c.title, c.slug, c.description, c.image_url, c.platform,
                    c.downloads_count, c.likes_count,
                    cat.name AS category_name,
                    cat.slug AS category_slug
            FROM contents c
            JOIN categories cat ON cat.id = c.category_id
            WHERE c.author_id = :author_id
            AND c.status = 'published'
            AND cat.is_active = 1
            ORDER BY c.created_at DESC
            LIMIT {$limit}"
        );

        $query->execute(['author_id' => $authorId]);

        return $query->fetchAll();
    }

    public function findAdminList(int $limit = 100): array
    {
        $limit = max(1, min($limit, 100));

        $query = $this->pdo->query(
            "SELECT c.id, c.title, c.slug, c.status, c.updated_at,
                    cat.name AS category_name
             FROM contents c
             JOIN categories cat ON cat.id = c.category_id
             ORDER BY c.updated_at DESC, c.id DESC
             LIMIT {$limit}"
        );

        return $query->fetchAll();
    }

    public function findById(int $contentId): ?array
    {
        $query = $this->pdo->prepare(
            'SELECT * FROM contents WHERE id = :id LIMIT 1'
        );

        $query->execute(['id' => $contentId]);

        return $query->fetch() ?: null;
    }
}
