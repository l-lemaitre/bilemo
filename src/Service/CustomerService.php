<?php

namespace App\Service;

use App\Entity\Customer;
use DateTimeImmutable;
use Doctrine\Persistence\ObjectManager;

class CustomerService
{
    private function setCustomer(ObjectManager $entityManager, Customer $customer): Customer
    {
        date_default_timezone_set('Europe/Paris');
        $currentDate = new DateTimeImmutable();

        $customer->setDateAdd($currentDate);

        $entityManager->persist($customer);
        $entityManager->flush();

        return $customer;
    }

    public function addCustomer(ObjectManager $entityManager, Customer $customer): Customer
    {
        return $this->setCustomer($entityManager, $customer);
    }

    public function editCustomer(ObjectManager $entityManager, Customer $customer): void
    {
        $this->setCustomer($entityManager, $customer);
    }

    public function removeCustomer(ObjectManager $entityManager, Customer $customer): void
    {
        $entityManager->remove($customer);
        $entityManager->flush();
    }
}
