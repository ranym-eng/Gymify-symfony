<?php

namespace App\Repository;

use App\Entity\User;
use App\Enum\Role;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Psr\Log\LoggerInterface;

/**
 * @extends ServiceEntityRepository<User>
 */
class UserRepository extends ServiceEntityRepository
{
    private $logger;

    public function __construct(ManagerRegistry $registry, LoggerInterface $logger)
    {
        parent::__construct($registry, User::class);
        $this->logger = $logger;
    }

    /**
     * Find all users, sorted by ID in ascending order.
     *
     * @return User[]
     */
    public function findAll(): array
    {
        return $this->findBy([], ['id' => 'ASC']);
    }

    /**
     * Find users by their roles.
     *
     * @param Role[] $roles
     * @return User[]
     */
    public function findByRoles(array $roles): array
    {
        if (empty($roles)) {
            $this->logger->warning('No roles provided for findByRoles');
            return [];
        }

        $roleValues = array_map(fn(Role $role) => $role->value, $roles);

        $this->logger->info('Executing findByRoles', [
            'roles' => $roleValues,
        ]);

        // Map Role enum values to corresponding entity classes
        $classMap = [
            'sportif' => \App\Entity\Sportif::class,
            'entraineur' => \App\Entity\Entraineur::class,
            'admin' => \App\Entity\Admin::class,
            'responsable_salle' => \App\Entity\ResponsableSalle::class,
        ];

        // Log the class map for debugging
        $this->logger->debug('Class map', [
            'classMap' => $classMap,
        ]);

        // Filter classes based on provided roles
        $targetClasses = array_filter(
            $classMap,
            fn($key) => in_array($key, $roleValues),
            ARRAY_FILTER_USE_KEY
        );

        if (empty($targetClasses)) {
            $this->logger->warning('No valid entity classes mapped for provided roles', [
                'roles' => $roleValues,
            ]);
            return [];
        }

        // Log the target classes
        $this->logger->debug('Target classes', [
            'targetClasses' => $targetClasses,
        ]);

        $queryBuilder = $this->createQueryBuilder('u')
            ->where('u INSTANCE OF :classes')
            ->setParameter('classes', array_values($targetClasses))
            ->orderBy('u.id', 'ASC');

        $query = $queryBuilder->getQuery();

        // Log the DQL and SQL for debugging
        $this->logger->debug('DQL Query', [
            'dql' => $query->getDQL(),
            'sql' => $query->getSQL(),
            'parameters' => array_map(fn($param) => is_array($param) ? implode(', ', $param) : $param, $query->getParameters()->getValues()),
        ]);

        try {
            $results = $query->getResult();
            $this->logger->info('Users found', [
                'count' => count($results),
                'roles' => $roleValues,
                'users' => array_map(fn(User $u) => [
                    'id' => $u->getId(),
                    'email' => $u->getEmail(),
                    'role' => $u->getRole()->value,
                    'class' => get_class($u),
                ], $results),
            ]);
            return $results;
        } catch (\Exception $e) {
            $this->logger->error('Error in findByRoles', [
                'message' => $e->getMessage(),
                'roles' => $roleValues,
            ]);
            throw $e;
        }
    }
    public function getUserCountByRole(): array
    {
        return [
            'sportif' => (int) $this->createQueryBuilder('u')
                ->select('COUNT(u.id)')
                ->where('u INSTANCE OF \App\Entity\Sportif')
                ->getQuery()
                ->getSingleScalarResult(),
    
            'entraineur' => (int) $this->createQueryBuilder('u')
                ->select('COUNT(u.id)')
                ->where('u INSTANCE OF \App\Entity\Entraineur')
                ->getQuery()
                ->getSingleScalarResult(),
    
            'admin' => (int) $this->createQueryBuilder('u')
                ->select('COUNT(u.id)')
                ->where('u INSTANCE OF \App\Entity\Admin')
                ->getQuery()
                ->getSingleScalarResult(),
    
            'responsable_salle' => (int) $this->createQueryBuilder('u')
                ->select('COUNT(u.id)')
                ->where('u INSTANCE OF \App\Entity\ResponsableSalle')
                ->getQuery()
                ->getSingleScalarResult(),
        ];
    }
    public function save(User $user): void
    {
        $entityManager = $this->getEntityManager();
        $entityManager->persist($user);
        $entityManager->flush();
    }
    
    }

