<?php

namespace App\Service;

use App\Dto\EditProduct;
use App\Entity\Customer;
use App\Entity\Product;
use DateTimeImmutable;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

class ProductService
{
    private ManagerRegistry $doctrine;

    private TagAwareCacheInterface $cache;

    public function __construct(ManagerRegistry $doctrine, TagAwareCacheInterface $cache)
    {
        $this->doctrine = $doctrine;
        $this->cache = $cache;
    }

    private function setProduct(Product $product): Product
    {
        $entityManager = $this->doctrine->getManager();
        $entityManager->persist($product);
        $entityManager->flush();

        return $product;
    }

    public function addProduct(Product $product, Customer $customer): Product
    {
        $this->cache->invalidateTags(['productsCache']);

        $product->setCustomer($customer);

        date_default_timezone_set('Europe/Paris');
        $currentDate = new DateTimeImmutable();
        $product->setDateAdd($currentDate);

        return $this->setProduct($product);
    }

    public function editProduct(Product $product, EditProduct $editProductDto): Product
    {
        $this->cache->invalidateTags(['productsCache', 'productsCache-' . $product->getId()]);

        $product->setName($editProductDto->getName());
        $product->setPrice($editProductDto->getPrice());
        if (trim($editProductDto->getDescription())) {
            $product->setDescription($editProductDto->getDescription());
        }

        return $this->setProduct($product);
    }

    public function removeProduct(Product $product): void
    {
        $this->cache->invalidateTags(['productsCache', 'productsCache-' . $product->getId()]);

        $entityManager = $this->doctrine->getManager();
        $entityManager->remove($product);
        $entityManager->flush();
    }
}
