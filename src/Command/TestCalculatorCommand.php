<?php

namespace App\Command;

use App\Entity\Category;
use App\Entity\Product;
use App\Entity\Reservation;
use App\Entity\ReservationItem;
use App\Service\ReservationCalculator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'app:test-calculator',
    description: 'Test du ReservationCalculator (prix, durée, caution) sans UI',
)]
class TestCalculatorCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly ReservationCalculator $calculator
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        // 1) Créer une catégorie + un produit (test)
        $category = new Category();
        $category->setName('Test');
        $category->setDescription('Catégorie test');

        $product = new Product();
        $product->setName('Cuillère');
        $product->setDescription('Cuillère test');
        $product->setPricePerDay('2.50');
        $product->setDepositUnit('1.00');
        $product->setQuantityTotal(100);
        $product->setCategory($category);

        // 2) Créer une réservation + item
        $reservation = new Reservation();
        $reservation->setReference('RES-TEST-00001');
        $reservation->setStartDate(new \DateTimeImmutable('2026-01-20'));
        $reservation->setEndDate(new \DateTimeImmutable('2026-01-23')); // 3 jours si on calcule 20->23 = 3
        $reservation->setStatus(Reservation::STATUS_PENDING);

        // ⚠️ temporaire: on lie à un user existant
        $user = $this->em->getRepository(\App\Entity\User::class)->findOneBy([]);
        if (!$user) {
            $output->writeln('<error>Aucun user trouvé en base. Crée un user puis relance.</error>');
            return Command::FAILURE;
        }
        $reservation->setUser($user);

        $item = new ReservationItem();
        $item->setProduct($product);
        $item->setQuantity(20);

        $reservation->addItem($item);

        // 3) Calcul des totaux
        $this->calculator->computeReservationTotals($reservation);

        // 4) Affichage résultat
        $output->writeln('--- RESULTATS ---');
        $output->writeln('Jours: ' . $this->calculator->calculateDays($reservation->getStartDate(), $reservation->getEndDate()));
        $output->writeln('Rental total: ' . $reservation->getRentalTotal());
        $output->writeln('Deposit total: ' . $reservation->getDepositTotal());
        $output->writeln('ReturnDueDate: ' . $reservation->getReturnDueDate()?->format('Y-m-d'));

        $output->writeln('Item lineRentalTotal: ' . $item->getLineRentalTotal());
        $output->writeln('Item lineDepositTotal: ' . $item->getLineDepositTotal());

        // 5) (optionnel) persister en base pour vérifier
       // $this->em->persist($category);
       // $this->em->persist($product);
       // $this->em->persist($reservation);
        //$this->em->flush();

        $output->writeln('<info>OK: données test enregistrées.</info>');

        return Command::SUCCESS;
    }
}

