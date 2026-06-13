<?php

namespace App\Repository;

use App\Entity\Salle;
use App\Entity\EquipeEvent;
use App\Enum\EventType;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<EquipeEvent>
 */
class EquipeEventRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, EquipeEvent::class);
    }

    /**
     * Find EquipeEvents by Salle.
     *
     * @param Salle $salle
     * @return EquipeEvent[]
     */
    public function findBySalle(Salle $salle): array
    {
        return $this->createQueryBuilder('ee')
            ->join('ee.event', 'e')
            ->where('e.salle = :salle')
            ->setParameter('salle', $salle)
            ->orderBy('e.date', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find EquipeEvents for a given Salle and EventType.
     *
     * @param Salle $salle
     * @param EventType $type
     * @return EquipeEvent[]
     */
    public function findBySalleAndType(Salle $salle, EventType $type): array
    {
        return $this->createQueryBuilder('ee')
            ->innerJoin('ee.event', 'e')
            ->where('e.salle = :salle')
            ->andWhere('e.type = :type')
            ->setParameter('salle', $salle)
            ->setParameter('type', $type)
            ->orderBy('e.date', 'ASC')
            ->getQuery()
            ->getResult();
    }
}