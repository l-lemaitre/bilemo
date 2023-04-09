<?php

namespace App\DataFixtures;

use App\Entity\User;
use DateTimeImmutable;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class UserFixtures extends Fixture
{
    private UserPasswordHasherInterface $userPasswordHasher;

    private string $timezone;

    private DateTimeImmutable $currentDate;

    public const USERS = [
        [
            'email' => 'contact@llemaitre.com',
            'roles' => ["ROLE_ADMIN"],
            'password' => 'admin_55'
        ],
        [
            'email' => 'ludoviclemaitre@orange.fr',
            'roles' => ["ROLE_USER"],
            'password' => 'user_5_5'
        ]
    ];

    public function __construct(UserPasswordHasherInterface $userPasswordHasher, string $timezone)
    {
        $this->userPasswordHasher = $userPasswordHasher;

        $this->timezone = $timezone;

        date_default_timezone_set($this->timezone);
        $this->currentDate = new DateTimeImmutable();
    }

    public function load(ObjectManager $manager): void
    {
        foreach (self::USERS as $user) {
            $customerFixtures = $this->getReference(CustomerFixtures::CUSTOMERS_REFERENCE);

            $userEntity = new User();
            $userEntity->setCustomer($customerFixtures);
            $userEntity->setEmail($user['email']);
            $userEntity->setRoles($user['roles']);
            $userEntity->setPassword(
                $this->userPasswordHasher->hashPassword(
                    $userEntity,
                    $user['password']
                )
            );
            $userEntity->setRegistrationDate($this->currentDate);
            $manager->persist($userEntity);
        }

        $manager->flush();
    }

    public function getDependencies(): array
    {
        return [
            CustomerFixtures::class
        ];
    }
}
