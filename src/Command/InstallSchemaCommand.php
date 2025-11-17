<?php

namespace Qd\SchemaBundle\Command;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Qd\SchemaBundle\Entity\Comment;
use Qd\SchemaBundle\Entity\EntityAlias;
use Qd\SchemaBundle\Entity\Release;
use Qd\SchemaBundle\Entity\Snapshot;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'qd:schema:install',
    description: 'Creates the QD Schema tables if they do not already exist (without modifying business tables).'
)]
final class InstallSchemaCommand extends Command
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Show the SQL statements without executing them.')
            ->addOption('force', null, InputOption::VALUE_NONE, 'Execute the SQL statements to create the tables.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io   = new SymfonyStyle($input, $output);
        $conn = $this->em->getConnection();
        $sm   = $conn->createSchemaManager();

        $existing = array_map('strtolower', $sm->listTableNames());
        $toCreateMetas = [];

        foreach ([Comment::class, Snapshot::class, Release::class, EntityAlias::class] as $class) {
            $meta = $this->em->getClassMetadata($class);
            if (!in_array(strtolower($meta->getTableName()), $existing, true)) {
                $toCreateMetas[] = $meta;
            }
        }

        if (!$toCreateMetas) {
            $io->success('All QD Schema tables already exist. No action required.');
            return Command::SUCCESS;
        }

        $tool = new SchemaTool($this->em);
        $sqls = $tool->getCreateSchemaSql($toCreateMetas);

        if ($input->getOption('dry-run') && !$input->getOption('force')) {
            $io->section('SQL statements to execute');
            foreach ($sqls as $sql) {
                $io->writeln($sql . ';');
            }
            $io->note('Run this command with --force to execute the statements.');
            return Command::SUCCESS;
        }

        if (!$input->getOption('force')) {
            $io->warning('No changes have been applied. Use the --force option to create the tables.');
            return Command::INVALID;
        }

        $io->section('Creating missing QD Schema tables...');
        $tool->createSchema($toCreateMetas);
        $io->success('QD Schema tables created successfully.');

        return Command::SUCCESS;
    }
}
