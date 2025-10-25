<?php
namespace Qd\SchemaBundle\Controller;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;

final class SchemaApiController
{
    public function __construct(private EntityManagerInterface $em) {}

    public function schema(): JsonResponse
    {
        $metas = $this->em->getMetadataFactory()->getAllMetadata();
        $out = ['entities' => []];

        foreach ($metas as $m) {
            if(str_starts_with($m->getTableName(), 'qd_')) {
                continue;
            }
            // Nom court
            $name = substr($m->getName(), strrpos($m->getName(), '\\') + 1);

            $entity = [
                'name'   => $name,
                'fqcn'   => $m->getName(),
                'table'  => $m->getTableName(),
                'pk'     => $m->getIdentifierFieldNames(),
                'fields' => [],
                'relations' => [],
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
                    'type'        => $assoc['type'],               // 1:1, 2:1-N, 3:N-1, 4:N-N (constantes ClassMetadata)
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
