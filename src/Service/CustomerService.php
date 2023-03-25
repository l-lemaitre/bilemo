<?php

namespace App\Service;

use App\Entity\Customer;
use Doctrine\Persistence\ObjectManager;

class CustomerService
{
    public function addCustomer(ObjectManager $entityManager, Customer $customer, \Datetime $currentDate, bool $edit = false): ?Customer
    {
        $customer->setDateAdd($currentDate);

        $entityManager->persist($customer);
        $entityManager->flush();

        if ($edit) {
            return null;
        } else {
            return $customer;
        }
    }

    public function removeCustomer(ObjectManager $entityManager, Customer $customer): void
    {
        $entityManager->remove($customer);
        $entityManager->flush();
    }
}
