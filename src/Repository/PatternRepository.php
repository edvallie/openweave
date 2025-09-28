<?php

namespace App\Repository;

use App\Entity\Pattern;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Pattern>
 */
class PatternRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Pattern::class);
    }

    /**
     * Find patterns with filters and pagination
     */
    public function findWithFilters(array $filters = [], int $page = 1, int $perPage = 52): array
    {
        $qb = $this->createQueryBuilder('p');
        
        // Apply search filter
        if (!empty($filters['search'])) {
            $qb->andWhere('p.title LIKE :search')
               ->setParameter('search', '%' . $filters['search'] . '%');
        }
        
        // Apply shaft filters
        if (!empty($filters['minShafts'])) {
            $qb->andWhere('p.shafts >= :minShafts')
               ->setParameter('minShafts', (int) $filters['minShafts']);
        }
        
        if (!empty($filters['maxShafts'])) {
            $qb->andWhere('p.shafts <= :maxShafts')
               ->setParameter('maxShafts', (int) $filters['maxShafts']);
        }
        
        // Apply treadle filters
        if (!empty($filters['minTreadles'])) {
            $qb->andWhere('p.treadles >= :minTreadles')
               ->setParameter('minTreadles', (int) $filters['minTreadles']);
        }
        
        if (!empty($filters['maxTreadles'])) {
            $qb->andWhere('p.treadles <= :maxTreadles')
               ->setParameter('maxTreadles', (int) $filters['maxTreadles']);
        }
        
        // Order by title
        $qb->orderBy('p.title', 'ASC');
        
        // Apply pagination
        $offset = ($page - 1) * $perPage;
        $qb->setFirstResult($offset)
           ->setMaxResults($perPage);
        
        return $qb->getQuery()->getResult();
    }
    
    /**
     * Count patterns with filters
     */
    public function countWithFilters(array $filters = []): int
    {
        $qb = $this->createQueryBuilder('p')
                   ->select('COUNT(p.id)');
        
        // Apply search filter
        if (!empty($filters['search'])) {
            $qb->andWhere('p.title LIKE :search')
               ->setParameter('search', '%' . $filters['search'] . '%');
        }
        
        // Apply shaft filters
        if (!empty($filters['minShafts'])) {
            $qb->andWhere('p.shafts >= :minShafts')
               ->setParameter('minShafts', (int) $filters['minShafts']);
        }
        
        if (!empty($filters['maxShafts'])) {
            $qb->andWhere('p.shafts <= :maxShafts')
               ->setParameter('maxShafts', (int) $filters['maxShafts']);
        }
        
        // Apply treadle filters
        if (!empty($filters['minTreadles'])) {
            $qb->andWhere('p.treadles >= :minTreadles')
               ->setParameter('minTreadles', (int) $filters['minTreadles']);
        }
        
        if (!empty($filters['maxTreadles'])) {
            $qb->andWhere('p.treadles <= :maxTreadles')
               ->setParameter('maxTreadles', (int) $filters['maxTreadles']);
        }
        
        return $qb->getQuery()->getSingleScalarResult();
    }
}
