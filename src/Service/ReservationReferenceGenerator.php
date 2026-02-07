<?php

namespace App\Service;

use App\Repository\ReservationRepository;
use Random\RandomException;

final class ReservationReferenceGenerator
{
    public function __construct(private readonly ReservationRepository $reservationRepository) {}

    /**
     * @throws RandomException
     */
    public function generate(): string
    {
        $year = (new \DateTimeImmutable())->format('Y');

        // boucle anti-collision (ultra rare mais safe)
        for ($i = 0; $i < 10; $i++) {
            $suffix = str_pad((string) random_int(0, 99999), 5, '0', STR_PAD_LEFT);
            $ref = sprintf('RES-%s-%s', $year, $suffix);

            if (!$this->reservationRepository->findOneBy(['reference' => $ref])) {
                return $ref;
            }
        }

        throw new \RuntimeException('Impossible de générer une référence unique.');
    }
}

