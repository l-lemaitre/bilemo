<?php

namespace App\Repository;

use App\Entity\Customer;
use App\Entity\Product;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Product>
 *
 * @method Product|null find($id, $lockMode = null, $lockVersion = null)
 * @method Product|null findOneBy(array $criteria, array $orderBy = null)
 * @method Product[]    findAll()
 * @method Product[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ProductRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Product::class);
    }

    public function save(Product $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Product $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function getProductsCustomerWithPagination(Customer $id, int $page, int $limit) {
        $queryBuilder = $this->createQueryBuilder('p')
            ->where('p.customer = :id')
            ->setParameter('id', $id)
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit);
        return $queryBuilder->getQuery()->getResult();
    }

    public function getProductCustomer(int $id, Customer $customer): ?Product
    {
        $queryBuilder = $this->createQueryBuilder('p')
            ->where('p.id = :id')
            ->andWhere('p.customer = :customer_id')
            ->setParameter('id', $id)
            ->setParameter('customer_id', $customer);
        return $queryBuilder->getQuery()->setMaxResults(1)->getOneOrNullResult();
    }
}
