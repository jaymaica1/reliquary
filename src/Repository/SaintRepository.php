<?php

namespace App\Repository;

use App\Entity\Saint;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\Query;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Saint>
 */
class SaintRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Saint::class);
    }
    
    /**
     * Find saints by feast date (month and day)
     * 
     * Using a native SQL query with PostgreSQL date functions to compare month and day
     * while setting a fixed year (2025) for both the feast_date and the reference date.
     * This approach avoids issues with Doctrine DQL's limited support for date functions.
     * 
     * @param \DateTimeInterface $date The date to match against feast dates
     * @return array Returns an array of Saint objects
     */
    public function findByFeastDate(\DateTimeInterface $date): array
    {
        // Create a fixed date with year 2025 for comparison
        $today = new \DateTime('2025-' . $date->format('m-d'));

        $queryBuilder = $this->createQueryBuilder('s')
            ->where('s.feast_date = :today')
            ->setParameter('today', $today)
            ->addOrderBy('s.canonical_status', 'DESC')
        ;

        
        return $queryBuilder->getQuery()->getResult();
    }

    //    /**
    //     * @return Saint[] Returns an array of Saint objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('s')
    //            ->andWhere('s.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('s.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?Saint
    //    {
    //        return $this->createQueryBuilder('s')
    //            ->andWhere('s.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }

    /**
     * Find all saints query with optional canonical status filter
     * 
     * @param string|null $canonicalStatus The canonical status to filter by
     * @param bool $includeIncomplete Whether to include incomplete saints
     * @return Query The query object
     */
    public function findAllQuery(?string $canonicalStatus = null, string $searchTerm = null, bool $includeIncomplete = false): Query
    {
        $queryBuilder = $this->createQueryBuilder('s');

        if ($searchTerm !== null) {
            $queryBuilder
                ->leftJoin('s.translations', 't')
                ->andWhere('LOWER(s.name) LIKE LOWER(:searchTerm) OR LOWER(t.name) LIKE LOWER(:searchTerm) OR LOWER(t.saintPhrase) LIKE LOWER(:searchTerm)')
                ->setParameter('searchTerm', '%' . $searchTerm . '%');
        }

        if (!$includeIncomplete) {
            $queryBuilder
                ->andWhere('s.is_incomplete = :incomplete')
                ->setParameter('incomplete', false);
        }

        if ($canonicalStatus) {
            $queryBuilder
                ->andWhere('s.canonical_status = :canonicalStatus')
                ->setParameter('canonicalStatus', $canonicalStatus);
        }

        return $queryBuilder->getQuery();
    }

    /**
     * Find saints created by a specific user with optional canonical status filter
     * 
     * @param object $user The user who created the saints
     * @param string|null $canonicalStatus The canonical status to filter by
     * @param bool $includeIncomplete Whether to include incomplete saints
     * @return Query The query object
     */
    public function findByCreatorQuery($user, ?string $canonicalStatus = null, bool $includeIncomplete = false, string $searchTerm = null): Query
    {
        $queryBuilder = $this->createQueryBuilder('s')
            ->where('s.creator = :user')
            ->setParameter('user', $user);
            
        if ($searchTerm !== null) {
            $queryBuilder
                ->leftJoin('s.translations', 't')
                ->andWhere('LOWER(s.name) LIKE LOWER(:searchTerm) OR LOWER(t.name) LIKE LOWER(:searchTerm) OR LOWER(t.saintPhrase) LIKE LOWER(:searchTerm)')
                ->setParameter('searchTerm', '%' . $searchTerm . '%');
        }

        if (!$includeIncomplete) {
            $queryBuilder
                ->andWhere('s.is_incomplete = :incomplete')
                ->setParameter('incomplete', false);
        }

        if ($canonicalStatus) {
            $queryBuilder
                ->andWhere('s.canonical_status = :canonicalStatus')
                ->setParameter('canonicalStatus', $canonicalStatus);
        }

        return $queryBuilder->getQuery();
    }

    /**
     * Find all incomplete saints query
     * 
     * @return Query The query object
     */
    public function findIncompleteQuery(): Query
    {
        $queryBuilder = $this->createQueryBuilder('s')
            ->andWhere('s.is_incomplete = :incomplete')
            ->setParameter('incomplete', true)
            ->orderBy('s.id', 'DESC');

        return $queryBuilder->getQuery();
    }

    /**
     * Find featured saints
     * 
     * @return Saint[] Returns an array of featured Saint objects
     */
    public function findFeatured(): array
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.featured = :featured')
            ->setParameter('featured', true)
            ->andWhere('s.is_incomplete = :incomplete')
            ->setParameter('incomplete', false)
            ->orderBy('s.name', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
