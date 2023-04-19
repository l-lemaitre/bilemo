<?php

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

class EditProduct
{
    #[Assert\NotBlank(message: "Le nom du produit est obligatoire.")]
    private ?string $name = null;

    #[Assert\NotBlank(message: "Le prix du produit est obligatoire.")]
    private ?string $price = null;

    private ?string $description = null;

    /**
     * @return string|null
     */
    public function getName(): ?string
    {
        return $this->name;
    }

    /**
     * @param string|null $name
     */
    public function setName(?string $name): void
    {
        $this->name = $name;
    }

    /**
     * @return string|null
     */
    public function getPrice(): ?string
    {
        return $this->price;
    }

    /**
     * @param string|null $price
     */
    public function setPrice(?string $price): void
    {
        $this->price = $price;
    }

    /**
     * @return string|null
     */
    public function getDescription(): ?string
    {
        return $this->description;
    }

    /**
     * @param string|null $description
     */
    public function setDescription(?string $description): void
    {
        $this->description = $description;
    }
}