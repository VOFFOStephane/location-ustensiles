<?php

namespace App\Command;

use App\Repository\ReservationDateChangeRequestRepository;
use App\Service\ChangeRequestApplier;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'app:change-request:approve',
    description: 'Approuve une demande de modification de date (applique les dates + recalculs).'
)]
class ApproveChangeRequestCommand extends Command
{
    public function __construct(
        private readonly ReservationDateChangeRequestRepository $repo,
        private readonly ChangeRequestApplier $applier
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('id', InputArgument::REQUIRED, 'ID de la demande (ReservationDateChangeRequest)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $id = (int) $input->getArgument('id');

        $req = $this->repo->find($id);
        if (!$req) {
            $output->writeln("<error>Demande #$id introuvable.</error>");
            return Command::FAILURE;
        }

        try {
            $this->applier->approve($req);
            $output->writeln("<info>OK: demande #$id APPROVED, réservation mise à jour.</info>");
            return Command::SUCCESS;
        } catch (\Throwable $e) {
            $output->writeln("<error>Erreur: {$e->getMessage()}</error>");
            return Command::FAILURE;
        }
    }
}
