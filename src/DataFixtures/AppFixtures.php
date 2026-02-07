<?php

namespace App\DataFixtures;

use App\Entity\Category;
use App\Entity\Product;
use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class AppFixtures extends Fixture
{
    public function __construct(
        private readonly UserPasswordHasherInterface $passwordHasher
    ) {}

    public function load(ObjectManager $manager): void
    {
        // --- USERS ---
        $admin = new User();
        $admin->setEmail('admin@test.com');
        $admin->setFirstname('Admin');
        $admin->setLastname('Test');
        $admin->setRoles(['ROLE_ADMIN']);

        $admin->setPassword(
            $this->passwordHasher->hashPassword($admin, 'Password123!')
        );

        $client = new User();
        $client->setEmail('client@test.com');
        $client->setFirstname('Client');
        $client->setLastname('Test');
        $client->setRoles(['ROLE_USER']);

        $client->setPassword(
            $this->passwordHasher->hashPassword($client, 'Password123')
        );

        $client2 = new User();
        $client2->setEmail('client@test2.com');
        $client2->setFirstname('Client2');
        $client2->setLastname('Test2');
        $client2->setRoles(['ROLE_USER']);

        $client2->setPassword(
            $this->passwordHasher->hashPassword($client2, 'Password123')
        );

        $manager->persist($admin);
        $manager->persist($client);
        $manager->persist($client2);

        // --- CATEGORIES ---
        $cat1 = new Category();
        $cat1->setName('Cuisson');
        $cat1->setDescription('Ustensiles de cuisson');
        $cat1->setIsDisabled(false);

        $cat2 = new Category();
        $cat2->setName('Service');
        $cat2->setDescription('Ustensiles pour service traiteur');
        $cat2->setIsDisabled(false);

        $manager->persist($cat1);
        $manager->persist($cat2);

        // --- PRODUCTS ---
        $p1 = new Product();
        $p1->setName('Cuillère');
        $p1->setDescription('Cuillère en inox');
        $p1->setPricePerDay('2.50');
        $p1->setDepositUnit('1.00');
        $p1->setQuantityTotal(200);
        $p1->setIsDisabled(false);
        $p1->setCategory($cat1);

        $p2 = new Product();
        $p2->setName('Assiette');
        $p2->setDescription('Assiette blanche');
        $p2->setPricePerDay('3.00');
        $p2->setDepositUnit('2.00');
        $p2->setQuantityTotal(100);
        $p2->setIsDisabled(false);
        $p2->setCategory($cat2);

        $manager->persist($p1);
        $manager->persist($p2);

        $manager->flush();
    }
}
