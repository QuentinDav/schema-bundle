<?php

namespace Qd\SchemaBundle\Service;

use Doctrine\ORM\EntityManagerInterface;
use Qd\SchemaBundle\Entity\Release;
use Symfony\Bundle\SecurityBundle\Security;

class SnapshotService
{

    public function __construct(
        private EntityManagerInterface $em,
        private SchemaExtractor        $extractor,
        private SchemaDiff             $diff,
        private Security               $security
    )
    {
    }

    /**
     * Create a new release with snapshots of all entities
     */
    public function createRelease(string $name, ?string $description = null): array
    {
        $conn = $this->em->getConnection();
        $factory = $this->em->getMetadataFactory();
        $metas = $factory->getAllMetadata();

        $user = $this->security->getUser()?->getUserIdentifier();
        $release = new Release($name, $description, $user);

        $changed = [];
        $added = [];
        $snapshotCount = 0;

        $conn->beginTransaction();
        try {
            foreach ($metas as $meta) {
                $fqcn = $meta->getName();

                $schema = $this->extractor->extractEntity($fqcn);
                $hash = SchemaExtractor::stableHash($schema);

                $prev = $conn->executeQuery(
                    'SELECT schema_json FROM qd_schema_snapshot WHERE entity_fqcn = :f ORDER BY created_at DESC LIMIT 1',
                    ['f' => $fqcn]
                )->fetchAssociative();

                $diffResult = $prev
                    ? $this->diff->diff(json_decode($prev['schema_json'], true), $schema)
                    : null;

                $snap = new \Qd\SchemaBundle\Entity\Snapshot(
                    $fqcn,
                    $schema,
                    $hash,
                    $diffResult,
                    $user
                );

                $release->addSnapshot($snap);
                $snapshotCount++;

                // Track changes
                if ($diffResult === null) {
                    $added[] = $fqcn;
                } elseif ($prev && !$this->diff->isEmpty($diffResult)) {
                    $changed[] = $fqcn;

                    // Create system comments for changes
                    $msg = $this->diff->toSystemComment($diffResult);
                    foreach ($msg as $m) {
                        $conn->insert('qd_schema_comment', [
                            'entity_fqcn' => $fqcn,
                            'body' => "Schéma mis à jour: $m",
                            'author' => 'system',
                            'is_system' => 1,
                            'created_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
                            'updated_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
                        ]);
                    }
                }
            }

            $this->em->persist($release);
            $this->em->flush();
            $conn->commit();

            return [
                'ok' => true,
                'release_id' => $release->getId(),
                'release_name' => $release->getName(),
                'snapshots' => $snapshotCount,
                'changed' => $changed,
                'added' => $added,
                'count_changed' => count($changed),
                'count_added' => count($added),
            ];
        } catch (\Throwable $e) {
            $conn->rollBack();
            throw $e;
        }
    }

    /**
     * @deprecated Use createRelease() instead
     */
    public function snapshotAll(): array
    {
        $conn = $this->em->getConnection();
        $factory = $this->em->getMetadataFactory();
        $metas = $factory->getAllMetadata();

        $changed = [];
        $created = 0;

        $conn->beginTransaction();
        try {
            foreach ($metas as $meta) {
                $fqcn = $meta->getName();

                $schema = $this->extractor->extractEntity($fqcn);
                $hash = SchemaExtractor::stableHash($schema);

                $prev = $conn->executeQuery(
                    'SELECT schema_json FROM qd_schema_snapshot WHERE entity_fqcn = :f ORDER BY created_at DESC LIMIT 1',
                    ['f' => $fqcn]
                )->fetchAssociative();

                $diff = $prev
                    ? $this->diff->diff(json_decode($prev['schema_json'], true), $schema)
                    : ['fields_added' => [], 'fields_removed' => [], 'fields_changed' => [], 'rels_added' => [], 'rels_removed' => [], 'rels_changed' => []];

                $snap = new \Qd\SchemaBundle\Entity\Snapshot(
                    $fqcn, $schema, $hash, $prev ? $diff : null,
                    $this->security->getUser()?->getUserIdentifier()
                );
                $this->em->persist($snap);
                $created++;

                if ($prev && !$this->diff->isEmpty($diff)) {
                    $msg = $this->diff->toSystemComment($diff);
                    foreach ($msg as $m) {
                        $conn->insert('qd_schema_comment', [
                            'entity_fqcn' => $fqcn,
                            'body' => "Schéma mis à jour: $m",
                            'author' => 'system',
                            'is_system' => 1,
                            'created_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
                            'updated_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
                        ]);
                        $changed[] = $fqcn;
                    }
                }
            }

            $this->em->flush();
            $conn->commit();

            return [
                'ok' => true,
                'snapshots' => $created,
                'changed' => $changed,
                'countChanged' => count($changed),
            ];
        } catch (\Throwable $e) {
            $conn->rollBack();
            throw $e;
        }
    }

}