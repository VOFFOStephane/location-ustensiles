<?php

namespace App\DataFixtures;

use App\Entity\Category;
use App\Entity\Product;
use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Faker\Factory;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class AppFixtures extends Fixture
{
    public function __construct(
        private readonly UserPasswordHasherInterface $passwordHasher
    ) {}

    public function load(ObjectManager $manager): void
    {
        $faker = Factory::create('fr_FR');
        $now = new \DateTimeImmutable();

        // --- USERS ---
        $admin = new User();
        $admin->setEmail('admin@test.com');
        $admin->setFirstname('Admin');
        $admin->setLastname('Test');
        $admin->setRoles(['ROLE_ADMIN']);
        $admin->setPassword($this->passwordHasher->hashPassword($admin, 'Password123!'));
        $manager->persist($admin);

        $client = new User();
        $client->setEmail('client@test.com');
        $client->setFirstname('Client');
        $client->setLastname('Test');
        $client->setRoles(['ROLE_USER']);
        $client->setIsVerified(true);
        $client->setPassword($this->passwordHasher->hashPassword($client, 'Password123'));
        $manager->persist($client);

        $client2 = new User();
        $client2->setEmail('client@test2.com');
        $client2->setFirstname('Client2');
        $client2->setLastname('Test2');
        $client2->setRoles(['ROLE_USER']);
        $client2->setIsVerified(true);
        $client2->setPassword($this->passwordHasher->hashPassword($client2, 'Password123'));
        $manager->persist($client2);

        // --- CATEGORIES ---
        $cat1 = new Category();
        $cat1->setName('Cuisson');
        $cat1->setDescription('Ustensiles de cuisson');
        $cat1->setIsDisabled(false);
        $cat1->setCreatedAt($now);
        $cat1->setUpdatedAt($now);
        $manager->persist($cat1);

        $cat2 = new Category();
        $cat2->setName('Service');
        $cat2->setDescription('Ustensiles pour service traiteur');
        $cat2->setIsDisabled(false);
        $cat2->setCreatedAt($now);
        $cat2->setUpdatedAt($now);
        $manager->persist($cat2);

        // on prépare la liste de catégories utilisables pour Faker (inclut les 2 ci-dessus)
        $categories = [$cat1, $cat2];

        // 2 autres catégories (pour arriver à 4)
        foreach (['Préparation', 'Électroménager'] as $name) {
            $cat = new Category();
            $cat->setName($name);
            $cat->setDescription($faker->sentence(10));
            $cat->setIsDisabled(false);
            $cat->setCreatedAt($now);
            $cat->setUpdatedAt($now);

            $manager->persist($cat);
            $categories[] = $cat;
        }

        // --- PRODUCTS (2 manuels) ---
        $p1 = new Product();
        $p1->setName('Cuillère');
        $p1->setDescription('Cuillère en inox');
        $p1->setPricePerDay('2.50');
        $p1->setDepositUnit('1.00');
        $p1->setQuantityTotal(200);
        $p1->setIsDisabled(false);
        $p1->setCategory($cat1);
        $manager->persist($p1);

        $p2 = new Product();
        $p2->setName('Assiette');
        $p2->setDescription('Assiette blanche');
        $p2->setPricePerDay('3.00');
        $p2->setDepositUnit('2.00');
        $p2->setQuantityTotal(100);
        $p2->setIsDisabled(false);
        $p2->setCategory($cat2);
        $manager->persist($p2);

        // --- PRODUCTS (10 faker) ---
        for ($i = 0; $i < 10; $i++) {
            $product = new Product();
            $cat = $faker->randomElement($categories);

            $product->setName($faker->words(3, true) . ' - ' . $cat->getName());
            $product->setDescription($faker->paragraph(3));

            $pricePerDay = number_format($faker->randomFloat(2, 5, 150), 2, '.', '');
            $depositUnit = number_format($faker->randomFloat(2, 0, 80), 2, '.', '');

            $product->setPricePerDay($pricePerDay);
            $product->setDepositUnit($depositUnit);
            $product->setQuantityTotal($faker->numberBetween(1, 30));
            $product->setIsDisabled(false);
            $product->setCategory($cat);

            $manager->persist($product);
        }

        $manager->flush();
    }
}
