<?php

namespace App\Service;

use App\Entity\Customer;
use App\Entity\Product;
use DateTimeImmutable;
use Doctrine\Persistence\ObjectManager;

class ProductService
{
    public function setProduct(ObjectManager $entityManager, Product $product, Customer $customer): Product
    {
        date_default_timezone_set('Europe/Paris');
        $currentDate = new DateTimeImmutable();

        $product->setCustomer($customer);
        $product->setDateAdd($currentDate);

        $entityManager->persist($product);
        $entityManager->flush();

        return $product;
    }

    public function addProduct(ObjectManager $entityManager, Product $product, Customer $customer): Product
    {
        return $this->setProduct($entityManager, $product, $customer);
    }

    public function editProduct(ObjectManager $entityManager, Product $product, Customer $customer): void
    {
        $this->setProduct($entityManager, $product, $customer);
    }

    public function removeProduct(ObjectManager $entityManager, Product $product): void
    {
        $entityManager->remove($product);
        $entityManager->flush();
    }
}
