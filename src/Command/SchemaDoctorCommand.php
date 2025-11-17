<?php

declare(strict_types=1);

namespace Qd\SchemaBundle\Command;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Health check command for the schema.
 *
 * Detects common issues:
 * - Entities without relations
 * - Circular dependencies
 * - Missing indexes on foreign keys
 * - Inefficient bidirectional relations
 * - Tables without primary keys
 * - Orphaned inverse sides (mappedBy to non-existent field)
 * - Naming inconsistencies
 */
#[AsCommand(
    name: 'qd:schema:doctor',
    description: 'Runs health checks on the Doctrine schema',
)]
class SchemaDoctorCommand extends Command
{
    private array $issues = [];
    private array $warnings = [];
    private array $suggestions = [];

    public function __construct(
        private readonly EntityManagerInterface $em
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('fix', null, InputOption::VALUE_NONE, 'Attempt to fix issues automatically (not implemented yet)')
            ->addOption('verbose-checks', null, InputOption::VALUE_NONE, 'Show detailed information for each check')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $verboseChecks = $input->getOption('verbose-checks');

        $io->title('Schema Doctor - Health Check');

        $metadataFactory = $this->em->getMetadataFactory();
        $allMetadata = $metadataFactory->getAllMetadata();

        $io->section(sprintf('Analyzing %d entities...', count($allMetadata)));

        $progressBar = $io->createProgressBar(count($allMetadata));
        $progressBar->start();

        foreach ($allMetadata as $metadata) {
            $this->checkEntity($metadata);
            $progressBar->advance();
        }

        $progressBar->finish();
        $io->newLine(2);

        $io->text('Running cross-entity checks...');
        $this->checkCircularDependencies($allMetadata);
        $this->checkOrphanedRelations($allMetadata);

        $this->displayResults($io, $verboseChecks);

        if (count($this->issues) === 0) {
            $io->success('No critical issues found! Your schema looks healthy.');
            return Command::SUCCESS;
        }

        $io->warning(sprintf('%d issue(s) found.', count($this->issues)));
        return Command::FAILURE;
    }

    private function checkEntity(ClassMetadata $metadata): void
    {
        $entityName = $metadata->getName();

        if (empty($metadata->getAssociationNames())) {
            $this->warnings[] = [
                'type' => 'isolated_entity',
                'entity' => $entityName,
                'message' => sprintf('Entity "%s" has no relations (isolated entity)', $this->getShortName($entityName)),
            ];
        }

        if (empty($metadata->getIdentifier())) {
            $this->issues[] = [
                'type' => 'no_primary_key',
                'entity' => $entityName,
                'message' => sprintf('Entity "%s" has no primary key defined', $this->getShortName($entityName)),
            ];
        }

        foreach ($metadata->getAssociationNames() as $assocName) {
            $this->checkRelation($metadata, $assocName);
        }

        $this->checkNamingConventions($metadata);

        $this->checkIndexes($metadata);
    }

    private function checkRelation(ClassMetadata $metadata, string $assocName): void
    {
        $mapping = $metadata->getAssociationMapping($assocName);
        $entityName = $metadata->getName();

        if (isset($mapping['inversedBy']) && $mapping['type'] === ClassMetadata::MANY_TO_MANY) {
            $this->suggestions[] = [
                'type' => 'many_to_many_bidirectional',
                'entity' => $entityName,
                'field' => $assocName,
                'message' => sprintf(
                    'Entity "%s" has bidirectional ManyToMany on "%s". Consider making it unidirectional if not needed.',
                    $this->getShortName($entityName),
                    $assocName
                ),
            ];
        }

        if (!empty($mapping['cascade']) && in_array('remove', $mapping['cascade'], true)) {
            if ($mapping['type'] === ClassMetadata::MANY_TO_MANY) {
                $this->warnings[] = [
                    'type' => 'cascade_remove_many_to_many',
                    'entity' => $entityName,
                    'field' => $assocName,
                    'message' => sprintf(
                        'Entity "%s" uses cascade=remove on ManyToMany relation "%s". This can be dangerous!',
                        $this->getShortName($entityName),
                        $assocName
                    ),
                ];
            }
        }

        if (isset($mapping['orphanRemoval']) && $mapping['orphanRemoval'] && !$mapping['isOwningSide']) {
            $this->issues[] = [
                'type' => 'orphan_removal_non_owning',
                'entity' => $entityName,
                'field' => $assocName,
                'message' => sprintf(
                    'Entity "%s" has orphanRemoval on non-owning side "%s". This only works on OneToMany and OneToOne (owning side).',
                    $this->getShortName($entityName),
                    $assocName
                ),
            ];
        }

        if (in_array($mapping['type'], [ClassMetadata::MANY_TO_ONE, ClassMetadata::ONE_TO_ONE], true)) {
            if ($mapping['fetch'] === ClassMetadata::FETCH_EAGER) {
                $this->suggestions[] = [
                    'type' => 'eager_loading',
                    'entity' => $entityName,
                    'field' => $assocName,
                    'message' => sprintf(
                        'Entity "%s" uses EAGER loading on "%s". Consider using LAZY or EXTRA_LAZY for better performance.',
                        $this->getShortName($entityName),
                        $assocName
                    ),
                ];
            }
        }
    }

    private function checkNamingConventions(ClassMetadata $metadata): void
    {
        $entityName = $metadata->getName();
        $tableName = $metadata->getTableName();

        if ($tableName !== strtolower($tableName)) {
            $this->suggestions[] = [
                'type' => 'table_naming',
                'entity' => $entityName,
                'message' => sprintf(
                    'Entity "%s" has table name "%s" which is not in snake_case.',
                    $this->getShortName($entityName),
                    $tableName
                ),
            ];
        }

        foreach ($metadata->getFieldNames() as $fieldName) {
            $mapping = $metadata->getFieldMapping($fieldName);
            $columnName = $mapping['columnName'] ?? $fieldName;

            if ($columnName !== strtolower($columnName)) {
                $this->suggestions[] = [
                    'type' => 'column_naming',
                    'entity' => $entityName,
                    'field' => $fieldName,
                    'message' => sprintf(
                        'Entity "%s" has column "%s" which is not in snake_case.',
                        $this->getShortName($entityName),
                        $columnName
                    ),
                ];
            }
        }
    }

    private function checkIndexes(ClassMetadata $metadata): void
    {
        $entityName = $metadata->getName();
        $indexes = $metadata->table['indexes'] ?? [];
        $indexedColumns = [];

        foreach ($indexes as $index) {
            foreach ($index['columns'] ?? [] as $column) {
                $indexedColumns[] = $column;
            }
        }

        foreach ($metadata->getAssociationNames() as $assocName) {
            $mapping = $metadata->getAssociationMapping($assocName);

            if (!$mapping['isOwningSide']) {
                continue;
            }

            if (isset($mapping['joinColumns'])) {
                foreach ($mapping['joinColumns'] as $joinColumn) {
                    $columnName = $joinColumn['name'];

                    if (!in_array($columnName, $indexedColumns, true)) {
                        $this->suggestions[] = [
                            'type' => 'missing_index',
                            'entity' => $entityName,
                            'field' => $assocName,
                            'column' => $columnName,
                            'message' => sprintf(
                                'Entity "%s" relation "%s" (column "%s") has no index. Consider adding one for better performance.',
                                $this->getShortName($entityName),
                                $assocName,
                                $columnName
                            ),
                        ];
                    }
                }
            }
        }
    }

    private function checkCircularDependencies(array $allMetadata): void
    {
        $graph = [];

        foreach ($allMetadata as $metadata) {
            $entityName = $metadata->getName();
            $graph[$entityName] = [];

            foreach ($metadata->getAssociationNames() as $assocName) {
                $mapping = $metadata->getAssociationMapping($assocName);
                if ($mapping['isOwningSide']) {
                    $graph[$entityName][] = $mapping['targetEntity'];
                }
            }
        }

        $visited = [];
        $recursionStack = [];

        foreach (array_keys($graph) as $node) {
            if (!isset($visited[$node])) {
                $this->detectCycle($node, $graph, $visited, $recursionStack, []);
            }
        }
    }

    private function detectCycle(string $node, array $graph, array &$visited, array &$recursionStack, array $path): void
    {
        $visited[$node] = true;
        $recursionStack[$node] = true;
        $path[] = $node;

        foreach ($graph[$node] ?? [] as $neighbor) {
            if (!isset($visited[$neighbor])) {
                $this->detectCycle($neighbor, $graph, $visited, $recursionStack, $path);
            } elseif (isset($recursionStack[$neighbor]) && $recursionStack[$neighbor]) {
                $cyclePath = array_slice($path, array_search($neighbor, $path));
                $cyclePath[] = $neighbor;

                $this->warnings[] = [
                    'type' => 'circular_dependency',
                    'cycle' => $cyclePath,
                    'message' => sprintf(
                        'Circular dependency detected: %s',
                        implode(' -> ', array_map(fn($c) => $this->getShortName($c), $cyclePath))
                    ),
                ];
            }
        }

        $recursionStack[$node] = false;
    }

    private function checkOrphanedRelations(array $allMetadata): void
    {
        $entityMap = [];
        foreach ($allMetadata as $metadata) {
            $entityMap[$metadata->getName()] = $metadata;
        }

        foreach ($allMetadata as $metadata) {
            foreach ($metadata->getAssociationNames() as $assocName) {
                $mapping = $metadata->getAssociationMapping($assocName);

                if (!isset($entityMap[$mapping['targetEntity']])) {
                    $this->issues[] = [
                        'type' => 'orphaned_relation',
                        'entity' => $metadata->getName(),
                        'field' => $assocName,
                        'target' => $mapping['targetEntity'],
                        'message' => sprintf(
                            'Entity "%s" has relation "%s" pointing to non-existent entity "%s"',
                            $this->getShortName($metadata->getName()),
                            $assocName,
                            $this->getShortName($mapping['targetEntity'])
                        ),
                    ];
                    continue;
                }

                $targetMetadata = $entityMap[$mapping['targetEntity']];

                if (isset($mapping['mappedBy'])) {
                    $mappedByField = $mapping['mappedBy'];
                    if (!$targetMetadata->hasAssociation($mappedByField)) {
                        $this->issues[] = [
                            'type' => 'invalid_mapped_by',
                            'entity' => $metadata->getName(),
                            'field' => $assocName,
                            'mappedBy' => $mappedByField,
                            'target' => $mapping['targetEntity'],
                            'message' => sprintf(
                                'Entity "%s" relation "%s" has mappedBy="%s" but field does not exist on "%s"',
                                $this->getShortName($metadata->getName()),
                                $assocName,
                                $mappedByField,
                                $this->getShortName($mapping['targetEntity'])
                            ),
                        ];
                    }
                }

                if (isset($mapping['inversedBy'])) {
                    $inversedByField = $mapping['inversedBy'];
                    if (!$targetMetadata->hasAssociation($inversedByField)) {
                        $this->issues[] = [
                            'type' => 'invalid_inversed_by',
                            'entity' => $metadata->getName(),
                            'field' => $assocName,
                            'inversedBy' => $inversedByField,
                            'target' => $mapping['targetEntity'],
                            'message' => sprintf(
                                'Entity "%s" relation "%s" has inversedBy="%s" but field does not exist on "%s"',
                                $this->getShortName($metadata->getName()),
                                $assocName,
                                $inversedByField,
                                $this->getShortName($mapping['targetEntity'])
                            ),
                        ];
                    }
                }
            }
        }
    }

    private function displayResults(SymfonyStyle $io, bool $verbose): void
    {
        $totalIssues = count($this->issues);
        $totalWarnings = count($this->warnings);
        $totalSuggestions = count($this->suggestions);

        if ($totalIssues > 0) {
            $io->section(sprintf('Critical Issues (%d)', $totalIssues));

            foreach ($this->issues as $issue) {
                $io->error($issue['message']);
                if ($verbose) {
                    $io->text(sprintf('  Type: %s', $issue['type']));
                    if (isset($issue['entity'])) {
                        $io->text(sprintf('  Entity: %s', $issue['entity']));
                    }
                }
            }
        }

        if ($totalWarnings > 0) {
            $io->section(sprintf('Warnings (%d)', $totalWarnings));

            foreach ($this->warnings as $warning) {
                $io->warning($warning['message']);
                if ($verbose) {
                    $io->text(sprintf('  Type: %s', $warning['type']));
                }
            }
        }

        if ($totalSuggestions > 0) {
            $io->section(sprintf('Suggestions (%d)', $totalSuggestions));

            foreach ($this->suggestions as $suggestion) {
                $io->note($suggestion['message']);
                if ($verbose) {
                    $io->text(sprintf('  Type: %s', $suggestion['type']));
                }
            }
        }

        $io->section('Summary');
        $io->definitionList(
            ['Critical Issues' => sprintf('<fg=%s>%d</>', $totalIssues > 0 ? 'red' : 'green', $totalIssues)],
            ['Warnings' => sprintf('<fg=%s>%d</>', $totalWarnings > 0 ? 'yellow' : 'green', $totalWarnings)],
            ['Suggestions' => sprintf('<fg=%s>%d</>', $totalSuggestions > 0 ? 'cyan' : 'green', $totalSuggestions)],
        );
    }

    private function getShortName(string $fqcn): string
    {
        return substr($fqcn, strrpos($fqcn, '\\') + 1);
    }
}
