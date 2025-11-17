<?php
namespace Qd\SchemaBundle\Service;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;

final class SchemaExtractor
{
    public function __construct(private EntityManagerInterface $em) {}

    /**
     * Extract all entities from Doctrine metadata.
     *
     * @return array<int, array<string, mixed>>
     */
    public function extract(): array
    {
        $entities = [];
        $metadatas = $this->em->getMetadataFactory()->getAllMetadata();

        foreach ($metadatas as $metadata) {
            $entityData = $this->extractEntity($metadata->getName());

            $entities[] = [
                'name' => $this->getShortName($metadata->getName()),
                'fqcn' => $metadata->getName(),
                'tableName' => $metadata->getTableName(),
                'fields' => $this->transformFields($entityData['fields']),
                'associations' => $this->transformAssociations($entityData['rels']),
            ];
        }

        return $entities;
    }

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

    /**
     * Get short class name from FQCN.
     */
    private function getShortName(string $fqcn): string
    {
        $parts = explode('\\', $fqcn);
        return end($parts);
    }

    /**
     * Transform fields to NL→SQL format.
     */
    private function transformFields(array $fields): array
    {
        $result = [];
        foreach ($fields as $name => $field) {
            $result[] = [
                'name' => $name,
                'type' => $field['type'],
                'nullable' => $field['nullable'],
            ];
        }
        return $result;
    }

    /**
     * Transform associations to NL→SQL format.
     */
    private function transformAssociations(array $rels): array
    {
        $result = [];
        foreach ($rels as $fieldName => $rel) {
            $result[] = [
                'fieldName' => $fieldName,
                'targetEntity' => $this->getShortName($rel['target']),
                'type' => $rel['type'],
            ];
        }
        return $result;
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
