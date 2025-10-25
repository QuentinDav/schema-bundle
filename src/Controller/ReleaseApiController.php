<?php

namespace Qd\SchemaBundle\Controller;

use Doctrine\ORM\EntityManagerInterface;
use Qd\SchemaBundle\Entity\Release;
use Qd\SchemaBundle\Entity\Snapshot;
use Qd\SchemaBundle\Service\SnapshotService;
use Qd\SchemaBundle\Service\SchemaDiff;
use Qd\SchemaBundle\Service\VersioningService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class ReleaseApiController
{
    public function __construct(
        private EntityManagerInterface $em,
        private SnapshotService $snapshotService,
        private SchemaDiff $schemaDiff,
        private VersioningService $versioningService
    ) {
    }

    /**
     * GET /schema-doc/api/releases
     * List all releases with summary statistics
     */
    public function list(): JsonResponse
    {
        $repo = $this->em->getRepository(Release::class);
        $releases = $repo->createQueryBuilder('r')
            ->orderBy('r.createdAt', 'DESC')
            ->getQuery()
            ->getResult();

        $data = array_map(function (Release $release) {
            $summary = $release->getSummary();
            return [
                'id' => $release->getId(),
                'name' => $release->getName(),
                'description' => $release->getDescription(),
                'created_at' => $release->getCreatedAt()->format('Y-m-d H:i:s'),
                'created_by' => $release->getCreatedBy(),
                'total_entities' => $summary['total_entities'],
                'changed_entities' => $summary['changed_entities'],
                'added_entities' => $summary['added_entities'],
            ];
        }, $releases);

        return new JsonResponse(['releases' => $data]);
    }

    /**
     * GET /schema-doc/api/releases/suggested-version
     * Get suggested next version based on pending changes
     */
    public function suggestedVersion(): JsonResponse
    {
        // We need to peek at what changes would be made
        // For now, return next minor version as default
        // In a real scenario, we'd analyze pending changes

        $nextVersion = $this->versioningService->getNextVersion('minor');

        return new JsonResponse([
            'version' => $nextVersion,
            'type' => 'minor',
            'reason' => 'Default suggestion (changes not yet analyzed)',
            'available_types' => [
                'major' => $this->versioningService->getNextVersion('major'),
                'minor' => $this->versioningService->getNextVersion('minor'),
                'patch' => $this->versioningService->getNextVersion('patch'),
            ]
        ]);
    }

    /**
     * POST /schema-doc/api/releases
     * Create a new release with automatic versioning
     */
    public function create(Request $request): JsonResponse
    {
        $payload = json_decode($request->getContent(), true);
        $versionType = $payload['version_type'] ?? 'auto'; // 'auto', 'major', 'minor', 'patch'
        $description = $payload['description'] ?? null;

        try {
            // First, create the release to get the summary
            $name = $this->versioningService->getNextVersion(
                $versionType === 'auto' ? 'minor' : $versionType
            );

            // Check if version already exists
            $existingRelease = $this->em->getRepository(Release::class)->findOneBy(['name' => $name]);
            if ($existingRelease) {
                return new JsonResponse([
                    'ok' => false,
                    'message' => "Release version {$name} already exists"
                ], 409);
            }

            $result = $this->snapshotService->createRelease($name, $description);

            // If auto-detection was requested and we have the summary,
            // we could potentially recreate with the correct version
            // but for simplicity, we'll keep the current approach

            return new JsonResponse($result);
        } catch (\Throwable $e) {
            return new JsonResponse([
                'ok' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * GET /schema-doc/api/releases/{id}
     * Get detailed information about a release including all snapshots
     */
    public function get(int $id): JsonResponse
    {
        $release = $this->em->getRepository(Release::class)->find($id);

        if (!$release) {
            return new JsonResponse(['message' => 'Release not found'], 404);
        }

        $snapshots = [];
        $changedEntities = [];
        $addedEntities = [];
        $unchangedEntities = [];

        foreach ($release->getSnapshots() as $snapshot) {
            $entityName = substr($snapshot->getEntityFqcn(), strrpos($snapshot->getEntityFqcn(), '\\') + 1);

            $snapData = [
                'id' => $snapshot->getId(),
                'entity_fqcn' => $snapshot->getEntityFqcn(),
                'entity_name' => $entityName,
                'schema_hash' => $snapshot->getSchemaHash(),
                'diff' => $snapshot->getDiffJson(),
                'created_at' => $snapshot->getCreatedAt()->format('Y-m-d H:i:s'),
            ];

            $snapshots[] = $snapData;

            // Categorize entities
            if ($snapshot->getDiffJson() === null) {
                $addedEntities[] = $snapData;
            } elseif ($this->isDiffEmpty($snapshot->getDiffJson())) {
                $unchangedEntities[] = $snapData;
            } else {
                $changedEntities[] = $snapData;
            }
        }

        $summary = $release->getSummary();

        return new JsonResponse([
            'id' => $release->getId(),
            'name' => $release->getName(),
            'description' => $release->getDescription(),
            'created_at' => $release->getCreatedAt()->format('Y-m-d H:i:s'),
            'created_by' => $release->getCreatedBy(),
            'summary' => $summary,
            'snapshots' => $snapshots,
            'changed_entities' => $changedEntities,
            'added_entities' => $addedEntities,
            'unchanged_entities' => $unchangedEntities,
        ]);
    }

    /**
     * GET /schema-doc/api/releases/compare/{id1}/{id2}
     * Compare two releases
     */
    public function compare(int $id1, int $id2): JsonResponse
    {
        $release1 = $this->em->getRepository(Release::class)->find($id1);
        $release2 = $this->em->getRepository(Release::class)->find($id2);

        if (!$release1 || !$release2) {
            return new JsonResponse(['message' => 'One or both releases not found'], 404);
        }

        // Group snapshots by entity FQCN
        $snapshots1 = [];
        foreach ($release1->getSnapshots() as $snap) {
            $snapshots1[$snap->getEntityFqcn()] = $snap;
        }

        $snapshots2 = [];
        foreach ($release2->getSnapshots() as $snap) {
            $snapshots2[$snap->getEntityFqcn()] = $snap;
        }

        $allFqcns = array_unique(array_merge(array_keys($snapshots1), array_keys($snapshots2)));

        $addedEntities = [];
        $removedEntities = [];
        $modifiedEntities = [];
        $unchangedEntities = [];

        foreach ($allFqcns as $fqcn) {
            $entityName = substr($fqcn, strrpos($fqcn, '\\') + 1);

            $snap1 = $snapshots1[$fqcn] ?? null;
            $snap2 = $snapshots2[$fqcn] ?? null;

            if (!$snap1 && $snap2) {
                // Entity added in release 2
                $addedEntities[] = [
                    'entity_fqcn' => $fqcn,
                    'entity_name' => $entityName,
                    'schema' => $snap2->getSchemaJson(),
                ];
            } elseif ($snap1 && !$snap2) {
                // Entity removed in release 2
                $removedEntities[] = [
                    'entity_fqcn' => $fqcn,
                    'entity_name' => $entityName,
                ];
            } elseif ($snap1 && $snap2) {
                // Entity exists in both, check if modified
                if ($snap1->getSchemaHash() === $snap2->getSchemaHash()) {
                    $unchangedEntities[] = [
                        'entity_fqcn' => $fqcn,
                        'entity_name' => $entityName,
                    ];
                } else {
                    // Calculate diff between the two snapshots
                    $diff = $this->schemaDiff->diff($snap1->getSchemaJson(), $snap2->getSchemaJson());

                    $modifiedEntities[] = [
                        'entity_fqcn' => $fqcn,
                        'entity_name' => $entityName,
                        'diff' => $diff,
                        'old_schema' => $snap1->getSchemaJson(),
                        'new_schema' => $snap2->getSchemaJson(),
                    ];
                }
            }
        }

        return new JsonResponse([
            'release1' => [
                'id' => $release1->getId(),
                'name' => $release1->getName(),
                'created_at' => $release1->getCreatedAt()->format('Y-m-d H:i:s'),
            ],
            'release2' => [
                'id' => $release2->getId(),
                'name' => $release2->getName(),
                'created_at' => $release2->getCreatedAt()->format('Y-m-d H:i:s'),
            ],
            'added_entities' => $addedEntities,
            'removed_entities' => $removedEntities,
            'modified_entities' => $modifiedEntities,
            'unchanged_entities' => $unchangedEntities,
            'summary' => [
                'total_changes' => count($addedEntities) + count($removedEntities) + count($modifiedEntities),
                'added' => count($addedEntities),
                'removed' => count($removedEntities),
                'modified' => count($modifiedEntities),
                'unchanged' => count($unchangedEntities),
            ],
        ]);
    }

    /**
     * GET /schema-doc/api/snapshots/entity/{fqcn}
     * Get full history of a specific entity across all releases
     */
    public function entityHistory(string $fqcn): JsonResponse
    {
        // Decode the FQCN (URL encoded)
        $fqcn = urldecode($fqcn);

        $snapshots = $this->em->getRepository(Snapshot::class)
            ->createQueryBuilder('s')
            ->where('s.entityFqcn = :fqcn')
            ->orderBy('s.createdAt', 'DESC')
            ->setParameter('fqcn', $fqcn)
            ->getQuery()
            ->getResult();

        $history = array_map(function (Snapshot $snapshot) {
            $release = $snapshot->getRelease();
            return [
                'id' => $snapshot->getId(),
                'schema_hash' => $snapshot->getSchemaHash(),
                'diff' => $snapshot->getDiffJson(),
                'created_at' => $snapshot->getCreatedAt()->format('Y-m-d H:i:s'),
                'created_by' => $snapshot->getCreatedBy(),
                'tag' => $snapshot->getTag(),
                'release' => $release ? [
                    'id' => $release->getId(),
                    'name' => $release->getName(),
                ] : null,
            ];
        }, $snapshots);

        return new JsonResponse([
            'entity_fqcn' => $fqcn,
            'entity_name' => substr($fqcn, strrpos($fqcn, '\\') + 1),
            'history' => $history,
        ]);
    }

    /**
     * GET /schema-doc/api/releases/{id}/export/markdown
     * Export release notes as Markdown
     */
    public function exportMarkdown(int $id): Response
    {
        $release = $this->em->getRepository(Release::class)->find($id);

        if (!$release) {
            return new JsonResponse(['message' => 'Release not found'], 404);
        }

        $markdown = $this->generateMarkdown($release);

        $response = new Response($markdown);
        $response->headers->set('Content-Type', 'text/markdown; charset=UTF-8');
        $response->headers->set('Content-Disposition', sprintf(
            'attachment; filename="release-%s.md"',
            preg_replace('/[^a-zA-Z0-9_-]/', '_', $release->getName())
        ));

        return $response;
    }

    private function generateMarkdown(Release $release): string
    {
        $md = [];
        $summary = $release->getSummary();

        // Header
        $md[] = "# {$release->getName()}";
        $md[] = "";
        if ($release->getDescription()) {
            $md[] = $release->getDescription();
            $md[] = "";
        }
        $md[] = "*Created: {$release->getCreatedAt()->format('Y-m-d H:i:s')}*";
        if ($release->getCreatedBy()) {
            $md[] = "*By: {$release->getCreatedBy()}*";
        }
        $md[] = "";

        // Summary
        $md[] = "## 📊 Summary";
        $md[] = "";
        $md[] = "- **Total Entities:** {$summary['total_entities']}";
        $md[] = "- **Modified:** {$summary['changed_entities']}";
        $md[] = "- **Added:** {$summary['added_entities']}";
        $md[] = "";

        // Categorize entities
        $addedEntities = [];
        $changedEntities = [];
        $unchangedEntities = [];

        foreach ($release->getSnapshots() as $snapshot) {
            $entityName = substr($snapshot->getEntityFqcn(), strrpos($snapshot->getEntityFqcn(), '\\') + 1);
            $data = [
                'name' => $entityName,
                'fqcn' => $snapshot->getEntityFqcn(),
                'diff' => $snapshot->getDiffJson(),
                'schema' => $snapshot->getSchemaJson(),
            ];

            if ($snapshot->getDiffJson() === null) {
                $addedEntities[] = $data;
            } elseif ($this->isDiffEmpty($snapshot->getDiffJson())) {
                $unchangedEntities[] = $data;
            } else {
                $changedEntities[] = $data;
            }
        }

        // Added Entities
        if (!empty($addedEntities)) {
            $md[] = "## ✨ New Entities";
            $md[] = "";
            foreach ($addedEntities as $entity) {
                $md[] = "### `{$entity['fqcn']}`";
                $md[] = "";
                $schema = $entity['schema'];

                // Fields
                if (!empty($schema['fields'])) {
                    $md[] = "**Fields:**";
                    foreach ($schema['fields'] as $name => $spec) {
                        $type = $spec['type'] ?? 'unknown';
                        $nullable = ($spec['nullable'] ?? false) ? ', nullable' : ', not-null';
                        $unique = ($spec['unique'] ?? false) ? ', unique' : '';
                        $length = isset($spec['length']) ? ", len={$spec['length']}" : '';
                        $md[] = "- `{$name}` ({$type}{$length}{$nullable}{$unique})";
                    }
                    $md[] = "";
                }

                // Relations
                if (!empty($schema['rels'])) {
                    $md[] = "**Relations:**";
                    foreach ($schema['rels'] as $field => $rel) {
                        $typeMap = [1 => 'OneToOne', 2 => 'ManyToOne', 4 => 'OneToMany', 8 => 'ManyToMany'];
                        $type = $typeMap[$rel['type']] ?? "type={$rel['type']}";
                        $owning = ($rel['owning'] ?? false) ? ', owning' : '';
                        $nullable = ($rel['nullable'] ?? false) ? ', nullable' : '';
                        $md[] = "- `{$field}` ({$type} → {$rel['target']}{$owning}{$nullable})";
                    }
                    $md[] = "";
                }
            }
        }

        // Modified Entities
        if (!empty($changedEntities)) {
            $md[] = "## 🔧 Modified Entities";
            $md[] = "";
            foreach ($changedEntities as $entity) {
                $md[] = "### `{$entity['fqcn']}`";
                $md[] = "";
                $diff = $entity['diff'];

                // Fields Added
                if (!empty($diff['fields_added'])) {
                    $md[] = "**Fields Added:**";
                    foreach ($diff['fields_added'] as $field) {
                        $type = $field['type'] ?? 'unknown';
                        $nullable = ($field['nullable'] ?? false) ? ', nullable' : ', not-null';
                        $unique = ($field['unique'] ?? false) ? ', unique' : '';
                        $length = isset($field['length']) ? ", len={$field['length']}" : '';
                        $md[] = "- ✅ `{$field['name']}` ({$type}{$length}{$nullable}{$unique})";
                    }
                    $md[] = "";
                }

                // Fields Removed
                if (!empty($diff['fields_removed'])) {
                    $md[] = "**Fields Removed:**";
                    foreach ($diff['fields_removed'] as $field) {
                        $md[] = "- ❌ `{$field['name']}`";
                    }
                    $md[] = "";
                }

                // Fields Changed
                if (!empty($diff['fields_changed'])) {
                    $md[] = "**Fields Modified:**";
                    foreach ($diff['fields_changed'] as $change) {
                        $md[] = "- 🔄 `{$change['name']}`";
                        $from = $change['from'];
                        $to = $change['to'];

                        if (($from['type'] ?? null) !== ($to['type'] ?? null)) {
                            $fromType = $from['type'] ?? 'unknown';
                            $toType = $to['type'] ?? 'unknown';
                            $md[] = "  - type: `{$fromType}` → `{$toType}`";
                        }
                        if (($from['length'] ?? null) !== ($to['length'] ?? null)) {
                            $fromLen = $from['length'] ?? 'none';
                            $toLen = $to['length'] ?? 'none';
                            $md[] = "  - length: `{$fromLen}` → `{$toLen}`";
                        }
                        if (($from['nullable'] ?? null) !== ($to['nullable'] ?? null)) {
                            $fromNull = ($from['nullable'] ?? false) ? 'yes' : 'no';
                            $toNull = ($to['nullable'] ?? false) ? 'yes' : 'no';
                            $md[] = "  - nullable: `{$fromNull}` → `{$toNull}`";
                        }
                        if (($from['unique'] ?? null) !== ($to['unique'] ?? null)) {
                            $fromUniq = ($from['unique'] ?? false) ? 'yes' : 'no';
                            $toUniq = ($to['unique'] ?? false) ? 'yes' : 'no';
                            $md[] = "  - unique: `{$fromUniq}` → `{$toUniq}`";
                        }
                    }
                    $md[] = "";
                }

                // Relations Added
                if (!empty($diff['rels_added'])) {
                    $md[] = "**Relations Added:**";
                    foreach ($diff['rels_added'] as $rel) {
                        $typeMap = [1 => 'OneToOne', 2 => 'ManyToOne', 4 => 'OneToMany', 8 => 'ManyToMany'];
                        $relType = $rel['type'] ?? 0;
                        $type = $typeMap[$relType] ?? "type={$relType}";
                        $target = $rel['target'] ?? 'Unknown';
                        $owning = ($rel['owning'] ?? false) ? ', owning' : '';
                        $nullable = ($rel['nullable'] ?? false) ? ', nullable' : '';
                        $md[] = "- ✅ `{$rel['field']}` ({$type} → {$target}{$owning}{$nullable})";
                    }
                    $md[] = "";
                }

                // Relations Removed
                if (!empty($diff['rels_removed'])) {
                    $md[] = "**Relations Removed:**";
                    foreach ($diff['rels_removed'] as $rel) {
                        $md[] = "- ❌ `{$rel['field']}`";
                    }
                    $md[] = "";
                }

                // Relations Changed
                if (!empty($diff['rels_changed'])) {
                    $md[] = "**Relations Modified:**";
                    foreach ($diff['rels_changed'] as $change) {
                        $field = $change['field'] ?? 'unknown';
                        $target = $change['to']['target'] ?? ($change['from']['target'] ?? 'Unknown');
                        $md[] = "- 🔄 `{$field}` → {$target}";
                        $from = $change['from'] ?? [];
                        $to = $change['to'] ?? [];

                        $typeMap = [1 => 'OneToOne', 2 => 'ManyToOne', 4 => 'OneToMany', 8 => 'ManyToMany'];
                        if (($from['type'] ?? null) !== ($to['type'] ?? null)) {
                            $fromTypeInt = $from['type'] ?? 0;
                            $toTypeInt = $to['type'] ?? 0;
                            $fromType = $typeMap[$fromTypeInt] ?? "type={$fromTypeInt}";
                            $toType = $typeMap[$toTypeInt] ?? "type={$toTypeInt}";
                            $md[] = "  - type: `{$fromType}` → `{$toType}`";
                        }
                        if (($from['owning'] ?? null) !== ($to['owning'] ?? null)) {
                            $fromOwn = ($from['owning'] ?? false) ? 'yes' : 'no';
                            $toOwn = ($to['owning'] ?? false) ? 'yes' : 'no';
                            $md[] = "  - owning: `{$fromOwn}` → `{$toOwn}`";
                        }
                        if (($from['nullable'] ?? null) !== ($to['nullable'] ?? null)) {
                            $fromNull = ($from['nullable'] ?? false) ? 'yes' : 'no';
                            $toNull = ($to['nullable'] ?? false) ? 'yes' : 'no';
                            $md[] = "  - nullable: `{$fromNull}` → `{$toNull}`";
                        }
                    }
                    $md[] = "";
                }
            }
        }

        // Footer
        $md[] = "---";
        $md[] = "";
        $md[] = "*Generated by QD Schema Bundle*";

        return implode("\n", $md);
    }

    private function isDiffEmpty(?array $diff): bool
    {
        if ($diff === null) {
            return true;
        }
        foreach ($diff as $v) {
            if (!empty($v)) {
                return false;
            }
        }
        return true;
    }
}
