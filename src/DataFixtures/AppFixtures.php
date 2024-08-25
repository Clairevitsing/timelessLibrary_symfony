<?php

namespace App\DataFixtures;

use App\Entity\Author;
use App\Entity\Book;
use App\Entity\BookLoan;
use App\Entity\Category;
use App\Entity\Loan;
use App\Entity\User;
use DateTime;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Faker\Factory;
use Faker\Generator;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class AppFixtures extends Fixture
{
    // Constants for the number of entities to create
    private const NB_CATEGORIES = 6;
    private const NB_BOOKS = 100;
    private const NB_AUTHORS = 10;
    private const NB_USERS = 100;
    private const NB_LOANS = 50;

    // User role constants
    private const ROLE_USER = 'ROLE_USER';
    private const ROLE_LIBRARIAN = 'ROLE_LIBRARIAN';

    private Generator $faker;
    private array $usedUsernames = [];
    private array $usedIsbns = [];

    public function __construct(private readonly UserPasswordHasherInterface $hasher)
    {
        $this->faker = Factory::create('fr_FR');
    }

    public function load(ObjectManager $manager): void
    {
        $categories = $this->createCategories($manager);
        $authors = $this->createAuthors($manager);
        $users = $this->createUsers($manager);
        $books = $this->createBooks($manager, $categories, $authors);
        $loans = $this->createLoans($manager, $users);

        $this->createBookLoans($manager, $books, $loans);

        $manager->flush();
    }

    private function createCategories(ObjectManager $manager): array
    {
        $categories = [];
        for ($i = 0; $i < self::NB_CATEGORIES; $i++) {
            $category = new Category();
            $category->setName($this->faker->unique()->word())
                ->setDescription($this->faker->text(300));
            $manager->persist($category);
            $categories[] = $category;
        }
        return $categories;
    }

    private function createAuthors(ObjectManager $manager): array
    {
        $authors = [];
        for ($i = 0; $i < self::NB_AUTHORS; $i++) {
            $author = new Author();
            $author->setFirstName($this->faker->firstName())
                ->setLastName($this->faker->lastName())
                ->setBiography($this->faker->text())
                ->setBirthDate($this->faker->dateTimeBetween('-75 years', '-18 years'));
            $manager->persist($author);
            $authors[] = $author;
        }
        return $authors;
    }

    private function createUsers(ObjectManager $manager): array
    {
        $users = [];
        $librarianCount = 0;
        $targetLibrarianCount = max(1, ceil(self::NB_USERS / 20));

        for ($i = 0; $i < self::NB_USERS; $i++) {
            $user = new User();
            $user->setEmail($this->faker->unique()->safeEmail())
                ->setFirstName($this->faker->firstName())
                ->setLastName($this->faker->lastName())
                ->setUserName($this->generateRandomUniqueUsername())
                ->setPhoneNumber($this->faker->phoneNumber());

            $subStartDate = $this->faker->dateTimeBetween('-1 year');
            $user->setSubStartDate($subStartDate)
                ->setSubEndDate($this->faker->dateTimeBetween($subStartDate, '+1 year'));

            if ($librarianCount < $targetLibrarianCount && ($i % 20 === 0 || $i === self::NB_USERS - 1)) {
                $user->setRoles([self::ROLE_LIBRARIAN]);
                $librarianCount++;
            } else {
                $user->setRoles([self::ROLE_USER]);
            }

            $user->setPassword($this->hasher->hashPassword($user, "password"));
            $manager->persist($user);
            $users[] = $user;
        }
        return $users;
    }

    private function createBooks(ObjectManager $manager, array $categories, array $authors): array
    {
        $books = [];
        for ($i = 0; $i < self::NB_BOOKS; $i++) {
            $book = new Book();
            $book->setTitle($this->faker->sentence(3))
                ->setDescription($this->faker->text(500))
                ->setPublishedYear($this->faker->dateTimeBetween('-30 years', 'now'))
                ->setCategory($this->faker->randomElement($categories))
                ->setAvailable(true)
                ->setISBN($this->generateUniqueIsbn())
                ->setImage($this->getRandomBookCover());

            $numberOfAuthors = $this->faker->numberBetween(1, 3);
            $selectedAuthors = $this->faker->randomElements($authors, $numberOfAuthors);
            foreach ($selectedAuthors as $author) {
                $book->addAuthor($author);
            }

            $manager->persist($book);
            $books[] = $book;
        }
        return $books;
    }

    private function createLoans(ObjectManager $manager, array $users): array
    {
        $loans = [];
        for ($i = 0; $i < self::NB_LOANS; $i++) {
            $loan = new Loan();
            $loanDate = $this->faker->dateTimeBetween('-6 months');
            $dueDate = (clone $loanDate)->modify('+3 weeks');
            $loan->setLoanDate($loanDate)
                ->setDueDate($dueDate)
                ->setReturnDate($this->faker->boolean(70) ? $this->faker->dateTimeBetween($loanDate) : null)
                ->setUser($this->faker->randomElement($users));

            $manager->persist($loan);
            $loans[] = $loan;
        }
        return $loans;
    }

    private function createBookLoans(ObjectManager $manager, array $books, array $loans): void
    {
        foreach ($loans as $loan) {
            $numberOfBooks = $this->faker->numberBetween(1, 3);
            $availableBooks = array_filter($books, fn($book) => $book->isAvailable());

            if (count($availableBooks) < $numberOfBooks) {
                continue;
            }

            $selectedBooks = $this->faker->randomElements($availableBooks, $numberOfBooks);

            foreach ($selectedBooks as $book) {
                $bookLoan = new BookLoan();
                $bookLoan->setBook($book)
                    ->setLoan($loan);

                $book->addBookLoan($bookLoan);
                $loan->addBookLoan($bookLoan);

                $book->setAvailable($loan->getReturnDate() !== null);

                $manager->persist($bookLoan);
            }
        }
    }

    private function generateRandomUniqueUsername(): string
    {
        do {
            $username = $this->faker->userName();
        } while (in_array($username, $this->usedUsernames, true));

        $this->usedUsernames[] = $username;
        return $username;
    }

    private function generateUniqueIsbn(): string
    {
        do {
            $isbn = '978' . $this->faker->numerify('#########');
            $weightedSum = 0;
            for ($i = 0; $i < 12; $i++) {
                $digit = (int) $isbn[$i];
                $weightedSum += ($i % 2 === 0) ? $digit : $digit * 3;
            }
            $checkDigit = (10 - ($weightedSum % 10)) % 10;
            $isbn .= $checkDigit;
        } while (in_array($isbn, $this->usedIsbns, true));

        $this->usedIsbns[] = $isbn;
        return $isbn;
    }

    private function getRandomBookCover(): string
    {
        $width = 300;
        $height = 400;
        $randomId = $this->faker->numberBetween(1, 1000);
        return "https://picsum.photos/id/$randomId/$width/$height";
    }
}