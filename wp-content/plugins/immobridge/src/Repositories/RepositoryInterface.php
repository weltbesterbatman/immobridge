<?php
/**
 * Repository Interface
 *
 * @package ImmoBridge
 * @subpackage Repositories
 * @since 1.0.0
 */

declare(strict_types=1);

namespace ImmoBridge\Repositories;

/**
 * Repository Interface
 *
 * Defines the contract for all repository implementations.
 *
 * @template T
 * @since 1.0.0
 */
interface RepositoryInterface
{
    /**
     * Find entity by ID
     *
     * @param int $id Entity ID
     * @return T|null
     */
    public function findById(int $id): mixed;

    /**
     * Find all entities
     *
     * @param array<string, mixed> $criteria Search criteria
     * @param array<string, string> $orderBy Order by criteria
     * @param int|null $limit Limit results
     * @param int|null $offset Offset results
     * @return array<T>
     */
    public function findAll(array $criteria = [], array $orderBy = [], ?int $limit = null, ?int $offset = null): array;

    /**
     * Find one entity by criteria
     *
     * @param array<string, mixed> $criteria Search criteria
     * @return T|null
     */
    public function findOneBy(array $criteria): mixed;

    /**
     * Find entities by criteria
     *
     * @param array<string, mixed> $criteria Search criteria
     * @param array<string, string> $orderBy Order by criteria
     * @param int|null $limit Limit results
     * @param int|null $offset Offset results
     * @return array<T>
     */
    public function findBy(array $criteria, array $orderBy = [], ?int $limit = null, ?int $offset = null): array;

    /**
     * Save entity
     *
     * @param T $entity Entity to save
     * @return T Saved entity
     */
    public function save(mixed $entity): mixed;

    /**
     * Delete entity
     *
     * @param T $entity Entity to delete
     * @return bool Success status
     */
    public function delete(mixed $entity): bool;

    /**
     * Delete entity by ID
     *
     * @param int $id Entity ID
     * @return bool Success status
     */
    public function deleteById(int $id): bool;

    /**
     * Count entities
     *
     * @param array<string, mixed> $criteria Search criteria
     * @return int
     */
    public function count(array $criteria = []): int;

    /**
     * Check if entity exists
     *
     * @param int $id Entity ID
     * @return bool
     */
    public function exists(int $id): bool;
}
