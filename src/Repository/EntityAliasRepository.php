<?php

declare(strict_types=1);

namespace Qd\SchemaBundle\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Qd\SchemaBundle\Entity\EntityAlias;

/**
 * @extends ServiceEntityRepository<EntityAlias>
 */
class EntityAliasRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, EntityAlias::class);
    }

    /**
     * Find all aliases for a specific entity.
     *
     * @param string $entityFqcn
     * @return EntityAlias[]
     */
    public function findByEntity(string $entityFqcn): array
    {
        return $this->createQueryBuilder('a')
            ->where('a.entityFqcn = :fqcn')
            ->setParameter('fqcn', $entityFqcn)
            ->orderBy('a.alias', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find entity FQCN by alias.
     *
     * @param string $alias
     * @return string|null
     */
    public function findEntityByAlias(string $alias): ?string
    {
        $result = $this->createQueryBuilder('a')
            ->select('a.entityFqcn')
            ->where('LOWER(a.alias) = LOWER(:alias)')
            ->setParameter('alias', $alias)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        return $result['entityFqcn'] ?? null;
    }

    /**
     * Get all aliases grouped by entity.
     *
     * @return array<string, array<string, mixed>>
     */
    public function findAllGroupedByEntity(): array
    {
        $aliases = $this->createQueryBuilder('a')
            ->orderBy('a.entityFqcn', 'ASC')
            ->addOrderBy('a.alias', 'ASC')
            ->getQuery()
            ->getResult();

        $grouped = [];
        foreach ($aliases as $alias) {
            $fqcn = $alias->getEntityFqcn();
            if (!isset($grouped[$fqcn])) {
                $grouped[$fqcn] = [];
            }
            $grouped[$fqcn][] = $alias->toArray();
        }

        return $grouped;
    }

    /**
     * Get a map of alias => entity FQCN for quick lookups.
     *
     * @return array<string, string>
     */
    public function getAliasToEntityMap(): array
    {
        $qb = $this->createQueryBuilder('a')
            ->select('a.alias, a.entityFqcn')
            ->getQuery();

        $results = $qb->getArrayResult();

        $map = [];
        foreach ($results as $row) {
            $map[mb_strtolower($row['alias'])] = $row['entityFqcn'];
        }

        return $map;
    }

    /**
     * Check if an alias exists.
     *
     * @param string $alias
     * @param int|null $excludeId Exclude this ID from the check (for updates)
     * @return bool
     */
    public function aliasExists(string $alias, ?int $excludeId = null): bool
    {
        $qb = $this->createQueryBuilder('a')
            ->select('COUNT(a.id)')
            ->where('LOWER(a.alias) = LOWER(:alias)')
            ->setParameter('alias', $alias);

        if ($excludeId !== null) {
            $qb->andWhere('a.id != :id')
                ->setParameter('id', $excludeId);
        }

        return (int) $qb->getQuery()->getSingleScalarResult() > 0;
    }

    public function save(EntityAlias $alias): void
    {
        $this->getEntityManager()->persist($alias);
        $this->getEntityManager()->flush();
    }

    public function remove(EntityAlias $alias): void
    {
        $this->getEntityManager()->remove($alias);
        $this->getEntityManager()->flush();
    }
}
