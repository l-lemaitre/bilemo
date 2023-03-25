<?php

namespace App\Service;

use App\Entity\Customer;
use App\Entity\Product;
use Doctrine\Persistence\ObjectManager;

class ProductService
{
    public function addProduct(ObjectManager $entityManager, Product $product, Customer $customer, \Datetime $currentDate, bool $edit = false): ?Product
    {
        $product->setCustomer($customer);
        $product->setDateAdd($currentDate);

        $entityManager->persist($product);
        $entityManager->flush();

        if ($edit) {
            return null;
        } else {
            return $product;
        }
    }

    public function removeProduct(ObjectManager $entityManager, Product $product): void
    {
        $entityManager->remove($product);
        $entityManager->flush();
    }
}
