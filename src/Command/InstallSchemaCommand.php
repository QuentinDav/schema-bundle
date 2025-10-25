<?php
namespace Qd\SchemaBundle\Command;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Qd\SchemaBundle\Entity\Comment;
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
    description: 'Crée les tables QD Schema si elles n’existent pas (sans toucher aux tables métier).'
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
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Affiche le SQL sans exécuter')
            ->addOption('force', null, InputOption::VALUE_NONE, 'Exécute le SQL (création)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io   = new SymfonyStyle($input, $output);
        $conn = $this->em->getConnection();
        $sm   = $conn->createSchemaManager();

        // 1) Tables cibles du bundle
        $wantedTables = [
            $this->em->getClassMetadata(Comment::class)->getTableName(),
            $this->em->getClassMetadata(Snapshot::class)->getTableName(),
            $this->em->getClassMetadata(Release::class)->getTableName(),
        ];

        // 2) On ne crée que ce qui manque
        $existing = array_map('strtolower', $sm->listTableNames());
        $toCreateMetas = [];
        foreach ([Comment::class, Snapshot::class, Release::class] as $class) {
            $meta = $this->em->getClassMetadata($class);
            if (!in_array(strtolower($meta->getTableName()), $existing, true)) {
                $toCreateMetas[] = $meta;
            }
        }

        if (!$toCreateMetas) {
            $io->success('✅ Les tables QD Schema existent déjà. Rien à faire.');
            return Command::SUCCESS;
        }

        $tool = new SchemaTool($this->em);
        $sqls = $tool->getCreateSchemaSql($toCreateMetas);

        if ($input->getOption('dry-run') && !$input->getOption('force')) {
            $io->section('SQL à exécuter');
            foreach ($sqls as $sql) {
                $io->writeln($sql.';');
            }
            $io->note('Exécute avec --force pour appliquer.');
            return Command::SUCCESS;
        }

        if (!$input->getOption('force')) {
            $io->warning('Aucune modification effectuée (ajoute --force pour créer les tables).');
            return Command::INVALID;
        }

        // 3) Exécuter
        $io->section('Création des tables manquantes…');
        $tool->createSchema($toCreateMetas);
        $io->success('✅ Tables QD Schema créées avec succès.');

        return Command::SUCCESS;
    }
}
