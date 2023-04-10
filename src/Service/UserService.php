<?php

namespace App\Service;

use App\Dto\EditUser;
use App\Entity\Customer;
use App\Entity\User;
use DateTimeImmutable;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

class UserService
{
    private ManagerRegistry $doctrine;

    private UserPasswordHasherInterface $userPasswordHasher;

    private string $timezone;

    private DateTimeImmutable $currentDate;

    private TagAwareCacheInterface $cache;

    public function __construct(ManagerRegistry $doctrine, UserPasswordHasherInterface $userPasswordHasher, string $timezone, TagAwareCacheInterface $cache)
    {
        $this->doctrine = $doctrine;

        $this->userPasswordHasher = $userPasswordHasher;

        $this->timezone = $timezone;
        date_default_timezone_set($this->timezone);
        $this->currentDate = new DateTimeImmutable();

        $this->cache = $cache;
    }

    private function setUser(User $user): User
    {
        $entityManager = $this->doctrine->getManager();
        $entityManager->persist($user);
        $entityManager->flush();

        return $user;
    }

    public function addUser(User $user): User
    {
        $user->setRoles(['ROLE_USER']);

        if (trim($user->getPassword())) {
            $user->setPassword(
                $this->userPasswordHasher->hashPassword(
                    $user,
                    $user->getPassword()
                )
            );
        }

        $user->setRegistrationDate($this->currentDate);

        return $this->setUser($user);
    }

    public function bindUser(User $user, Customer $customer): User
    {
        $this->cache->invalidateTags(['usersCache', 'userCache-' . $user->getId(), 'customersCache']);

        $user->setCustomer($customer);

        return $this->setUser($user);
    }

    public function unbindUser(User $user): User
    {
        $this->cache->invalidateTags(['usersCache', 'userCache-' . $user->getId(), 'customersCache']);

        $user->setCustomer(null);

        return $this->setUser($user);
    }

    public function editUser(User $user, EditUser $editUserDto): User
    {
        $this->cache->invalidateTags(['usersCache', 'userCache-' . $user->getId()]);

        $user->setEmail($editUserDto->getEmail());

        if (trim($editUserDto->getPassword())) {
            $user->setPassword(
                $this->userPasswordHasher->hashPassword(
                    $user,
                    $editUserDto->getPassword()
                )
            );
        }

        $user->setRegistrationDate($this->currentDate);

        return $this->setUser($user);
    }

    public function removeUser(User $user): void
    {
        $this->cache->invalidateTags(['usersCache', 'userCache-' . $user->getId()]);

        $entityManager = $this->doctrine->getManager();
        $entityManager->remove($user);
        $entityManager->flush();
    }
}
