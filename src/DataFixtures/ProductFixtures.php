<?php

namespace App\DataFixtures;

use App\Entity\Product;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class ProductFixtures extends Fixture
{
    private string $timezone;

    public const PRODUCTS = [
        [
            'name' => 'Product 1',
            'price' => '100',
            'description' => 'Test product 1.'
        ],
        [
            'name' => 'Product 2',
            'price' => '120',
            'description' => 'Test product 2.'
        ]
    ];

    public function __construct(string $timezone)
    {
        $this->timezone = $timezone;

        date_default_timezone_set($this->timezone);
        $this->currentDate = new \DateTime();
    }

    public function load(ObjectManager $manager): void
    {
        foreach (self::PRODUCTS as $product) {
            $customerFixtures = $this->getReference(CustomerFixtures::CUSTOMERS_REFERENCE);

            $priceEntity = new Product();
            $priceEntity->setCustomer($customerFixtures);
            $priceEntity->setname($product['name']);
            $priceEntity->setPrice($product['price']);
            $priceEntity->setDescription($product['description']);
            $priceEntity->setDateAdd($this->currentDate);
            $manager->persist($priceEntity);
        }

        $manager->flush();
    }

    public function getDependencies()
    {
        return [
            CustomerFixtures::class
        ];
    }
}
