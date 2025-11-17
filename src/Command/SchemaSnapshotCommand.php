<?php

declare(strict_types=1);

namespace Qd\SchemaBundle\Command;

use Qd\SchemaBundle\Service\SnapshotService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Interactive command to create schema snapshots (releases).
 *
 * Provides a user-friendly CLI interface for creating releases with:
 * - Interactive mode (asks for name and description)
 * - Non-interactive mode (pass arguments directly)
 * - Summary of changes before creating
 * - Detailed output of what changed
 */
#[AsCommand(
    name: 'qd:schema:snapshot',
    description: 'Creates a schema snapshot (release) with optional interactive mode',
)]
class SchemaSnapshotCommand extends Command
{
    public function __construct(
        private readonly SnapshotService $snapshotService
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('name', InputArgument::OPTIONAL, 'Release name (e.g., "v1.2.0", "Sprint 42")')
            ->addArgument('description', InputArgument::OPTIONAL, 'Release description')
            ->addOption('interactive', 'i', InputOption::VALUE_NONE, 'Run in interactive mode')
            ->addOption('yes', 'y', InputOption::VALUE_NONE, 'Skip confirmation prompt')
            ->setHelp(<<<'HELP'
The <info>%command.name%</info> command creates a new schema snapshot (release).

<comment>Basic usage:</comment>
  <info>php %command.full_name% "v1.2.0" "Added User authentication"</info>

<comment>Interactive mode:</comment>
  <info>php %command.full_name% --interactive</info>
  or
  <info>php %command.full_name% -i</info>

<comment>Skip confirmation:</comment>
  <info>php %command.full_name% "v1.2.0" --yes</info>

This command will:
1. Analyze all Doctrine entities
2. Compare current schema with the last snapshot
3. Create a new release with all changes
4. Generate system comments for schema modifications
HELP
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->title('Schema Snapshot Creator');

        $interactive = $input->getOption('interactive');
        $skipConfirm = $input->getOption('yes');

        $name = $input->getArgument('name');
        $description = $input->getArgument('description');

        if ($interactive || !$name) {
            $io->section('Release Information');

            if (!$name) {
                $nameQuestion = new Question('Release name (e.g., "v1.2.0", "Sprint 42"): ');
                $nameQuestion->setValidator(function ($answer) {
                    if (empty(trim($answer))) {
                        throw new \RuntimeException('Release name cannot be empty.');
                    }
                    return $answer;
                });
                $name = $io->askQuestion($nameQuestion);
            }

            if (!$description) {
                $descQuestion = new Question('Description (optional, press Enter to skip): ', null);
                $description = $io->askQuestion($descQuestion);
            }
        }

        if (!$name) {
            $io->error('Release name is required. Use --interactive or provide name as argument.');
            return Command::FAILURE;
        }

        $io->section('Creating Snapshot');
        $io->definitionList(
            ['Release Name' => $name],
            ['Description' => $description ?: '<fg=gray>None</>'],
        );

        if (!$skipConfirm) {
            $confirmQuestion = new ConfirmationQuestion(
                'Do you want to create this snapshot? (yes/no) [yes]: ',
                true
            );

            if (!$io->askQuestion($confirmQuestion)) {
                $io->warning('Snapshot creation cancelled.');
                return Command::SUCCESS;
            }
        }

        $io->newLine();
        $io->text('Analyzing schema and creating snapshots...');
        $progressBar = $io->createProgressBar();
        $progressBar->start();

        try {
            $result = $this->snapshotService->createRelease($name, $description);
            $progressBar->finish();
            $io->newLine(2);

            if (!$result['ok']) {
                $io->error('Failed to create snapshot.');
                return Command::FAILURE;
            }

            $this->displayResults($io, $result);

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $progressBar->finish();
            $io->newLine(2);
            $io->error('An error occurred: ' . $e->getMessage());

            if ($output->isVerbose()) {
                $io->block($e->getTraceAsString(), 'TRACE', 'fg=white;bg=red', ' ', true);
            }

            return Command::FAILURE;
        }
    }

    private function displayResults(SymfonyStyle $io, array $result): void
    {
        $io->success('Snapshot created successfully!');

        $io->section('Summary');
        $io->definitionList(
            ['Release ID' => sprintf('#%s', $result['release_id'])],
            ['Release Name' => $result['release_name']],
            ['Total Snapshots' => $result['snapshots']],
            ['Entities Added' => sprintf('<fg=%s>%d</>', $result['count_added'] > 0 ? 'green' : 'gray', $result['count_added'])],
            ['Entities Changed' => sprintf('<fg=%s>%d</>', $result['count_changed'] > 0 ? 'yellow' : 'gray', $result['count_changed'])],
        );

        if ($result['count_added'] > 0) {
            $io->section(sprintf('New Entities (%d)', $result['count_added']));
            $addedList = array_map(fn($fqcn) => $this->getShortName($fqcn), $result['added']);
            $io->listing($addedList);
        }

        if ($result['count_changed'] > 0) {
            $io->section(sprintf('Modified Entities (%d)', $result['count_changed']));
            $changedList = array_map(fn($fqcn) => $this->getShortName($fqcn), $result['changed']);
            $io->listing($changedList);
        }

        if ($result['count_added'] === 0 && $result['count_changed'] === 0) {
            $io->note('No schema changes detected since the last snapshot.');
        }

        $io->newLine();
        $io->text([
            sprintf('View this release in the web interface at: <href=%s>%s</>', '/schema-doc/', '/schema-doc/'),
            '',
            'You can also:',
            '  • Compare this snapshot with previous releases',
            '  • Add comments to document changes',
            '  • Track entity evolution over time',
        ]);
    }

    private function getShortName(string $fqcn): string
    {
        return substr($fqcn, strrpos($fqcn, '\\') + 1);
    }
}
