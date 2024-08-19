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
    public function __construct(
        ManagerRegistry $registry,
        private EntityManagerInterface $entityManager
    ){
        parent::__construct($registry, Author::class);
    }
    public function findAll(): array
    {
        return $this->createQueryBuilder('a')
            ->leftJoin('a.books', 'b')
            ->leftJoin('b.category', 'c')
            ->addSelect('b', 'c')
            ->getQuery()
            ->getResult();
    }

    public function findOneById(int $id): ?array
    {
        return $this->createQueryBuilder('a')
            //->select('a', 'b', 'c')
            //->select('a.id AS author_id', 'a.firstName', 'a.lastName', 'a.biography', 'a.birthDate',
               //'b.id AS book_id', 'b.title AS book_title', 'b.ISBN', 'b.publishedYear',
              // 'b.description', 'b.image', 'b.available',
              //'c.id AS category_id', 'c.name AS category_name', 'c.description AS category_description')
            ->leftJoin('a.books', 'b')
            ->leftJoin('b.category', 'c')
            ->where('a.id = :id')
            ->setParameter('id', $id)
            ->getQuery()
            ->getResult();

    }
    public function remove(Author $author): void
    {
        // Remove the specified author from the entity manager
        $this->entityManager->remove($author);
        // Commit the changes to the database
        $this->entityManager->flush();
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
