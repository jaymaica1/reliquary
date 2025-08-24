<?php

namespace App\Service;

use App\Enum\RelicStatus;
use App\Repository\RelicRepository;
use App\Repository\SaintRepository;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Finder\Finder;

class NotificationService
{
    public function __construct(
        private SaintRepository $saintRepository,
        private RelicRepository $relicRepository,
        private ParameterBagInterface $parameterBag
    ) {}

    public function getIncompleteSaintsCount(): int
    {
        return $this->saintRepository->countIncomplete();
    }

    public function getPendingRelicsCount(): int
    {
        return $this->relicRepository->countByStatus(RelicStatus::PENDING);
    }

    public function getErrorLogsCount(): int
    {
        $logDir = $this->parameterBag->get('kernel.logs_dir');
        $errorCount = 0;

        if (!file_exists($logDir)) {
            return 0;
        }

        $finder = new Finder();
        $finder->files()->in($logDir)->name('*.log');

        foreach ($finder as $file) {
            $content = file_get_contents($file->getRealPath());
            // Count ERROR and CRITICAL level log entries
            $errorCount += preg_match_all('/\.(ERROR|CRITICAL)\s/', $content);
        }

        return $errorCount;
    }

    public function getAllNotificationCounts(): array
    {
        return [
            'incompleteSaints' => $this->getIncompleteSaintsCount(),
            'pendingRelics' => $this->getPendingRelicsCount(),
            'errorLogs' => $this->getErrorLogsCount(),
        ];
    }
}