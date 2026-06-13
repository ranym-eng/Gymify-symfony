<?php

namespace App\Repository;

use App\Entity\ResponsableSalle;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class ResponsableSalleRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ResponsableSalle::class);
    }

    //    /**
    //     * @return ResponsableSalle[] Returns an array of ResponsableSalle objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('r')
    //            ->andWhere('r.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('r.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?ResponsableSalle
    //    {
    //        return $this->createQueryBuilder('r')
    //            ->andWhere('r.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }

    /**
     * Find a single ResponsableSalle entity by a specific field.
     *
     * @param mixed $value The value to search for
     * @param string $field The field to search on (default: 'id')
     * @return ResponsableSalle|null Returns a ResponsableSalle object or null
     */
    public function findOneByField($value, string $field = 'id'): ?ResponsableSalle
    {
        return $this->createQueryBuilder('r')
            ->andWhere("r.{$field} = :val")
            ->setParameter('val', $value)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Find all ResponsableSalle entities.
     *
     * @return ResponsableSalle[] Returns an array of all ResponsableSalle objects
     */
    public function findAllResponsableSalle(): array
    {
        return $this->createQueryBuilder('r')
            ->orderBy('r.id', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
