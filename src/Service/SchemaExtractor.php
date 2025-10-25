<?php
namespace Qd\SchemaBundle\Service;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;

final class SchemaExtractor
{
    public function __construct(private EntityManagerInterface $em) {}

    public function extractEntity(string $fqcn): array
    {
        /** @var ClassMetadata $m */
        $m = $this->em->getClassMetadata($fqcn);

        $fields = [];
        foreach ($m->getFieldNames() as $name) {
            $map = $m->getFieldMapping($name);
            $fields[$name] = [
                'type'     => $map['type'] ?? null,
                'length'   => $map['length'] ?? null,
                'nullable' => (bool)($map['nullable'] ?? false),
                'unique'   => (bool)($map['unique'] ?? false),
            ];
        }
        ksort($fields);

        $rels = [];
        foreach ($m->associationMappings as $a) {
            $type = $a['type'];
            $rels[$a['fieldName']] = [
                'target'    => $a['targetEntity'],
                'type'      => match ($type) {
                    ClassMetadata::ONE_TO_ONE   => 'one_to_one',
                    ClassMetadata::MANY_TO_ONE  => 'many_to_one',
                    ClassMetadata::ONE_TO_MANY  => 'one_to_many',
                    ClassMetadata::MANY_TO_MANY => 'many_to_many',
                    default => 'unknown'
                },
                'owning'    => (bool)($a['isOwningSide'] ?? false),
                // nullable n'a de sens que pour owning 1-1 / N-1
                'nullable'  => ($type === ClassMetadata::ONE_TO_ONE || $type === ClassMetadata::MANY_TO_ONE)
                && ($a['isOwningSide'] ?? false)
                    ? ($a['joinColumns'][0]['nullable'] ?? null)
                    : null,
            ];
        }
        ksort($rels);

        return [
            'fqcn'   => $m->getName(),
            'table'  => $m->getTableName(),
            'pk'     => $m->getIdentifierFieldNames(),
            'fields' => $fields,
            'rels'   => $rels,
        ];
    }

    public static function stableHash(array $schema): string
    {
        $norm = self::stableJson($schema);
        return hash('sha256', $norm);
    }

    public static function stableJson(array $data): string
    {
        $sort = function (&$v) use (&$sort) {
            if (is_array($v)) {
                ksort($v);
                foreach ($v as &$vv) $sort($vv);
            }
        };
        $sort($data);
        return json_encode($data, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
    }
}
