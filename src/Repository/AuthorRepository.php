<?php

namespace App\Repository;

use App\Entity\Author;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Author>
 */
class AuthorRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Author::class);
    }
    public function findAll(): array
    {
        $authors = $this->createQueryBuilder('a')
            ->leftJoin('a.book', 'b')
            ->leftJoin('b.category', 'c')
            ->addSelect('b')
            ->addSelect('c')
            ->getQuery()
            ->getResult();

        $authorsWithBooks = [];

        foreach ($authors as $author) {
            $authorId = $author->getId();
            if (!isset($authorsWithBooks[$authorId])) {
                $authorsWithBooks[$authorId] = [
                    'id' => $authorId,
                    'firstName' => $author->getFirstName(),
                    'lastName' => $author->getLastName(),
                    'biography' => $author->getBiography(),
                    'birthDate' => $author->getBirthDate()->format('Y-m-d'),
                    'books' => []
                ];
            }

            foreach ($author->getBooks() as $book) {
                $authorsWithBooks[$authorId]['books'][] = [
                    'id' => $book->getId(),
                    'title' => $book->getTitle(),
                    'description' => $book->getDescription(),
                    'isbn' => $book->getISBN(),
                    'image'=> $book->getImage(),
                    'available'=>$book->isAvailable(),
                    'category' => $book->getCategory() ? [
                        'id' => $book->getCategory()->getId(),
                        'name' => $book->getCategory()->getName(),
                        'description' => $book->getCategory()->getDescription()
                    ] : null
                ];
            }
        }

        return $authorsWithBooks;
    }

    public function findOneById(int $id): ?array
    {
        $query = $this->createQueryBuilder('a')
            ->select('a.id AS author_id', 'a.firstName', 'a.lastName', 'a.biography', 'a.birthDate',
                'b.id AS book_id', 'b.title AS book_title', 'b.ISBN', 'b.publishedYear',
                'b.description', 'b.image', 'b.available',
                'c.id AS category_id', 'c.name AS category_name', 'c.description AS category_description')
            ->leftJoin('a.book', 'b')
            ->leftJoin('b.category', 'c')
            ->where('a.id = :id')
            ->setParameter('id', $id)
            ->getQuery();

        $result = $query->getArrayResult();

        if (empty($result)) {
            return null;
        }

        $author = [
            'id' => null,
            'firstName' => null,
            'lastName' => null,
            'biography' => null,
            'birthDate' => null,
            'books' => []
        ];

        foreach ($result as $row) {
            if ($author['id'] === null) {
                $author['id'] = $row['author_id'] ?? null;
                $author['firstName'] = $row['firstName'] ?? null;
                $author['lastName'] = $row['lastName'] ?? null;
                $author['biography'] = $row['biography'] ?? null;
                $author['birthDate'] = $row['birthDate'] ?? null;
            }

            if (isset($row['book_id'])) {
                $author['books'][] = [
                    'id' => $row['book_id'] ?? null,
                    'title' => $row['book_title'] ?? null,
                    'ISBN' => $row['ISBN'] ?? null,
                    'publishedYear' => $row['publishedYear'] ?? null,
                    'description' => $row['description'] ?? null,
                    'image' => $row['image'] ?? null,
                    'available' => $row['available'] ?? null,
                    'category' => isset($row['category_id']) ? [
                        'id' => $row['category_id'] ?? null,
                        'name' => $row['category_name'] ?? null,
                        'description' => $row['category_description'] ?? null
                    ] : null
                ];
            }
        }

        return $author;
    }




    //    /**
    //     * @return Author[] Returns an array of Author objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('a')
    //            ->andWhere('a.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('a.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?Author
    //    {
    //        return $this->createQueryBuilder('a')
    //            ->andWhere('a.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
}
