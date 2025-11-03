<?php

declare(strict_types=1);

namespace Qd\SchemaBundle\Service\NlToSql;

/**
 * Finds paths between entities in a schema graph using BFS algorithm.
 *
 * This is the PHP equivalent of frontend/src/utils/pathFinder.js
 * Used to determine which JOINs are needed to connect entities in a query.
 */
final class PathFinder
{
    /**
     * Find all paths between source and target entities.
     *
     * @param array<string, mixed> $sourceEntity
     * @param array<string, mixed> $targetEntity
     * @param array<int, array<string, mixed>> $allEntities
     * @param int $maxDepth Maximum path length
     * @return array<array{
     *     path: array<array<string, mixed>>,
     *     length: int,
     *     relations: array<array<string, mixed>>,
     *     entities: array<array<string, mixed>>
     * }>
     */
    public function findPaths(
        array $sourceEntity,
        array $targetEntity,
        array $allEntities,
        int $maxDepth = 5
    ): array {
        $sourceId = $sourceEntity['fqcn'] ?? $sourceEntity['name'] ?? '';
        $targetId = $targetEntity['fqcn'] ?? $targetEntity['name'] ?? '';

        if (empty($sourceId) || empty($targetId)) {
            return [];
        }

        // Same entity
        if ($sourceId === $targetId) {
            return [[
                'path' => [$sourceEntity],
                'length' => 0,
                'relations' => [],
                'entities' => [$sourceEntity],
            ]];
        }

        // Build entity maps
        $entityMapByFqcn = [];
        $entityMapByName = [];
        foreach ($allEntities as $entity) {
            $fqcn = $entity['fqcn'] ?? $entity['name'] ?? '';
            $name = $entity['name'] ?? '';

            if (!empty($fqcn)) {
                $entityMapByFqcn[$fqcn] = $entity;
            }
            if (!empty($name)) {
                $entityMapByName[$name] = $entity;
            }
        }

        // Build adjacency map
        $adjacencyMap = $this->buildAdjacencyMap($allEntities, $entityMapByName);

        // BFS to find all paths
        $paths = [];
        $queue = [[
            'currentId' => $sourceId,
            'path' => [$sourceId],
            'relations' => [],
            'visited' => [$sourceId => true],
        ]];

        while (!empty($queue)) {
            $current = array_shift($queue);
            $currentId = $current['currentId'];
            $path = $current['path'];
            $relations = $current['relations'];
            $visited = $current['visited'];

            // Stop if max depth reached
            if (count($path) > $maxDepth) {
                continue;
            }

            // Get neighbors
            $neighbors = $adjacencyMap[$currentId] ?? [];

            foreach ($neighbors as $neighbor) {
                $neighborId = $neighbor['targetId'];
                $relation = $neighbor['relation'];

                // Skip if already visited
                if (isset($visited[$neighborId])) {
                    continue;
                }

                $newPath = array_merge($path, [$neighborId]);
                $newRelations = array_merge($relations, [$relation]);
                $newVisited = array_merge($visited, [$neighborId => true]);

                // Found target!
                if ($neighborId === $targetId) {
                    $paths[] = [
                        'path' => array_map(fn($id) => $entityMapByFqcn[$id] ?? $entityMapByName[$id] ?? null, $newPath),
                        'length' => count($newPath) - 1,
                        'relations' => $newRelations,
                        'entities' => array_map(fn($id) => $entityMapByFqcn[$id] ?? $entityMapByName[$id] ?? null, $newPath),
                    ];
                    continue;
                }

                // Continue searching
                $queue[] = [
                    'currentId' => $neighborId,
                    'path' => $newPath,
                    'relations' => $newRelations,
                    'visited' => $newVisited,
                ];
            }
        }

        // Sort by path length (shortest first)
        usort($paths, fn($a, $b) => $a['length'] <=> $b['length']);

        return $paths;
    }

    /**
     * Build adjacency map for efficient graph traversal.
     *
     * @param array<int, array<string, mixed>> $entities
     * @param array<string, array<string, mixed>> $entityMapByName
     * @return array<string, array<array{targetId: string, relation: array<string, mixed>}>>
     */
    private function buildAdjacencyMap(array $entities, array $entityMapByName): array
    {
        $adjacencyMap = [];

        foreach ($entities as $entity) {
            $entityId = $entity['fqcn'] ?? $entity['name'] ?? '';

            if (empty($entityId)) {
                continue;
            }

            if (!isset($adjacencyMap[$entityId])) {
                $adjacencyMap[$entityId] = [];
            }

            $relations = $entity['associations'] ?? $entity['relations'] ?? [];

            foreach ($relations as $relation) {
                $targetName = $relation['target'] ?? '';
                if (empty($targetName)) {
                    continue;
                }

                // Resolve target name to FQCN
                $targetEntity = $entityMapByName[$targetName] ?? null;
                $targetId = $targetEntity ? ($targetEntity['fqcn'] ?? $targetEntity['name'] ?? '') : $targetName;

                $adjacencyMap[$entityId][] = [
                    'targetId' => $targetId,
                    'relation' => [
                        'field' => $relation['field'] ?? '',
                        'type' => $relation['type'] ?? '',
                        'isOwning' => $relation['isOwning'] ?? false,
                        'mappedBy' => $relation['mappedBy'] ?? null,
                        'inversedBy' => $relation['inversedBy'] ?? null,
                        'from' => $entity['name'] ?? '',
                        'to' => $targetName,
                    ],
                ];
            }
        }

        return $adjacencyMap;
    }

    /**
     * Get all entities involved in paths.
     *
     * @param array<array<string, mixed>> $paths
     * @return array<array<string, mixed>>
     */
    public function getEntitiesFromPaths(array $paths): array
    {
        $entitySet = [];
        $seen = [];

        foreach ($paths as $path) {
            foreach ($path['entities'] ?? [] as $entity) {
                $id = $entity['fqcn'] ?? $entity['name'] ?? '';
                if (!empty($id) && !isset($seen[$id])) {
                    $entitySet[] = $entity;
                    $seen[$id] = true;
                }
            }
        }

        return array_values($entitySet);
    }

    /**
     * Get all relations involved in paths.
     *
     * @param array<array<string, mixed>> $paths
     * @return array<array<string, mixed>>
     */
    public function getRelationsFromPaths(array $paths): array
    {
        $relations = [];
        $seenPairs = [];

        foreach ($paths as $path) {
            foreach ($path['relations'] ?? [] as $relation) {
                $from = $relation['from'] ?? '';
                $to = $relation['to'] ?? '';

                if (empty($from) || empty($to)) {
                    continue;
                }

                // Create unique key for this relation pair
                $pairKey = implode('|', [$from < $to ? $from : $to, $from < $to ? $to : $from]);

                if (!isset($seenPairs[$pairKey])) {
                    $relations[] = $relation;
                    $seenPairs[$pairKey] = true;
                }
            }
        }

        return array_values($relations);
    }

    /**
     * Format a path for display.
     *
     * @param array<string, mixed> $path
     */
    public function formatPath(array $path): string
    {
        $entities = $path['entities'] ?? [];
        if (empty($entities)) {
            return '';
        }

        $names = array_map(fn($e) => $e['name'] ?? '', $entities);
        return implode(' → ', $names);
    }
}
