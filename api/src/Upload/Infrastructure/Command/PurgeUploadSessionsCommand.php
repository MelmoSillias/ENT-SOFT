<?php

namespace App\Upload\Infrastructure\Command;

use App\Upload\Application\Service\UploadSessionService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:uploads:purge',
    description: 'Supprime les sessions d\'upload expirées (24 h) et leur staging.',
)]
final class PurgeUploadSessionsCommand extends Command
{
    public function __construct(private readonly UploadSessionService $uploads)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $count = $this->uploads->purgeExpired();
        $io->writeln(sprintf('%d session(s) d\'upload expirée(s) purgée(s).', $count));

        return Command::SUCCESS;
    }
}
