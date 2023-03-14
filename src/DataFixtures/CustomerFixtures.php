<?php

namespace App\DataFixtures;

use App\Entity\Customer;
use DateTimeImmutable;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class CustomerFixtures extends Fixture
{
    private string $timezone;

    private DateTimeImmutable $currentDate;

    public const CUSTOMERS = [
        [
            'name' => 'Customer 1'
        ]
    ];

    public const CUSTOMERS_REFERENCE = 'customers';

    public function __construct(string $timezone)
    {
        $this->timezone = $timezone;

        date_default_timezone_set($this->timezone);
        $this->currentDate = new DateTimeImmutable();
    }

    public function load(ObjectManager $manager): void
    {
        $customerEntity = new Customer();

        foreach (self::CUSTOMERS as $customer) {
            $customerEntity->setName($customer['name']);
            $customerEntity->setDateAdd($this->currentDate);
            $manager->persist($customerEntity);
        }

        $manager->flush();

        $this->addReference(self::CUSTOMERS_REFERENCE, $customerEntity);
    }
}
