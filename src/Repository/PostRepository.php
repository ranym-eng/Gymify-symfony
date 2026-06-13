<?php

namespace App\Repository;

use App\Entity\Post;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\Query;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Post>
 */
class PostRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Post::class);
    }

    /**
     * Find all posts with filters applied
     * 
     * @param array $filters The filter criteria
     * @return Query The query with filters applied
     */
    public function findByFilters(array $filters = []): Query
    {
        // Initial query builder
        $qb = $this->createQueryBuilder('p')
            ->select('p', 'u')
            ->leftJoin('p.user', 'u')
            ->orderBy('p.createdAt', 'DESC')
            ->groupBy('p.id', 'u.id');
        
        // Apply search filter
        if (!empty($filters['search'])) {
            $qb->andWhere('p.title LIKE :search OR p.content LIKE :search')
               ->setParameter('search', '%' . $filters['search'] . '%');
        }
        
        // Filter by author
        if (!empty($filters['author'])) {
            $qb->andWhere('p.user = :author')
               ->setParameter('author', $filters['author']);
        }
        
        // Filter by date range
        if (!empty($filters['dateFrom'])) {
            $qb->andWhere('p.createdAt >= :dateFrom')
               ->setParameter('dateFrom', $filters['dateFrom']);
        }
        
        if (!empty($filters['dateTo'])) {
            // Add one day to include the end date
            $dateTo = clone $filters['dateTo'];
            $dateTo->modify('+1 day');
            
            $qb->andWhere('p.createdAt < :dateTo')
               ->setParameter('dateTo', $dateTo);
        }
        
        // Filter by minimum reactions count
        if (!empty($filters['minReactions'])) {
            $minReactions = (int)$filters['minReactions'];
            
            $qb->andWhere('(SELECT COUNT(r.id) FROM App\Entity\Reactions r WHERE r.post = p) >= :minReactions')
               ->setParameter('minReactions', $minReactions);
        }
        
        // Filter by minimum comments count
        if (!empty($filters['minComments'])) {
            $minComments = (int)$filters['minComments'];
            
            $qb->andWhere('(SELECT COUNT(c.id) FROM App\Entity\Comment c WHERE c.post = p) >= :minComments')
               ->setParameter('minComments', $minComments);
        }
        
        // Add sorting
        if (!empty($filters['sortBy'])) {
            switch ($filters['sortBy']) {
                case 'date_asc':
                    $qb->orderBy('p.createdAt', 'ASC');
                    break;
                
                case 'reactions':
                    $qb->orderBy('(SELECT COUNT(r2.id) FROM App\Entity\Reactions r2 WHERE r2.post = p)', 'DESC');
                    break;
                
                case 'comments':
                    $qb->orderBy('(SELECT COUNT(c2.id) FROM App\Entity\Comment c2 WHERE c2.post = p)', 'DESC');
                    break;
                
                case 'date_desc':
                default:
                    $qb->orderBy('p.createdAt', 'DESC');
                    break;
            }
        }
        
        return $qb->getQuery();
    }

    //    /**
    //     * @return Post[] Returns an array of Post objects
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

    //    public function findOneBySomeField($value): ?Post
    //    {
    //        return $this->createQueryBuilder('p')
    //            ->andWhere('p.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
}
