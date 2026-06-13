<?php
namespace App\Repository;

use App\Entity\Reponse;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Reponse>
 */
class ReponseRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Reponse::class);
    }

    public function findByReclamation(int $reclamationId): ?Reponse
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.reclamation = :reclamationId')
            ->setParameter('reclamationId', $reclamationId)
            ->getQuery()
            ->getOneOrNullResult();
    }
}