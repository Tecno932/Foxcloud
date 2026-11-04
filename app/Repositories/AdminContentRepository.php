<?php

declare(strict_types=1);

namespace FoxCraft\Repositories;

use PDO;
use RuntimeException;
use Throwable;

final class AdminContentRepository
{
    public function __construct(
        private readonly PDO $pdo
    ) {
    }

    public function save(
        int $contentId,
        int $categoryId,
        int $authorId,
        string $title,
        string $slug,
        string $description,
        ?string $imageUrl,
        ?string $downloadUrl,
        string $contentType,
        string $platform,
        string $gameVersions,
        int $featured,
        string $status
    ): int {
        try {
            $this->pdo->beginTransaction();

            if ($contentId > 0) {
                $save = $this->pdo->prepare(
                    'UPDATE contents
                     SET category_id = :category_id, title = :title, slug = :slug,
                         description = :description, image_url = :image_url,
                         download_url = :download_url, content_type = :content_type,
                         platform = :platform, game_versions = :game_versions,
                         is_featured = :is_featured, status = :status
                     WHERE id = :id'
                );

                $save->execute([
                    'category_id' => $categoryId,
                    'title' => $title,
                    'slug' => $slug,
                    'description' => $description,
                    'image_url' => $imageUrl,
                    'download_url' => $downloadUrl,
                    'content_type' => $contentType,
                    'platform' => $platform,
                    'game_versions' => $gameVersions,
                    'is_featured' => $featured,
                    'status' => $status,
                    'id' => $contentId,
                ]);

                if ($save->rowCount() === 0) {
                    $exists = $this->pdo->prepare(
                        'SELECT id FROM contents WHERE id = :id'
                    );
                    $exists->execute(['id' => $contentId]);

                    if (!$exists->fetchColumn()) {
                        throw new RuntimeException(
                            'La publicación que intentás editar no existe.'
                        );
                    }
                }

                $auditAction = 'content.updated';
                $auditId = $contentId;
            } else {
                $save = $this->pdo->prepare(
                    'INSERT INTO contents
                     (category_id, author_id, title, slug, description, image_url,
                      download_url, content_type, platform, game_versions,
                      is_featured, status)
                     VALUES
                     (:category_id, :author_id, :title, :slug, :description, :image_url,
                      :download_url, :content_type, :platform, :game_versions,
                      :is_featured, :status)'
                );

                $save->execute([
                    'category_id' => $categoryId,
                    'author_id' => $authorId,
                    'title' => $title,
                    'slug' => $slug,
                    'description' => $description,
                    'image_url' => $imageUrl,
                    'download_url' => $downloadUrl,
                    'content_type' => $contentType,
                    'platform' => $platform,
                    'game_versions' => $gameVersions,
                    'is_featured' => $featured,
                    'status' => $status,
                ]);

                $auditAction = 'content.created';
                $auditId = (int) $this->pdo->lastInsertId();
            }

            $audit = $this->pdo->prepare(
                'INSERT INTO admin_audit_log
                 (actor_user_id, action, entity_type, entity_id, details)
                 VALUES (:actor, :action, :type, :entity, :details)'
            );

            $audit->execute([
                'actor' => $authorId,
                'action' => $auditAction,
                'type' => 'content',
                'entity' => $auditId,
                'details' => json_encode([
                    'title' => $title,
                    'status' => $status,
                ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
            ]);

            $this->pdo->commit();

            return $auditId;
        } catch (Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            throw $exception;
        }
    }
}
