<?php
namespace Qd\SchemaBundle\Controller;

use Doctrine\ORM\EntityManagerInterface;
use Qd\SchemaBundle\Repository\EntityAliasRepository;
use Symfony\Component\HttpFoundation\JsonResponse;

final class SchemaApiController
{
    public function __construct(
        private EntityManagerInterface $em,
        private EntityAliasRepository $aliasRepository
    ) {}

    public function schema(): JsonResponse
    {
        $metas = $this->em->getMetadataFactory()->getAllMetadata();
        $out = ['entities' => []];

        $aliasesGrouped = $this->aliasRepository->findAllGroupedByEntity();

        foreach ($metas as $m) {
            if(str_starts_with($m->getTableName(), 'qd_')) {
                continue;
            }
            $name = substr($m->getName(), strrpos($m->getName(), '\\') + 1);

            $fqcn = $m->getName();
            $entity = [
                'name'   => $name,
                'fqcn'   => $fqcn,
                'table'  => $m->getTableName(),
                'pk'     => $m->getIdentifierFieldNames(),
                'fields' => [],
                'relations' => [],
                'aliases' => $aliasesGrouped[$fqcn] ?? [],
            ];

            foreach ($m->getFieldNames() as $field) {
                $map = $m->getFieldMapping($field);
                $entity['fields'][] = [
                    'name'     => $field,
                    'type'     => $map['type'] ?? null,
                    'nullable' => $map['nullable'] ?? false,
                    'length'   => $map['length'] ?? null,
                    'unique'   => $map['unique'] ?? false,
                ];
            }

            foreach ($m->associationMappings as $assoc) {
                $entity['relations'][] = [
                    'field'       => $assoc['fieldName'],
                    'target'      => substr($assoc['targetEntity'], strrpos($assoc['targetEntity'], '\\') + 1),
                    'type'        => $assoc['type'],
                    'mappedBy'    => $assoc['mappedBy']   ?? null,
                    'inversedBy'  => $assoc['inversedBy'] ?? null,
                    'isOwning'    => $assoc['isOwningSide'] ?? false,
                    'nullable'    => $assoc['joinColumns'][0]['nullable'] ?? null,
                ];
            }

            $out['entities'][] = $entity;
        }

        return new JsonResponse($out);
    }
}
