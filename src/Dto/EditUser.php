<?php

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

class EditUser
{
    #[Assert\NotBlank(message: "L'adresse e-mail est obligatoire.")]
    #[Assert\Email(message: 'L\'e-mail {{ value }} n\'est pas un e-mail valide.')]
    private ?string $email = null;

    /**
     * @var string The hashed password
     */
    #[Assert\Length(
        min: 8,
        max: 60,
        minMessage: 'Votre mot de passe doit comporter au moins {{ limit }} caractères.',
        maxMessage: 'Votre mot de passe ne peut pas dépasser {{ limit }} caractères.'
    )]
    private ?string $password = null;

    /**
     * @return string|null
     */
    public function getEmail(): ?string
    {
        return $this->email;
    }

    /**
     * @param string|null $email
     */
    public function setEmail(?string $email): void
    {
        $this->email = $email;
    }

    /**
     * @return string|null
     */
    public function getPassword(): ?string
    {
        return $this->password;
    }

    /**
     * @param string|null $password
     */
    public function setPassword(?string $password): void
    {
        $this->password = $password;
    }
}