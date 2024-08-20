<?php

namespace App\Repository;

use App\Entity\Author;
use App\Entity\Book;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Book>
 */
class BookRepository extends ServiceEntityRepository
{
    public function __construct(
        ManagerRegistry $registry,
        private EntityManagerInterface $entityManager
    ){
        parent::__construct($registry, Book::class);
    }

        public function findAll(): array
    {
        return $this->createQueryBuilder('b')
            ->leftJoin('b.authors', 'a')
            ->leftJoin('b.category', 'c')
            ->addSelect('a')
            ->addSelect('c')
            ->getQuery()
            ->getResult();

    }

    public function findOneById(int $id): ?array
    {
        return $this->createQueryBuilder('b')
            //->select('b.id AS book_id', 'b.title', 'b.ISBN', 'b.description', 'b.image', 'b.available')
            //->addSelect('c.id AS category_id', 'c.name AS category_name', 'c.description AS category_description')
            //->addSelect('a.id AS author_id', 'a.firstName', 'a.lastName', 'a.biography')
            ->leftJoin('b.authors', 'a')
            ->leftJoin('b.category', 'c')
            ->addSelect('a')
            ->addSelect('c')
            ->where('b.id = :id')
            ->setParameter('id', $id)
            ->getQuery()
            // Use getArrayResult() to handle multiple authors
            ->getResult();
//dd($book);

    }
    public function remove(Book $book): void
    {
        // Remove the specified book from the entity manager
        $this->entityManager->remove($book);
        // Commit the changes to the database
        $this->entityManager->flush();
    }


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
