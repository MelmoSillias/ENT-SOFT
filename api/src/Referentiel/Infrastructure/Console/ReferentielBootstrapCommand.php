<?php

namespace App\Referentiel\Infrastructure\Console;

use App\Referentiel\Application\Service\ReferentielBootstrapService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:referentiel:bootstrap',
    description: 'Charge le référentiel hardcodé (devises, pays, settings) sans écraser les données existantes',
)]
final class ReferentielBootstrapCommand extends Command
{
    public function __construct(
        private readonly ReferentielBootstrapService $bootstrapService,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $this->bootstrapService->bootstrap();
        $io->success('Référentiel bootstrap terminé (upsert idempotent).');

        return Command::SUCCESS;
    }
}
