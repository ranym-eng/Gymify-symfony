<?php

namespace App\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Process\Process;

class GenerateEntitiesCommand extends Command
{
    protected static $defaultName = 'app:generate:entities';

    public function __construct()
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setDescription('🛠 Générer les entités Doctrine depuis la base de données')
            ->setHelp('Cette commande importe les tables de la base de données comme entités (annotation) et génère les getters/setters.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('🚀 Génération des entités depuis la base de données');

        // Étape 1 : Importation du schéma de la base de données
        $importProcess = new Process([
            'php', 'bin/console',
            'doctrine:mapping:import',
            'App\\Entity',
            'annotation',
            '--path=src/Entity'
        ]);

        $importProcess->run();

        if (!$importProcess->isSuccessful()) {
            $io->error("❌ Erreur lors de l'importation des entités:");
            $io->writeln($importProcess->getErrorOutput());
            return Command::FAILURE;
        }

        $io->success("✅ Entités importées avec succès !");
        $io->writeln($importProcess->getOutput());

        // Étape 2 : Génération des getters/setters
        $generateProcess = new Process([
            'php', 'bin/console',
            'make:entity',
            '--regenerate'
        ]);

        $generateProcess->run();

        if (!$generateProcess->isSuccessful()) {
            $io->warning("⚠️ Les entités ont été importées, mais la génération des méthodes a échoué.");
            $io->writeln($generateProcess->getErrorOutput());
            return Command::SUCCESS;
        }

        $io->success("🚀 Méthodes (getters/setters) générées avec succès !");

        return Command::SUCCESS;
    }
}
