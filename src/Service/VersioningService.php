<?php

namespace Qd\SchemaBundle\Service;

use Doctrine\ORM\EntityManagerInterface;
use Qd\SchemaBundle\Entity\Release;

final class VersioningService
{
    public function __construct(
        private EntityManagerInterface $em
    ) {
    }

    /**
     * @param string $type 'major', 'minor', or 'patch'
     * @return string The next version (e.g., "v1.2.3")
     */
    public function getNextVersion(string $type = 'minor'): string
    {
        $lastVersion = $this->getLastVersion();

        if ($lastVersion === null) {
            return 'v1.0.0';
        }

        [$major, $minor, $patch] = $this->parseVersion($lastVersion);

        return match($type) {
            'major' => sprintf('v%d.0.0', $major + 1),
            'minor' => sprintf('v%d.%d.0', $major, $minor + 1),
            'patch' => sprintf('v%d.%d.%d', $major, $minor, $patch + 1),
            default => sprintf('v%d.%d.0', $major, $minor + 1),
        };
    }

    /**
     * @param array $summary Summary with added_entities and changed_entities
     * @return string 'major', 'minor', or 'patch'
     */
    public function detectVersionType(array $summary): string
    {
        $addedEntities = $summary['added_entities'] ?? 0;
        $changedEntities = $summary['changed_entities'] ?? 0;
        $totalEntities = $summary['total_entities'] ?? 0;

        if ($changedEntities > 0 && ($changedEntities / max($totalEntities, 1)) > 0.2) {
            return 'major';
        }

        if ($addedEntities > 0) {
            return 'minor';
        }

        return 'patch';
    }

    private function getLastVersion(): ?string
    {
        $lastRelease = $this->em->getRepository(Release::class)
            ->createQueryBuilder('r')
            ->orderBy('r.createdAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        return $lastRelease?->getName();
    }

    /**
     * @return array [major, minor, patch]
     */
    private function parseVersion(string $version): array
    {
        // Remove 'v' prefix if present
        $version = ltrim($version, 'vV');

        // Split by dots
        $parts = explode('.', $version);

        return [
            (int)($parts[0] ?? 0),
            (int)($parts[1] ?? 0),
            (int)($parts[2] ?? 0),
        ];
    }

    public function isValidVersion(string $version): bool
    {
        return (bool)preg_match('/^v?\d+\.\d+\.\d+$/', $version);
    }

    /**
     * @return array ['version' => 'v1.2.0', 'type' => 'minor', 'reason' => '2 entities added']
     */
    public function getSuggestedVersion(array $summary): array
    {
        $type = $this->detectVersionType($summary);
        $version = $this->getNextVersion($type);

        $reason = match($type) {
            'major' => sprintf('%d entities modified (>20%%)', $summary['changed_entities']),
            'minor' => sprintf('%d %s added', $summary['added_entities'], $summary['added_entities'] > 1 ? 'entities' : 'entity'),
            'patch' => 'Minor changes to existing entities',
        };

        return [
            'version' => $version,
            'type' => $type,
            'reason' => $reason,
        ];
    }
}