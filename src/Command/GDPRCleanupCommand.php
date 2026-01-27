<?php

namespace App\Command;

use App\Document\AccessLog;
use App\Entity\Contact;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Registry;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ODM\MongoDB\DocumentManager;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:gdpr:cleanup',
    description: 'Performs GDPR-related data cleanup and anonymization',
)]
class GDPRCleanupCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private DocumentManager $documentManager
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Show what would be done without making changes')
            ->addOption('ip-retention', null, InputOption::VALUE_REQUIRED, 'Days to keep full IP addresses', 30)
            ->addOption('user-retention', null, InputOption::VALUE_REQUIRED, 'Years to keep inactive users', 2);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $dryRun = $input->getOption('dry-run');
        $ipRetentionDays = (int) $input->getOption('ip-retention');
        $userRetentionYears = (int) $input->getOption('user-retention');

        $io->title('GDPR Data Cleanup');

        if ($dryRun) {
            $io->note('DRY RUN: No changes will be persisted.');
        }

        // 1. IP Anonymization for AccessLogs
        $this->anonymizeAccessLogIps($io, $ipRetentionDays, $dryRun);

        // 2. IP Anonymization for Contacts
        $this->anonymizeContactIps($io, $ipRetentionDays, $dryRun);

        // 3. Inactive User Deletion
        $this->cleanupInactiveUsers($io, $userRetentionYears, $dryRun);

        $io->success('GDPR cleanup completed.');

        return Command::SUCCESS;
    }

    private function anonymizeAccessLogIps(SymfonyStyle $io, int $days, bool $dryRun): void
    {
        $cutoffDate = new \DateTime("-{$days} days");
        $io->section(sprintf('Anonymizing AccessLog IPs older than %d days (%s)', $days, $cutoffDate->format('Y-m-d')));

        $qb = $this->documentManager->createQueryBuilder(AccessLog::class);
        $logs = $qb->field('timestamp')->lt($cutoffDate)
            ->field('ipAddress')->notEqual('anonymized')
            ->getQuery()
            ->execute();

        $count = 0;
        foreach ($logs as $log) {
            if (!$dryRun) {
                // Simplistic anonymization: replace with partial mask or constant
                // In a real scenario, we might want to keep the first octets
                $log->setIpAddress('0.0.0.0 (anonymized)');
            }
            $count++;
        }

        if (!$dryRun && $count > 0) {
            $this->documentManager->flush();
        }

        $io->writeln(sprintf('Processed %d access logs.', $count));
    }

    private function anonymizeContactIps(SymfonyStyle $io, int $days, bool $dryRun): void
    {
        $cutoffDate = new \DateTimeImmutable("-{$days} days");
        $io->section(sprintf('Anonymizing Contact IPs older than %d days (%s)', $days, $cutoffDate->format('Y-m-d')));

        $contacts = $this->entityManager->getRepository(Contact::class)
            ->createQueryBuilder('c')
            ->where('c.createdAt < :cutoff')
            ->andWhere('c.ipAddress IS NOT NULL')
            ->andWhere('c.ipAddress != :anon')
            ->setParameter('cutoff', $cutoffDate)
            ->setParameter('anon', 'anonymized')
            ->getQuery()
            ->getResult();

        $count = 0;
        foreach ($contacts as $contact) {
            if (!$dryRun) {
                $contact->setIpAddress('anonymized');
            }
            $count++;
        }

        if (!$dryRun && $count > 0) {
            $this->entityManager->flush();
        }

        $io->writeln(sprintf('Processed %d contact records.', $count));
    }

    private function cleanupInactiveUsers(SymfonyStyle $io, int $years, bool $dryRun): void
    {
        $cutoffDate = new \DateTime("-{$years} years");
        $io->section(sprintf('Cleaning up inactive users (no login since %s)', $cutoffDate->format('Y-m-d')));

        // Users who haven't logged in for X years
        // OR users who never logged in and were created more than X years ago
        $qb = $this->entityManager->getRepository(User::class)->createQueryBuilder('u');
        $inactiveUsers = $qb->where($qb->expr()->orX(
                $qb->expr()->lt('u.lastLoginAt', ':cutoff'),
                $qb->expr()->andX(
                    $qb->expr()->isNull('u.lastLoginAt'),
                    $qb->expr()->lt('u.createdAt', ':cutoff')
                )
            ))
            ->setParameter('cutoff', $cutoffDate)
            ->getQuery()
            ->getResult();

        $count = 0;
        foreach ($inactiveUsers as $user) {
            $io->writeln(sprintf(' - Deleting user: %s (Last login: %s)', 
                $user->getUserIdentifier(), 
                $user->getLastLoginAt() ? $user->getLastLoginAt()->format('Y-m-d') : 'Never'
            ));
            if (!$dryRun) {
                $this->entityManager->remove($user);
            }
            $count++;
        }

        if (!$dryRun && $count > 0) {
            $this->entityManager->flush();
        }

        $io->writeln(sprintf('Deleted %d inactive users.', $count));
    }
}
