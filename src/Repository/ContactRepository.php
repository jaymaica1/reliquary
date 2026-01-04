<?php

namespace App\Repository;

use App\Entity\Contact;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Contact>
 */
class ContactRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Contact::class);
    }

    public function save(Contact $contact, bool $flush = false): void
    {
        $this->getEntityManager()->persist($contact);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Contact $contact, bool $flush = false): void
    {
        $this->getEntityManager()->remove($contact);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Find all contacts ordered by creation date (newest first)
     */
    public function findAllOrderedQuery(): \Doctrine\ORM\Query
    {
        return $this->createQueryBuilder('c')
            ->orderBy('c.createdAt', 'DESC')
            ->getQuery();
    }

    /**
     * Find contacts by status
     */
    public function findByStatusQuery(string $status): \Doctrine\ORM\Query
    {
        return $this->createQueryBuilder('c')
            ->where('c.status = :status')
            ->setParameter('status', $status)
            ->orderBy('c.createdAt', 'DESC')
            ->getQuery();
    }

    public function findAllOrdered(): array
    {
        return $this->findAllOrderedQuery()->getResult();
    }

    /**
     * Find contacts by status
     */
    public function findByStatus(string $status): array
    {
        return $this->findByStatusQuery($status)->getResult();
    }

    /**
     * Count unread contacts
     */
    public function countUnread(): int
    {
        return $this->createQueryBuilder('c')
            ->select('COUNT(c.id)')
            ->where('c.status = :status')
            ->setParameter('status', 'new')
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Get statistics
     */
    public function getStatistics(): array
    {
        return [
            'total' => $this->createQueryBuilder('c')->select('COUNT(c.id)')->getQuery()->getSingleScalarResult(),
            'new' => $this->countUnread(),
            'read' => $this->createQueryBuilder('c')->select('COUNT(c.id)')
                ->where('c.status = :status')
                ->setParameter('status', 'read')
                ->getQuery()
                ->getSingleScalarResult(),
            'resolved' => $this->createQueryBuilder('c')->select('COUNT(c.id)')
                ->where('c.status = :status')
                ->setParameter('status', 'resolved')
                ->getQuery()
                ->getSingleScalarResult(),
        ];
    }
}
