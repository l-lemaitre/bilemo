<?php

namespace App\Service;

use App\Entity\Customer;
use App\Entity\User;
use DateTimeImmutable;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class UserService
{
    private UserPasswordHasherInterface $userPasswordHasher;

    private string $timezone;

    private DateTimeImmutable $currentDate;

    public function __construct(UserPasswordHasherInterface $userPasswordHasher, string $timezone)
    {
        $this->userPasswordHasher = $userPasswordHasher;

        $this->timezone = $timezone;

        date_default_timezone_set($this->timezone);
        $this->currentDate = new DateTimeImmutable();
    }

    public function addUser(ObjectManager $entityManager, User $user): User
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

        $entityManager->persist($user);
        $entityManager->flush();

        return $user;
    }

    public function bindingUser(ObjectManager $entityManager, User $user, Customer $customer = null): void
    {
        $user->setCustomer($customer);

        $entityManager->persist($user);
        $entityManager->flush();
    }

    public function editUser(ObjectManager $entityManager, User $user, Customer $customer, string $currentPassword): void
    {
        $user->setCustomer($customer);

        $user->setRoles(['ROLE_USER']);

        if (trim($user->getPassword()) <> $currentPassword) {
            $user->setPassword(
                $this->userPasswordHasher->hashPassword(
                    $user,
                    $user->getPassword()
                )
            );
        }

        $user->setRegistrationDate($this->currentDate);

        $entityManager->persist($user);
        $entityManager->flush();
    }

    public function removeUser(ObjectManager $entityManager, User $user): void
    {
        $entityManager->remove($user);
        $entityManager->flush();
    }
}
