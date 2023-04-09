<?php

namespace App\Repository;

use App\Entity\Customer;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\PasswordUpgraderInterface;

/**
 * @extends ServiceEntityRepository<User>
 *
 * @method User|null find($id, $lockMode = null, $lockVersion = null)
 * @method User|null findOneBy(array $criteria, array $orderBy = null)
 * @method User[]    findAll()
 * @method User[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class UserRepository extends ServiceEntityRepository implements PasswordUpgraderInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, User::class);
    }

    public function save(User $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(User $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Used to upgrade (rehash) the user's password automatically over time.
     */
    public function upgradePassword(PasswordAuthenticatedUserInterface $user, string $newHashedPassword): void
    {
        if (!$user instanceof User) {
            throw new UnsupportedUserException(sprintf('Instances of "%s" are not supported.', \get_class($user)));
        }

        $user->setPassword($newHashedPassword);

        $this->save($user, true);
    }

    public function getUserToBind(int $id): ?User
    {
        $queryBuilder = $this->createQueryBuilder('u')
            ->where('u.id = :id')
            ->andWhere('u.customer is NULL')
            ->setParameter('id', $id);
        return $queryBuilder->getQuery()->setMaxResults(1)->getOneOrNullResult();
    }

    public function getBindedUser(int $id, Customer $customer_id): ?User
    {
        $queryBuilder = $this->createQueryBuilder('u')
            ->where('u.id = :id')
            ->andWhere('u.customer = :customer_id')
            ->setParameter('id', $id)
            ->setParameter('customer_id', $customer_id);
        return $queryBuilder->getQuery()->setMaxResults(1)->getOneOrNullResult();
    }

    public function getUsersCustomerWithPagination(Customer $id, int $page, int $limit) {
        $queryBuilder = $this->createQueryBuilder('u')
            ->where('u.customer = :id')
            ->setParameter('id', $id)
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit);
        return $queryBuilder->getQuery()->getResult();
    }
}
