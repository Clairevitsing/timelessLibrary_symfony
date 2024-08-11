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
    public const NB_CATEGORIES = 6;
    public const NB_BOOKS = 30;
    public const NB_AUTHORS = 10;
    public const NB_USERS = 100;
    public const NB_LOANS = 50;

    // User role constants
    public const ROLE_USER = 'ROLE_USER';
    public const ROLE_LIBRARIAN = 'ROLE_LIBRARIAN';

    public function __construct(private readonly UserPasswordHasherInterface $hasher)
    {
    }
    public function load(ObjectManager $manager): void
    {
        $faker = Factory::create('fr_FR');

        $categories = $this->createCategories($manager, $faker);
        $authors = $this->createAuthors($manager, $faker);
        $users = $this->createUsers($manager, $faker);
        $books = $this->createBooks($manager, $faker, $categories, $authors);
        $loans = $this->createLoans($manager, $faker, $users);

        $this->createBookLoans($manager, $faker, $books, $loans);

        $manager->flush();

    }

    private function createCategories(ObjectManager $manager,Generator $faker):array
    {
        // Creation of categories
        $categories = [];
        for ($i = 0; $i < self::NB_CATEGORIES; $i++) {
            $category = new Category();
            $category->setName($faker->unique()->word());
            $category->setDescription($faker->text(300));
            $manager->persist($category);
            $categories[] = $category;
        }
        return $categories;
    }

    //create authors
    private function createAuthors(ObjectManager $manager,Generator $faker):array
    {
        $authors = [];
        for ($i = 0; $i < self::NB_AUTHORS; $i++) {
            $author = new Author();
            $author->setFirstName($faker->firstname())
                ->setLastName($faker->lastname())
                ->setBiography($faker->text())
                ->setBirthDate($faker->dateTimeBetween('-75 years', '-18 years'));
            $manager->persist($author);
            $authors[] = $author;
        }
        return $authors;
    }

    //create users
    private array $usedUsernames = [];
    private function createUsers(ObjectManager $manager,Generator $faker):array
    {
        $users = [];
        $librarianCount = 0;
        // Ensure at least 1 librarian
        $targetLibrarianCount = max(1, ceil(self::NB_USERS / 20));

        for ($i = 0; $i < self::NB_USERS; $i++) {
            $user = new User();
            $user->setEmail($faker->unique()->safeEmail());
            $user->setFirstName($faker->firstName());
            $user->setLastName($faker->lastName());
            $username = $this->generateUniqueUsername($faker, $user->getFirstName(), $user->getLastName());
            $user->setUserName($username);
            $user->setPhoneNumber($faker->phoneNumber());
            $subStartDate = $faker->dateTimeBetween('-1 year');
            $user->setSubStartDate($subStartDate);
            $user->setSubEndDate($faker->dateTimeBetween($subStartDate, '+1 year'));

            if ($librarianCount < $targetLibrarianCount && ($i % 20 === 0 || $i === self::NB_USERS - 1)) {
                $user->setRoles([self::ROLE_LIBRARIAN]);
                $librarianCount++;
            } else {
                $user->setRoles([self::ROLE_USER]);
            }

            $user->setPassword($this->hasher->hashPassword($user,"password"));

            //$user->setCreatedAt($faker->dateTimeBetween('-1 year', 'now'));
            //$user->setIsActive(true);

            $manager->persist($user);
            $users[] = $user;
        }
        return $users;
    }

    //create books
    private function createBooks(ObjectManager $manager,Generator $faker,array $categories, array $authors):array
    {
        // Initialize the array to store books
        $books = [];

        for ($i = 0; $i < self::NB_BOOKS; $i++) {
            $book = new Book();
            $book->setTitle($faker->sentence(3));
            $book->setDescription($faker->text(500));

            // Create a DateTime object for the published year
            //$publishedYear = $faker->dateTimeBetween('-30 years', 'now');
            try {
                $publishedYear = new DateTime($faker->date('Y-m-d', '-30 years'));
            } catch (\Exception) {
                // Handle the exception by using a default date
                $publishedYear = new DateTime('-30 years');
            }
            $book->setPublishedYear($publishedYear);

            $book->setCategory($faker->randomElement($categories));
            // Set the availability 80%  of the book
            $book->setAvailable($faker->boolean(80));

            // Add an ISBN
            $book->setISBN($this->generateIsbn($faker));
            // Add a book cover image
            $book->setImage($this->getRandomBookCover($faker));


            // Add multiple authors to the book
            $numberOfAuthors = $faker->numberBetween(1, 3);
            $selectedAuthors = $faker->randomElements($authors, $numberOfAuthors);
            foreach ($selectedAuthors as $author) {
                // Associate the author with the book
                $book->addAuthor($author);
            }

            $manager->persist($book);
            $books[] = $book;
        }
        return $books;
    }

    private function generateIsbn(Generator $faker): string
    {
        // Generate a valid ISBN-13
        $isbn = '978' . $faker->numerify('#########');
        $weightedSum = 0;

        // Calculate the weighted sum of the first 12 digits
        for ($i = 0; $i < 12; $i++) {
            // Updated to use type casting
            $digit = (int) $isbn[$i];
            // Alternate weights of 1 and 3
            $weightedSum += ($i % 2 === 0) ? $digit : $digit * 3;
        }

        // Calculate the check digit
        $checkDigit = (10 - ($weightedSum % 10)) % 10;

        // Return the complete ISBN-13 with the check digit
        return $isbn . $checkDigit;
    }

    private function getRandomBookCover(Generator $faker): string
    {
        $width = 300;
        $height = 400;
        $randomId = $faker->numberBetween(1, 1000);
        return "https://picsum.photos/id/$randomId/$width/$height";
    }

    private function generateUniqueUsername(Generator $faker, string $firstName, string $lastName): string
    {
        // Start with the first letter of the first name and the full last name
        $username = strtolower($firstName[0] . $lastName);

        // Remove any non-alphanumeric characters
        $username = preg_replace('/[^a-z0-9]/', '', $username);

        // If this username is already taken, add random digits until it's unique
        while (in_array($username, $this->usedUsernames,true)) {
            $username .= $faker->randomDigit();
        }

        // Add this username to the list of used usernames
        $this->usedUsernames[] = $username;

        return $username;
    }

    private function createLoans(ObjectManager $manager, Generator $faker, array $users): array
    {
        $loans = [];
        for ($i = 0; $i < self::NB_LOANS; $i++) {
            $loan = new Loan();

            // Set loan date within the last 6 months
            $loanDate = $faker->dateTimeBetween('-6 months');
            $loan->setLoanDate($loanDate);

            // Set due date 3 weeks after loan date
            $dueDate = (clone $loanDate)->modify('+3 weeks');
            $loan->setDueDate($dueDate);

            // 70% chance that the loan has been returned
            if ($faker->boolean(70)) {
                $returnDate = $faker->dateTimeBetween($loanDate);
                $loan->setReturnDate($returnDate);
            } else {
                // Ensure returnDate is set to null if not returned
                $loan->setReturnDate(null);
            }

            $loan->setUser($faker->randomElement($users));

            $manager->persist($loan);
            $loans[] = $loan;
        }

        $manager->flush();
        return $loans;
    }

    private function createBookLoans(ObjectManager $manager, Generator $faker, array $books, array $loans): void
    {
        foreach ($loans as $loan) {
            // Choose a random number of books for this loan (1 to 3)
            $numberOfBooks = $faker->numberBetween(1, 3);

            // Get available books
            $availableBooks = array_filter($books, static function($book) {
                return $book->isAvailable();
            });

            // If there are not enough available books, skip to the next loan
            if (count($availableBooks) < $numberOfBooks) {
                continue;
            }

            // Select random books
            $selectedBooks = $faker->randomElements($availableBooks, $numberOfBooks);

            foreach ($selectedBooks as $book) {
                $bookLoan = new BookLoan();
                $bookLoan->setBook($book);
                $bookLoan->setLoan($loan);

                $book->addBookLoan($bookLoan);
                $loan->addBookLoan($bookLoan);

                $book->setAvailable(false);

                $manager->persist($bookLoan);
            }
        }

        $manager->flush();
    }
}