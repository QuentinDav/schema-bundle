<?php

namespace Qd\SchemaBundle\Controller;

use Doctrine\ORM\EntityManagerInterface;
use Qd\SchemaBundle\Service\MigrationAnalyzer;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

class MigrationHistoryController extends AbstractController
{
    public function __construct(
        private MigrationAnalyzer $migrationAnalyzer,
        private EntityManagerInterface $em
    ) {
    }

    /**
     * Get migration history for a specific entity.
     */
    public function getEntityHistory(string $fqcn, Request $request): JsonResponse
    {
        try {
            $entityFqcn = $this->decodeFqcn($fqcn);

            $tableName = null;
            try {
                $metadata = $this->em->getClassMetadata($entityFqcn);
                $tableName = $metadata->getTableName();
            } catch (\Exception $e) {
            }

            $history = $this->migrationAnalyzer->getEntityHistory($entityFqcn, $tableName);

            return new JsonResponse([
                'ok' => true,
                'entity' => $entityFqcn,
                'tableName' => $tableName,
                'history' => $history,
                'count' => count($history),
            ]);
        } catch (\Exception $e) {
            return new JsonResponse([
                'ok' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get migration history for all entities.
     */
    public function getAllHistory(Request $request): JsonResponse
    {
        try {
            $history = $this->migrationAnalyzer->getAllEntitiesHistory();

            $enrichedHistory = [];
            $allMetadata = $this->em->getMetadataFactory()->getAllMetadata();

            $tableToFqcn = [];
            foreach ($allMetadata as $metadata) {
                $tableToFqcn[$metadata->getTableName()] = $metadata->getName();
            }

            foreach ($history as $tableName => $timeline) {
                $enrichedHistory[] = [
                    'tableName' => $tableName,
                    'entityFqcn' => $tableToFqcn[$tableName] ?? null,
                    'timeline' => $timeline,
                    'changeCount' => count($timeline),
                ];
            }

            return new JsonResponse([
                'ok' => true,
                'entities' => $enrichedHistory,
                'totalEntities' => count($enrichedHistory),
            ]);
        } catch (\Exception $e) {
            return new JsonResponse([
                'ok' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get summary statistics about migrations.
     */
    public function getStats(): JsonResponse
    {
        try {
            $allHistory = $this->migrationAnalyzer->getAllEntitiesHistory();

            $stats = [
                'totalEntities' => count($allHistory),
                'totalMigrations' => 0,
                'totalChanges' => 0,
                'entitiesByMigrationCount' => [],
                'changeTypes' => [],
                'mostActiveEntities' => [],
            ];

            $migrationsSet = [];

            foreach ($allHistory as $tableName => $timeline) {
                $changeCount = count($timeline);
                $stats['totalChanges'] += $changeCount;

                foreach ($timeline as $entry) {
                    $migrationsSet[$entry['migration']] = true;

                    foreach ($entry['changes'] as $change) {
                        $type = $change['type'];
                        $stats['changeTypes'][$type] = ($stats['changeTypes'][$type] ?? 0) + 1;
                    }
                }

                $stats['entitiesByMigrationCount'][] = [
                    'table' => $tableName,
                    'migrations' => $changeCount,
                ];
            }

            $stats['totalMigrations'] = count($migrationsSet);

            usort($stats['entitiesByMigrationCount'], fn($a, $b) => $b['migrations'] <=> $a['migrations']);
            $stats['mostActiveEntities'] = array_slice($stats['entitiesByMigrationCount'], 0, 10);

            return new JsonResponse([
                'ok' => true,
                'stats' => $stats,
            ]);
        } catch (\Exception $e) {
            return new JsonResponse([
                'ok' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get list of all migrations with basic info.
     */
    public function listMigrations(): JsonResponse
    {
        try {
            $allHistory = $this->migrationAnalyzer->getAllEntitiesHistory();
            $migrations = [];
            $seen = [];

            foreach ($allHistory as $timeline) {
                foreach ($timeline as $entry) {
                    $version = $entry['version'];

                    if (!isset($seen[$version])) {
                        $migrations[] = [
                            'version' => $version,
                            'migration' => $entry['migration'],
                            'timestamp' => $entry['timestamp'],
                            'description' => $entry['description'],
                            'date' => date('Y-m-d H:i:s', $entry['timestamp']),
                        ];
                        $seen[$version] = true;
                    }
                }
            }

            usort($migrations, fn($a, $b) => $b['timestamp'] <=> $a['timestamp']);

            return new JsonResponse([
                'ok' => true,
                'migrations' => $migrations,
                'count' => count($migrations),
            ]);
        } catch (\Exception $e) {
            return new JsonResponse([
                'ok' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Decode FQCN from URL parameter.
     */
    private function decodeFqcn(string $encoded): string
    {
        $decoded = base64_decode($encoded, true);
        if ($decoded !== false && $this->isValidFqcn($decoded)) {
            return $decoded;
        }

        $decoded = urldecode($encoded);
        if ($this->isValidFqcn($decoded)) {
            return $decoded;
        }

        if ($this->isValidFqcn($encoded)) {
            return $encoded;
        }

        throw new \InvalidArgumentException("Invalid FQCN format: $encoded");
    }

    /**
     * Check if string looks like a valid FQCN.
     */
    private function isValidFqcn(string $fqcn): bool
    {
        return (bool) preg_match('/^[a-zA-Z_][a-zA-Z0-9_\\\\]*$/', $fqcn);
    }
}
