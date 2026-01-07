<?php

namespace App\Entity;

use App\Repository\AppConfigRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: AppConfigRepository::class)]
class AppConfig
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255, unique: true)]
    private ?string $configKey = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $configValue = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $configGroup = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getConfigKey(): ?string
    {
        return $this->configKey;
    }

    public function setConfigKey(string $configKey): static
    {
        $this->configKey = $configKey;

        return $this;
    }

    public function getConfigValue(): ?string
    {
        return $this->configValue;
    }

    public function setConfigValue(?string $configValue): static
    {
        $this->configValue = $configValue;

        return $this;
    }

    public function getConfigGroup(): ?string
    {
        return $this->configGroup;
    }

    public function setConfigGroup(?string $configGroup): static
    {
        $this->configGroup = $configGroup;

        return $this;
    }
}
