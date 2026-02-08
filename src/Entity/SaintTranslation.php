<?php

namespace App\Entity;

use App\Repository\SaintTranslationRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: SaintTranslationRepository::class)]
class SaintTranslation
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'translations')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Saint $saint = null;

    #[ORM\Column(length: 5)]
    private ?string $locale = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $name = null;

    #[ORM\Column(type: "text", nullable: true)]
    private ?string $saintPhrase = null;

    #[ORM\Column(type: "text", nullable: true)]
    private ?string $biography = null;

    #[ORM\Column(type: "text", nullable: true)]
    private ?string $abstract = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSaint(): ?Saint
    {
        return $this->saint;
    }

    public function setSaint(?Saint $saint): static
    {
        $this->saint = $saint;

        return $this;
    }

    public function getLocale(): ?string
    {
        return $this->locale;
    }

    public function setLocale(string $locale): static
    {
        $this->locale = $locale;

        return $this;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getSaintPhrase(): ?string
    {
        return $this->saintPhrase;
    }

    public function setSaintPhrase(?string $saintPhrase): static
    {
        $this->saintPhrase = $saintPhrase;

        return $this;
    }

    public function getBiography(): ?string
    {
        return $this->biography;
    }

    public function setBiography(?string $biography): static
    {
        $this->biography = $biography;

        return $this;
    }

    public function getAbstract(): ?string
    {
        return $this->abstract;
    }

    public function setAbstract(?string $abstract): static
    {
        $this->abstract = $abstract;

        return $this;
    }
}