<?php

namespace App\Service;

use App\Entity\AppConfig;
use App\Repository\AppConfigRepository;
use Doctrine\ORM\EntityManagerInterface;

class ConfigurationService
{
    public function __construct(
        private AppConfigRepository $configRepository,
        private EntityManagerInterface $entityManager
    ) {
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $config = $this->configRepository->findOneByKey($key);
        return $config ? $config->getConfigValue() : $default;
    }

    public function set(string $key, ?string $value, ?string $group = null): void
    {
        $config = $this->configRepository->findOneByKey($key);
        if (!$config) {
            $config = new AppConfig();
            $config->setConfigKey($key);
            $this->entityManager->persist($config);
        }

        $config->setConfigValue($value);
        if ($group) {
            $config->setConfigGroup($group);
        }

        $this->entityManager->flush();
    }
}
