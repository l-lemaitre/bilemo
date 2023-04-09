<?php

namespace App\Service;

use App\Dto\EditProduct;
use App\Entity\Customer;
use App\Entity\Product;
use DateTimeImmutable;
use Doctrine\Persistence\ManagerRegistry;

class ProductService
{
    private ManagerRegistry $doctrine;

    public function __construct(ManagerRegistry $doctrine)
    {
        $this->doctrine = $doctrine;
    }

    private function setProduct(Product $product): Product
    {
        date_default_timezone_set('Europe/Paris');
        $currentDate = new DateTimeImmutable();

        $product->setDateAdd($currentDate);

        $entityManager = $this->doctrine->getManager();
        $entityManager->persist($product);
        $entityManager->flush();

        return $product;
    }

    public function addProduct(Product $product, Customer $customer): Product
    {
        $product->setCustomer($customer);

        return $this->setProduct($product);
    }

    public function editProduct(Product $product, EditProduct $editProductDto): Product
    {
        $product->setName($editProductDto->getName());
        $product->setPrice($editProductDto->getPrice());
        $product->setDescription($editProductDto->getDescription());

        return $this->setProduct($product);
    }

    public function removeProduct(Product $product): void
    {
        $entityManager = $this->doctrine->getManager();
        $entityManager->remove($product);
        $entityManager->flush();
    }
}
