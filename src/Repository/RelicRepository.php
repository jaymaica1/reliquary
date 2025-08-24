<?php

namespace App\Repository;

use App\Entity\Relic;
use App\Entity\User;
use App\Enum\RelicStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\ParameterType;
use Doctrine\ORM\Query;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Relic>
 */
class RelicRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Relic::class);
    }

    /**
     * Find relics by status
     *
     * @param RelicStatus $status The status to filter by
     * @return Relic[] Returns an array of Relic objects
     */
    public function findByStatus(RelicStatus $status): array
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.status = :status')
            ->setParameter('status', $status->value, ParameterType::STRING)
            ->getQuery()
            ->getResult();
    }

    /**
     * Count relics by status
     *
     * @param RelicStatus $status The status to filter by
     * @return int The count of relics with the specified status
     */
    public function countByStatus(RelicStatus $status): int
    {
        return $this->createQueryBuilder('r')
            ->select('COUNT(r.id)')
            ->andWhere('r.status = :status')
            ->setParameter('status', $status->value, ParameterType::STRING)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Find relics within a specified radius of a given location
     *
     * @param float $latitude The latitude of the center point
     * @param float $longitude The longitude of the center point
     * @param float $radiusKm The radius in kilometers
     * @param object|null $user The current user
     * @return Relic[] Returns an array of Relic objects
     */
    public function findWithinRadius(float $latitude, float $longitude, float $radiusKm, ?object $user = null): array
    {
        // Use a simple bounding box approach to filter relics
        // This avoids using trigonometric functions that might not be available in PostgreSQL

        // Approximate degrees to km conversion factors
        // These values are approximate and work best near the equator
        $kmPerLatDegree = 111.0; // 1 degree of latitude is approximately 111 km
        $kmPerLngDegree = 111.0 * cos(deg2rad($latitude)); // Longitude degrees vary with latitude

        // Calculate the latitude and longitude ranges for the bounding box
        $latRange = $radiusKm / $kmPerLatDegree;
        $lngRange = $radiusKm / $kmPerLngDegree;

        $minLat = $latitude - $latRange;
        $maxLat = $latitude + $latRange;
        $minLng = $longitude - $lngRange;
        $maxLng = $longitude + $lngRange;

        // First, get relics within the bounding box
        $qb = $this->createQueryBuilder('r')
            ->andWhere('r.latitude IS NOT NULL')
            ->andWhere('r.longitude IS NOT NULL')
            ->andWhere('r.latitude BETWEEN :minLat AND :maxLat')
            ->andWhere('r.longitude BETWEEN :minLng AND :maxLng')
            ->setParameter('minLat', $minLat)
            ->setParameter('maxLat', $maxLat)
            ->setParameter('minLng', $minLng)
            ->setParameter('maxLng', $maxLng);

        $this->applyVisibilityRestrictions($user, $qb);

        $relics = $qb->getQuery()->getResult();

        // Then, filter the results to get only those within the actual radius
        // This is done in PHP to avoid complex SQL calculations
        return array_filter($relics, function ($relic) use ($latitude, $longitude, $radiusKm, $kmPerLatDegree, $kmPerLngDegree) {
            $latDiff = abs($relic->getLatitude() - $latitude);
            $lngDiff = abs($relic->getLongitude() - $longitude);

            // Approximate distance calculation using the Pythagorean theorem
            // This is not perfectly accurate for large distances but works well for small ones
            $latDistKm = $latDiff * $kmPerLatDegree;
            $lngDistKm = $lngDiff * $kmPerLngDegree;
            $distanceKm = sqrt($latDistKm * $latDistKm + $lngDistKm * $lngDistKm);

            return $distanceKm <= $radiusKm;
        });
    }

    /**
     * Find all relics query with optional degree filter and visibility restrictions
     *
     * @param string|null $degree The degree to filter by
     * @param object|null $user The current user
     * @return Query The query object
     */
    public function findAllQuery(?string $degree = null, ?object $user = null): Query
    {
        $queryBuilder = $this->createQueryBuilder('r');

        if ($degree) {
            $queryBuilder
                ->andWhere('r.degree = :degree')
                ->setParameter('degree', $degree);
        }

        $this->applyVisibilityRestrictions($user, $queryBuilder);

        return $queryBuilder->getQuery();
    }

    /**
     * Find relics created by a specific user with optional degree filter
     *
     * @param object $user The user who created the relics
     * @param string|null $degree The degree to filter by
     * @return Query The query object
     */
    public function findByCreatorQuery($user, ?string $degree = null): Query
    {
        $queryBuilder = $this->createQueryBuilder('r')
            ->where('r.creator = :user')
            ->setParameter('user', $user);

        if ($degree) {
            $queryBuilder
                ->andWhere('r.degree = :degree')
                ->setParameter('degree', $degree);
        }

        return $queryBuilder->getQuery();
    }

    /**
     * Find all relics with visibility restrictions
     *
     * @param object|null $user The current user
     * @return Relic[] Returns an array of Relic objects
     */
    public function findAllWithVisibility(?object $user = null): array
    {
        $queryBuilder = $this->createQueryBuilder('r');

        // Apply visibility restrictions
        $this->applyVisibilityRestrictions($user, $queryBuilder);

        return $queryBuilder->getQuery()->getResult();
    }

    /**
     * Find relics by saint with visibility restrictions
     *
     * @param int $saintId The saint ID
     * @param object|null $user The current user
     * @return Relic[] Returns an array of Relic objects
     */
    public function findBySaintWithVisibility(int $saintId, ?object $user = null): array
    {
        $queryBuilder = $this->createQueryBuilder('r')
            ->andWhere('r.saint = :saint')
            ->setParameter('saint', $saintId);
        $this->applyVisibilityRestrictions($user, $queryBuilder);

        return $queryBuilder->getQuery()->getResult();
    }

    /**
     * @param object|null $user
     * @param QueryBuilder $queryBuilder
     * @return void
     */
    public function applyVisibilityRestrictions(?object $user, QueryBuilder $queryBuilder): void
    {
        if ($user?->isAdmin()) {
            return;
        }
        
        if ($user) {
            $queryBuilder
                ->andWhere('(r.status = :approved_status OR (r.creator = :user AND (r.status = :pending_status OR r.status = :rejected_status)))')
                ->setParameter('approved_status', RelicStatus::APPROVED->value, ParameterType::STRING)
                ->setParameter('pending_status', RelicStatus::PENDING->value, ParameterType::STRING)
                ->setParameter('rejected_status', RelicStatus::REJECTED->value, ParameterType::STRING)
                ->setParameter('user', $user);
            return;
        }

        $queryBuilder
            ->andWhere('r.status = :approved_status')
            ->setParameter('approved_status', RelicStatus::APPROVED->value, ParameterType::STRING);
    }
}
