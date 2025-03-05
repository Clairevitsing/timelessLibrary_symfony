<?php

namespace App\Repository;

use App\Entity\Author;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Author>
 */
class AuthorRepository extends ServiceEntityRepository
{
    private $entityManager;

    public function __construct(
        ManagerRegistry $registry,
        EntityManagerInterface $entityManager
    ) {
        parent::__construct($registry, Author::class);
        $this->entityManager = $entityManager;
    }

    /**
     * Remove an author from the database
     */
    public function remove(Author $author): void
    {
        $this->entityManager->remove($author);
        $this->entityManager->flush();
    }

    /**
     * Search authors with flexible criteria
     * 
     * @param array $criteria Search criteria
     * @return Author[] Array of matching authors
     */
    public function searchAuthors(array $criteria): array
    {
        $queryBuilder = $this->createQueryBuilder('a');

        if (!empty($criteria['firstName'])) {
            $queryBuilder->andWhere('a.firstName LIKE :firstName')
                ->setParameter('firstName', '%' . $criteria['firstName'] . '%');
        }

        if (!empty($criteria['lastName'])) {
            $queryBuilder->andWhere('a.lastName LIKE :lastName')
                ->setParameter('lastName', '%' . $criteria['lastName'] . '%');
        }

        return $queryBuilder->getQuery()->getResult();
    }

    /**
     * Find an author by first and last name
     */
    public function findByName(string $firstName, string $lastName): ?Author
    {
        return $this->createQueryBuilder('a')
            ->andWhere('a.firstName = :firstName')
            ->andWhere('a.lastName = :lastName')
            ->setParameter('firstName', $firstName)
            ->setParameter('lastName', $lastName)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
