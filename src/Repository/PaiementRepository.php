<?php

namespace App\Repository;

use App\Entity\Paiement;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Paiement>
 */
class PaiementRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Paiement::class);
    }

//    /**
//     * @return Paiement[] Returns an array of Paiement objects
//     */
//    public function findByExampleField($value): array
//    {
//        return $this->createQueryBuilder('p')
//            ->andWhere('p.exampleField = :val')
//            ->setParameter('val', $value)
//            ->orderBy('p.id', 'ASC')
//            ->setMaxResults(10)
//            ->getQuery()
//            ->getResult()
//        ;
//    }

//    public function findOneBySomeField($value): ?Paiement
//    {
//        return $this->createQueryBuilder('p')
//            ->andWhere('p.exampleField = :val')
//            ->setParameter('val', $value)
//            ->getQuery()
//            ->getOneOrNullResult()
//        ;
//    }
public function findExpiringPaiements(\DateTimeImmutable $dateLimit): array
{
    $startOfDay = (clone $dateLimit)->modify('midnight');
    $endOfDay = (clone $dateLimit)->modify('23:59:59');

    return $this->createQueryBuilder('p')
        ->where('p.date_fin BETWEEN :start AND :end')
        ->andWhere('p.status = :status')
        ->setParameter('start', $startOfDay)
        ->setParameter('end', $endOfDay)
        ->setParameter('status', 'succeeded')
        ->getQuery()
        ->getResult();
}
}
