<?php

namespace App\Repository;

use App\Entity\Events;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Events>
 */
class EventsRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Events::class);
    }

    /**
     * Find events by multi-criteria search (name, type, location).
     *
     * @param string $query
     * @return Events[]
     */
    public function findByMultiCriteria(string $query): array
    {
        $qb = $this->createQueryBuilder('e');

        if ($query) {
            $qb->where('LOWER(e.nom) LIKE :query')
               ->orWhere('LOWER(e.type) LIKE :query')
               ->orWhere('LOWER(e.lieu) LIKE :query')
               ->setParameter('query', '%' . strtolower($query) . '%');
        }

        return $qb->getQuery()->getResult();
    }
}