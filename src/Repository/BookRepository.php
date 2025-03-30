<?php

namespace App\Repository;

use App\Entity\Author;
use App\Entity\Book;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\ORM\Tools\Pagination\Paginator;

/**
 * @extends ServiceEntityRepository<Book>
 */
class BookRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Book::class);
    }
    public function findPublishedInYear(int $page, int $limit, int $year): array
    {
        $qb = $this->createQueryBuilder('b');

        // Define the start and end dates for the given year
        $startDate = new \DateTime('-4 years');
        $endDate = new \DateTime();

        // Adjust the query to use BETWEEN for date range comparison
        $query = $qb
            ->andWhere('b.publishedYear BETWEEN :startDate AND :endDate')
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate)
            ->orderBy('b.publishedYear', 'DESC')
            // Pagination logic
            ->setFirstResult(($page - 1) * $limit)
             //Set the maximum number of results to return
            ->setMaxResults($limit)
            ->getQuery();

        $paginator = new Paginator($query);

        return [
            'books' => $paginator->getIterator()->getArrayCopy(),  // Return the list of books
            'totalItems' => $paginator->count(),  // Return the total number of items
        ];
    }

    /**
     * Search for books by title
     * 
     * @param string $title The search term
     * @param int $limit Maximum number of results (optional)
     * @param int $offset For pagination (optional)
     * @return array The books matching the criteria
     */
    public function findBookByTitle(string $title, int $limit = 10, int $offset = 0): array
    {
        $queryBuilder = $this->createQueryBuilder('b');

        // Handle empty searches
        if (empty(trim($title))) {
            return [];
        }

        // Clean and prepare the search term
        $searchTerm = trim($title);
        $searchTerms = explode(' ', $searchTerm);

        // If searching with multiple words, use an OR condition for each word
        if (count($searchTerms) > 1) {
            $orExpressions = $queryBuilder->expr()->orX();
            foreach ($searchTerms as $key => $term) {
                if (strlen($term) >= 2) { 
                    $paramName = 'title' . $key;
                    $orExpressions->add($queryBuilder->expr()->like('LOWER(b.title)', ':' . $paramName));
                    $queryBuilder->setParameter($paramName, '%' . strtolower($term) . '%');
                }
            }

            if ($orExpressions->count() > 0) {
                $queryBuilder->where($orExpressions);
            }
        } else {
            // Simple search with a single term
            $queryBuilder
                ->where($queryBuilder->expr()->like('LOWER(b.title)', ':title'))
                ->setParameter('title', '%' . strtolower($searchTerm) . '%');
        }

        // Sort by relevance (books whose title starts with the search term first)
        $queryBuilder
            ->addOrderBy('CASE WHEN LOWER(b.title) LIKE :exact_start THEN 0 ELSE 1 END', 'ASC')
            ->addOrderBy('b.title', 'ASC')
            ->setParameter('exact_start', strtolower($searchTerm) . '%');

        // Pagination
        $queryBuilder
            ->setMaxResults($limit)
            ->setFirstResult($offset);

        return $queryBuilder->getQuery()->getResult();
    }

    // public function findBooksByCategory(int $categoryId): array
    // {
    //     return $this->createQueryBuilder('b')
    //         ->leftJoin('b.category', 'c')
    //         ->where('c.id = :categoryId')
    //         ->setParameter('categoryId', $categoryId)
    //         ->getQuery()
    //         ->getResult();
    // }

    //    /**
    //     * @return Book[] Returns an array of Book objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('b')
    //            ->andWhere('b.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('b.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?Book
    //    {
    //        return $this->createQueryBuilder('b')
    //            ->andWhere('b.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
}
