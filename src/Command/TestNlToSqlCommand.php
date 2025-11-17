<?php

declare(strict_types=1);

namespace Qd\SchemaBundle\Command;

use Qd\SchemaBundle\Service\NlToSql\NlToSqlOrchestrator;
use Qd\SchemaBundle\Service\SchemaExtractor;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Test command for Natural Language to SQL generation.
 */
#[AsCommand(
    name: 'qd:nl-to-sql:test',
    description: 'Test Natural Language to SQL generation with a given prompt',
)]
final class TestNlToSqlCommand extends Command
{
    public function __construct(
        private readonly NlToSqlOrchestrator $orchestrator,
        private readonly SchemaExtractor $schemaExtractor,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('prompt', InputArgument::REQUIRED, 'Natural language query')
            ->addOption('strategy', 's', InputOption::VALUE_OPTIONAL, 'Strategy: local, ai, or hybrid', null)
            ->addOption('compare', 'c', InputOption::VALUE_NONE, 'Compare all strategies')
            ->addOption('show-cost', null, InputOption::VALUE_NONE, 'Show cost breakdown')
            ->addOption('show-entities', null, InputOption::VALUE_NONE, 'Show entity list before generation')
            ->setHelp(<<<'HELP'
The <info>%command.name%</info> command tests the Natural Language to SQL generation:

  <info>php %command.full_name% "Get all users with their addresses"</info>

You can specify a strategy:
  <info>php %command.full_name% "Find training in Paris" --strategy=ai</info>

Compare all strategies:
  <info>php %command.full_name% "List users where age > 18" --compare</info>

Show cost breakdown:
  <info>php %command.full_name% "Get training data" --show-cost</info>
HELP
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $prompt = $input->getArgument('prompt');
        $strategy = $input->getOption('strategy');
        $compare = $input->getOption('compare');
        $showCost = $input->getOption('show-cost');
        $showEntities = $input->getOption('show-entities');

        $io->title('Natural Language to SQL Generator');
        $io->section('Input Prompt');
        $io->text($prompt);

        try {
            $io->section('Extracting Schema');
            $entities = $this->schemaExtractor->extract();
            $io->success(sprintf('Found %d entities', count($entities)));

            if ($showEntities) {
                $this->displayEntities($io, $entities);
            }

            if ($compare) {
                return $this->compareStrategies($io, $prompt, $entities, $showCost);
            }

            return $this->generateSingle($io, $prompt, $entities, $strategy, $showCost);
        } catch (\Exception $e) {
            $io->error('Failed to generate SQL: ' . $e->getMessage());
            if ($output->isVerbose()) {
                $io->text($e->getTraceAsString());
            }
            return Command::FAILURE;
        }
    }

    /**
     * Generate SQL with a single strategy.
     */
    private function generateSingle(
        SymfonyStyle $io,
        string $prompt,
        array $entities,
        ?string $strategy,
        bool $showCost
    ): int {
        $io->section('Generating SQL');

        if ($strategy !== null) {
            $io->text("Strategy: <info>{$strategy}</info>");
        }

        if ($showCost && $strategy !== 'local') {
            $estimate = $this->orchestrator->estimateCost($prompt, $strategy ?? 'ai');
            if ($estimate !== null) {
                $io->section('Cost Estimate');
                $io->table(
                    ['Metric', 'Value'],
                    [
                        ['Model', $estimate->model],
                        ['Estimated Cost', sprintf('$%.4f', $estimate->amount)],
                        ['Input Tokens', $estimate->estimatedInputTokens],
                        ['Output Tokens', $estimate->estimatedOutputTokens],
                    ]
                );
            }
        }

        $result = $this->orchestrator->generate($prompt, $entities, $strategy);

        $this->displayResult($io, $result, $showCost);

        return $result->success ? Command::SUCCESS : Command::FAILURE;
    }

    /**
     * Compare all strategies.
     */
    private function compareStrategies(
        SymfonyStyle $io,
        string $prompt,
        array $entities,
        bool $showCost
    ): int {
        $io->section('Comparing Strategies');

        $strategies = ['local', 'ai', 'hybrid'];
        $results = [];

        foreach ($strategies as $strategy) {
            $io->text("Running with strategy: <info>{$strategy}</info>");
            $result = $this->orchestrator->generate($prompt, $entities, $strategy);
            $results[$strategy] = $result;
        }

        $io->section('Comparison Results');
        $table = new Table($output = $io);
        $table->setHeaders(['Strategy', 'Success', 'Confidence', 'Provider', 'Cost']);

        foreach ($results as $strategy => $result) {
            $cost = $result->costInfo !== null
                ? sprintf('$%.4f', $result->costInfo->actual)
                : 'Free';

            $table->addRow([
                $strategy,
                $result->success ? '✓' : '✗',
                sprintf('%.2f', $result->confidence),
                $result->provider,
                $cost,
            ]);
        }

        $table->render();

        $bestResult = null;
        $bestConfidence = -1;

        foreach ($results as $strategy => $result) {
            if ($result->success && $result->confidence > $bestConfidence) {
                $bestResult = $result;
                $bestConfidence = $result->confidence;
            }
        }

        if ($bestResult !== null) {
            $io->section('Best Result');
            $this->displayResult($io, $bestResult, $showCost);
            return Command::SUCCESS;
        }

        $io->error('All strategies failed');
        return Command::FAILURE;
    }

    /**
     * Display a single result.
     */
    private function displayResult(SymfonyStyle $io, $result, bool $showCost): void
    {
        if ($result->success) {
            $io->success('SQL Generated Successfully');

            $io->section('Generated SQL');
            $io->block($result->sql, null, 'fg=white;bg=black', ' ', true);

            $io->section('Details');
            $io->table(
                ['Metric', 'Value'],
                [
                    ['Provider', $result->provider],
                    ['Confidence', sprintf('%.2f%%', $result->confidence * 100)],
                    ['Entities Used', implode(', ', array_column($result->entities, 'name'))],
                ]
            );

            if ($result->explanation) {
                $io->section('Explanation');
                $io->text($result->explanation);
            }

            if ($showCost && $result->costInfo !== null) {
                $io->section('Cost Information');
                $io->table(
                    ['Metric', 'Value'],
                    [
                        ['Estimated Cost', sprintf('$%.4f', $result->costInfo->estimated)],
                        ['Actual Cost', sprintf('$%.4f', $result->costInfo->actual)],
                        ['Input Tokens', $result->costInfo->inputTokens],
                        ['Output Tokens', $result->costInfo->outputTokens],
                        ['Total Tokens', $result->costInfo->totalTokens],
                    ]
                );
            }
        } else {
            $io->error('Generation Failed');
            $io->table(
                ['Field', 'Value'],
                [
                    ['Error', $result->error ?? 'Unknown'],
                    ['Message', $result->message ?? 'No message'],
                    ['Provider', $result->provider],
                ]
            );

            if (!empty($result->suggestions)) {
                $io->section('Suggestions');
                $io->listing($result->suggestions);
            }
        }
    }

    /**
     * Display available entities.
     *
     * @param array<int, array<string, mixed>> $entities
     */
    private function displayEntities(SymfonyStyle $io, array $entities): void
    {
        $io->section('Available Entities');
        $table = new Table($output = $io);
        $table->setHeaders(['Entity', 'Table', 'Fields', 'Associations']);

        foreach ($entities as $entity) {
            $table->addRow([
                $entity['name'] ?? 'Unknown',
                $entity['tableName'] ?? '-',
                count($entity['fields'] ?? []),
                count($entity['associations'] ?? []),
            ]);
        }

        $table->render();
    }
}
