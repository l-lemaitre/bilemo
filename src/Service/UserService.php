<?php

namespace App\Service;

use App\Dto\EditUser;
use App\Entity\Customer;
use App\Entity\User;
use DateTimeImmutable;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class UserService
{
    private ManagerRegistry $doctrine;

    private UserPasswordHasherInterface $userPasswordHasher;

    private string $timezone;

    private DateTimeImmutable $currentDate;

    public function __construct(ManagerRegistry $doctrine, UserPasswordHasherInterface $userPasswordHasher, string $timezone)
    {
        $this->doctrine = $doctrine;

        $this->userPasswordHasher = $userPasswordHasher;

        $this->timezone = $timezone;

        date_default_timezone_set($this->timezone);
        $this->currentDate = new DateTimeImmutable();
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

        $entityManager = $this->doctrine->getManager();
        $entityManager->persist($user);
        $entityManager->flush();

        return $user;
    }

    public function bindUser(User $user, Customer $customer): User
    {
        $user->setCustomer($customer);

        $entityManager = $this->doctrine->getManager();
        $entityManager->persist($user);
        $entityManager->flush();

        return $user;
    }

    public function unbindUser(User $user): User
    {
        $user->setCustomer(null);

        $entityManager = $this->doctrine->getManager();
        $entityManager->persist($user);
        $entityManager->flush();

        return $user;
    }

    public function editUser(User $user, EditUser $editUserDto): User
    {
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

        $entityManager = $this->doctrine->getManager();
        $entityManager->persist($user);
        $entityManager->flush();

        return $user;
    }

    public function removeUser(User $user): void
    {
        $entityManager = $this->doctrine->getManager();
        $entityManager->remove($user);
        $entityManager->flush();
    }
}
