<?php

declare(strict_types=1);

namespace Qd\SchemaBundle\Command;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Explains a Doctrine entity in detail.
 *
 * This command provides comprehensive information about an entity:
 * - Table name, repository, lifecycle callbacks
 * - All fields with types, nullable, unique constraints
 * - All relations with cardinality, owning/inverse side
 * - Indexes and unique constraints
 * - Inheritance information if applicable
 */
#[AsCommand(
    name: 'qd:schema:explain',
    description: 'Explains a Doctrine entity in detail',
)]
class SchemaExplainCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $em
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('entity', InputArgument::REQUIRED, 'Entity class name (short name or FQCN)')
            ->addOption('fields', 'f', InputOption::VALUE_NONE, 'Show detailed field information')
            ->addOption('relations', 'r', InputOption::VALUE_NONE, 'Show detailed relation information')
            ->addOption('sql', null, InputOption::VALUE_NONE, 'Show the CREATE TABLE SQL')
            ->addOption('json', null, InputOption::VALUE_NONE, 'Output as JSON')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $entityName = $input->getArgument('entity');

        try {
            $metadata = $this->resolveEntity($entityName);
        } catch (\Exception $e) {
            $io->error($e->getMessage());
            return Command::FAILURE;
        }

        $showFields = $input->getOption('fields');
        $showRelations = $input->getOption('relations');
        $showSql = $input->getOption('sql');
        $asJson = $input->getOption('json');

        if (!$showFields && !$showRelations && !$showSql) {
            $showFields = true;
            $showRelations = true;
        }

        $data = $this->extractEntityData($metadata);

        if ($asJson) {
            $output->writeln(json_encode($data, JSON_PRETTY_PRINT));
            return Command::SUCCESS;
        }

        $this->displayEntity($io, $metadata, $data, $showFields, $showRelations, $showSql);

        return Command::SUCCESS;
    }

    private function resolveEntity(string $entityName): ClassMetadata
    {
        $metadataFactory = $this->em->getMetadataFactory();
        $allMetadata = $metadataFactory->getAllMetadata();

        foreach ($allMetadata as $metadata) {
            if ($metadata->getName() === $entityName) {
                return $metadata;
            }
        }

        $matches = [];
        foreach ($allMetadata as $metadata) {
            $shortName = substr($metadata->getName(), strrpos($metadata->getName(), '\\') + 1);
            if (strcasecmp($shortName, $entityName) === 0) {
                $matches[] = $metadata;
            }
        }

        if (count($matches) === 1) {
            return $matches[0];
        }

        if (count($matches) > 1) {
            $fqcns = array_map(fn($m) => $m->getName(), $matches);
            throw new \RuntimeException(sprintf(
                'Multiple entities found for "%s". Please use the fully qualified class name:%s%s',
                $entityName,
                PHP_EOL,
                '  - ' . implode(PHP_EOL . '  - ', $fqcns)
            ));
        }

        throw new \RuntimeException(sprintf('Entity "%s" not found.', $entityName));
    }

    private function extractEntityData(ClassMetadata $metadata): array
    {
        return [
            'class' => $metadata->getName(),
            'table' => $metadata->getTableName(),
            'repository' => $metadata->customRepositoryClassName ?? 'Default',
            'readOnly' => $metadata->isReadOnly,
            'fields' => $this->extractFields($metadata),
            'relations' => $this->extractRelations($metadata),
            'indexes' => $metadata->table['indexes'] ?? [],
            'uniqueConstraints' => $metadata->table['uniqueConstraints'] ?? [],
            'inheritance' => $this->extractInheritance($metadata),
            'lifecycleCallbacks' => $metadata->lifecycleCallbacks,
        ];
    }

    private function extractFields(ClassMetadata $metadata): array
    {
        $fields = [];

        foreach ($metadata->getFieldNames() as $fieldName) {
            $mapping = $metadata->getFieldMapping($fieldName);

            $fields[] = [
                'name' => $fieldName,
                'type' => $mapping['type'],
                'column' => $mapping['columnName'] ?? $fieldName,
                'nullable' => $mapping['nullable'] ?? false,
                'unique' => $mapping['unique'] ?? false,
                'length' => $mapping['length'] ?? null,
                'precision' => $mapping['precision'] ?? null,
                'scale' => $mapping['scale'] ?? null,
                'default' => $mapping['options']['default'] ?? null,
                'generated' => $mapping['generated'] ?? null,
            ];
        }

        return $fields;
    }

    private function extractRelations(ClassMetadata $metadata): array
    {
        $relations = [];

        foreach ($metadata->getAssociationNames() as $assocName) {
            $mapping = $metadata->getAssociationMapping($assocName);

            $relations[] = [
                'field' => $assocName,
                'type' => $this->getRelationType($mapping['type']),
                'target' => $mapping['targetEntity'],
                'mappedBy' => $mapping['mappedBy'] ?? null,
                'inversedBy' => $mapping['inversedBy'] ?? null,
                'isOwningSide' => $mapping['isOwningSide'],
                'cascade' => $mapping['cascade'] ?? [],
                'fetch' => $mapping['fetch'] === ClassMetadata::FETCH_EAGER ? 'EAGER' : 'LAZY',
                'orphanRemoval' => $mapping['orphanRemoval'] ?? false,
                'joinColumns' => $mapping['joinColumns'] ?? null,
                'joinTable' => $mapping['joinTable'] ?? null,
            ];
        }

        return $relations;
    }

    private function getRelationType(int $type): string
    {
        return match ($type) {
            ClassMetadata::ONE_TO_ONE => 'OneToOne',
            ClassMetadata::MANY_TO_ONE => 'ManyToOne',
            ClassMetadata::ONE_TO_MANY => 'OneToMany',
            ClassMetadata::MANY_TO_MANY => 'ManyToMany',
            default => 'Unknown',
        };
    }

    private function extractInheritance(ClassMetadata $metadata): ?array
    {
        if (!$metadata->inheritanceType) {
            return null;
        }

        return [
            'type' => $this->getInheritanceType($metadata->inheritanceType),
            'discriminatorColumn' => $metadata->discriminatorColumn ?? null,
            'discriminatorMap' => $metadata->discriminatorMap ?? [],
            'parentClasses' => $metadata->parentClasses,
            'subClasses' => $metadata->subClasses,
        ];
    }

    private function getInheritanceType(int $type): string
    {
        return match ($type) {
            ClassMetadata::INHERITANCE_TYPE_NONE => 'None',
            ClassMetadata::INHERITANCE_TYPE_JOINED => 'Joined',
            ClassMetadata::INHERITANCE_TYPE_SINGLE_TABLE => 'SingleTable',
            ClassMetadata::INHERITANCE_TYPE_TABLE_PER_CLASS => 'TablePerClass',
            default => 'Unknown',
        };
    }

    private function displayEntity(
        SymfonyStyle $io,
        ClassMetadata $metadata,
        array $data,
        bool $showFields,
        bool $showRelations,
        bool $showSql
    ): void {
        $io->title(sprintf('Entity: %s', $metadata->getName()));

        $io->section('Basic Information');
        $io->definitionList(
            ['Table' => $data['table']],
            ['Repository' => $data['repository']],
            ['Read Only' => $data['readOnly'] ? 'Yes' : 'No'],
        );

        if ($data['inheritance']) {
            $io->section('Inheritance');
            $io->definitionList(
                ['Type' => $data['inheritance']['type']],
                ['Parent Classes' => implode(', ', $data['inheritance']['parentClasses']) ?: 'None'],
                ['Sub Classes' => implode(', ', $data['inheritance']['subClasses']) ?: 'None'],
            );
        }

        if ($showFields) {
            $io->section(sprintf('Fields (%d)', count($data['fields'])));

            $fieldRows = [];
            foreach ($data['fields'] as $field) {
                $constraints = [];
                if ($field['nullable']) {
                    $constraints[] = '<fg=gray>nullable</>';
                }
                if ($field['unique']) {
                    $constraints[] = '<fg=yellow>unique</>';
                }
                if ($field['generated']) {
                    $constraints[] = '<fg=cyan>generated</>';
                }

                $typeInfo = $field['type'];
                if ($field['length']) {
                    $typeInfo .= sprintf('(%d)', $field['length']);
                } elseif ($field['precision'] && $field['scale']) {
                    $typeInfo .= sprintf('(%d,%d)', $field['precision'], $field['scale']);
                }

                $fieldRows[] = [
                    $field['name'],
                    $typeInfo,
                    $field['column'],
                    implode(', ', $constraints) ?: '-',
                ];
            }

            $io->table(['Field', 'Type', 'Column', 'Constraints'], $fieldRows);
        }

        if ($showRelations) {
            $io->section(sprintf('Relations (%d)', count($data['relations'])));

            if (empty($data['relations'])) {
                $io->text('<fg=gray>No relations defined.</>');
            } else {
                $relationRows = [];
                foreach ($data['relations'] as $relation) {
                    $side = $relation['isOwningSide'] ? '<fg=green>owning</>' : '<fg=cyan>inverse</>';
                    $targetShort = substr($relation['target'], strrpos($relation['target'], '\\') + 1);

                    $extra = [];
                    if ($relation['mappedBy']) {
                        $extra[] = sprintf('mappedBy=%s', $relation['mappedBy']);
                    }
                    if ($relation['inversedBy']) {
                        $extra[] = sprintf('inversedBy=%s', $relation['inversedBy']);
                    }
                    if (!empty($relation['cascade'])) {
                        $extra[] = sprintf('cascade=[%s]', implode(',', $relation['cascade']));
                    }

                    $relationRows[] = [
                        $relation['field'],
                        sprintf('<fg=yellow>%s</>', $relation['type']),
                        $targetShort,
                        $side,
                        implode(', ', $extra),
                    ];
                }

                $io->table(['Field', 'Type', 'Target', 'Side', 'Details'], $relationRows);
            }
        }

        if (!empty($data['indexes'])) {
            $io->section('Indexes');
            $indexRows = [];
            foreach ($data['indexes'] as $name => $index) {
                $indexRows[] = [
                    $name,
                    implode(', ', $index['columns'] ?? []),
                    isset($index['options']['unique']) && $index['options']['unique'] ? 'Yes' : 'No',
                ];
            }
            $io->table(['Name', 'Columns', 'Unique'], $indexRows);
        }

        if (!empty($data['uniqueConstraints'])) {
            $io->section('Unique Constraints');
            $constraintRows = [];
            foreach ($data['uniqueConstraints'] as $name => $constraint) {
                $constraintRows[] = [
                    $name,
                    implode(', ', $constraint['columns'] ?? []),
                ];
            }
            $io->table(['Name', 'Columns'], $constraintRows);
        }

        if (!empty($data['lifecycleCallbacks'])) {
            $io->section('Lifecycle Callbacks');
            $callbackList = [];
            foreach ($data['lifecycleCallbacks'] as $event => $callbacks) {
                $callbackList[] = sprintf('<fg=cyan>%s</>: %s', $event, implode(', ', $callbacks));
            }
            $io->listing($callbackList);
        }

        if ($showSql) {
            $io->section('CREATE TABLE SQL');
            try {
                $sql = $this->generateCreateTableSql($metadata);
                $io->block($sql, null, 'fg=white;bg=black', ' ', true);
            } catch (\Exception $e) {
                $io->error('Could not generate SQL: ' . $e->getMessage());
            }
        }

        $io->success(sprintf(
            'Entity "%s" has %d fields and %d relations.',
            $metadata->getName(),
            count($data['fields']),
            count($data['relations'])
        ));
    }

    private function generateCreateTableSql(ClassMetadata $metadata): string
    {
        $platform = $this->em->getConnection()->getDatabasePlatform();
        $schemaManager = $this->em->getConnection()->createSchemaManager();
        $schema = $schemaManager->introspectSchema();

        $toSchema = clone $schema;
        $table = $metadata->getTableName();

        if ($toSchema->hasTable($table)) {
            $tableObj = $toSchema->getTable($table);
            $fromSchema = new \Doctrine\DBAL\Schema\Schema();

            $sqls = $fromSchema->getMigrateToSql($toSchema, $platform);

            foreach ($sqls as $sql) {
                if (stripos($sql, 'CREATE TABLE') !== false && stripos($sql, $table) !== false) {
                    return $sql;
                }
            }
        }

        return 'Table does not exist in current schema.';
    }
}
