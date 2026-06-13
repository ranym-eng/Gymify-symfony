<?php

namespace App\Repository;

use App\Entity\Produit;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Produit>
 *
 * @method Produit|null find($id, $lockMode = null, $lockVersion = null)
 * @method Produit|null findOneBy(array $criteria, array $orderBy = null)
 * @method Produit[]    findAll()
 * @method Produit[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ProduitRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Produit::class);
    }

    public function save(Produit $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Produit $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function searchByName(string $query): array
    {
        return $this->createQueryBuilder('p')
            ->where('p.nomP LIKE :query')
            ->setParameter('query', '%' . $query . '%')
            ->orderBy('p.nomP', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findByPriceRange(?float $minPrice = null, ?float $maxPrice = null): array
    {
        $qb = $this->createQueryBuilder('p');

        if ($minPrice !== null) {
            $qb->andWhere('p.prixP >= :minPrice')
               ->setParameter('minPrice', $minPrice);
        }

        if ($maxPrice !== null) {
            $qb->andWhere('p.prixP <= :maxPrice')
               ->setParameter('maxPrice', $maxPrice);
        }

        return $qb->orderBy('p.prixP', 'ASC')
                 ->getQuery()
                 ->getResult();
    }

    public function findByFilters(?float $minPrice = null, ?float $maxPrice = null, ?string $category = null): array
    {
        $qb = $this->createQueryBuilder('p');

        if ($minPrice !== null) {
            $qb->andWhere('p.prixP >= :minPrice')
               ->setParameter('minPrice', $minPrice);
        }

        if ($maxPrice !== null) {
            $qb->andWhere('p.prixP <= :maxPrice')
               ->setParameter('maxPrice', $maxPrice);
        }

        if ($category !== null && $category !== '') {
            $qb->andWhere('p.categorieP = :category')
               ->setParameter('category', $category);
        }

        return $qb->orderBy('p.prixP', 'ASC')
                 ->getQuery()
                 ->getResult();
    }

    /**
     * Returns the IDs of the top N best-selling products.
     */
    public function findTopVentesIds(int $limit = 3): array
    {
        $conn = $this->getEntityManager()->getConnection();
        $sql = 'SELECT produit_id, COUNT(*) as ventes
                FROM ligne_commande
                GROUP BY produit_id
                ORDER BY ventes DESC
                LIMIT :limit';
        $stmt = $conn->prepare($sql);
        $stmt->bindValue('limit', $limit, \PDO::PARAM_INT);
        $result = $stmt->executeQuery();
        return array_column($result->fetchAllAssociative(), 'produit_id');
    }
} 