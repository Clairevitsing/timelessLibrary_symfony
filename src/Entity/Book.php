<?php

namespace App\Entity;

use App\Repository\BookRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Serializer\Attribute\MaxDepth;

#[ORM\Entity(repositoryClass: BookRepository::class)]
class Book
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['book:read','author:read','category:read','bookLoan:read','loan:read'])]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Groups(['book:read', 'author:read','category:read','bookLoan:read','loan:read'])]
    private ?string $title = null;

    #[ORM\Column(length: 100)]
    #[Groups(['book:read', 'author:read','category:read','bookLoan:read','loan:read'])]
    private ?string $ISBN = null;

    #[ORM\Column(type: Types::DATE_MUTABLE)]
    #[Groups(['book:read', 'author:read','category:read','bookLoan:read','loan:read'])]
    private ?\DateTimeInterface $publishedYear = null;

    #[ORM\Column(type: Types::TEXT)]
    #[Groups(['book:read', 'author:read','category:read','bookLoan:read','loan:read'])]
    private ?string $description = null;

    #[ORM\Column(type: Types::TEXT)]
    #[Groups(['book:read', 'author:read','category:read','bookLoan:read','loan:read'])]
    private ?string $image = null;

    #[ORM\Column]
    #[Groups(['book:read', 'author:read','category:read','bookLoan:read','loan:read'])]
    private ?bool $available = false;

    /**
     * @var Collection<int, Author>
     */
    #[ORM\ManyToMany(targetEntity: Author::class, mappedBy: 'books',cascade: ['persist'], orphanRemoval: true)]
    //#[ORM\ManyToMany(targetEntity: Author::class, mappedBy: 'books', cascade: ["remove"])]
    #[Groups(['book:read','category:read','bookLoan:read'])]
    #[MaxDepth(1)]
    private Collection $authors;

    #[ORM\ManyToOne(targetEntity: Category::class, inversedBy: 'books',cascade: ['persist'])]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['book:read', 'author:read','bookLoan:read'])]
    private ?Category $category = null;

    /**
     * @var Collection<int, BookLoan>
     */
    #[ORM\OneToMany(targetEntity: BookLoan::class, mappedBy: 'book',orphanRemoval: true)]
    private Collection $bookLoans;

    public function __construct()
    {
        $this->authors = new ArrayCollection();
        $this->bookLoans = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(string $title): static
    {
        $this->title = $title;

        return $this;
    }

    public function getISBN(): ?string
    {
        return $this->ISBN;
    }

    public function setISBN(string $ISBN): static
    {
        $this->ISBN = $ISBN;

        return $this;
    }

    public function getPublishedYear(): ?\DateTimeInterface
    {
        return $this->publishedYear;
    }

    public function setPublishedYear(\DateTimeInterface $publishedYear): static
    {
        $this->publishedYear = $publishedYear;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(string $description): static
    {
        $this->description = $description;

        return $this;
    }

    public function getImage(): ?string
    {
        return $this->image;
    }

    public function setImage(string $image): static
    {
        $this->image = $image;

        return $this;
    }

    public function isAvailable(): bool
    {
        return $this->available;
    }

    //Sets the availability status of the book.
    public function setAvailable(bool $available): static
    {
        $this->available = $available;

        return $this;
    }

    public function updateAvailability(): void
    {
        $this->available = $this->calculateAvailability();
    }

    //Calculates whether the book is available based on the current loans.
    //If there is an active loan (i.e., a loan with no return date), the book is considered unavailable.
    private function calculateAvailability(): bool
    {
        foreach ($this->bookLoans as $bookLoan) {
            $loan = $bookLoan->getLoan();
            if ($loan && $loan->getReturnDate() === null) {
                return false;
            }
        }
        return true;
    }

    public function getAuthors(): Collection
    {
        return $this->authors;
    }

    public function addAuthor(Author $author): static
    {
        if (!$this->authors->contains($author)) {
            $this->authors->add($author);
            $author->addBook($this);
        }

        return $this;
    }

    public function removeAuthor(Author $author): static
    {
        if ($this->authors->removeElement($author)) {
            $author->removeBook($this);
        }

        return $this;
    }

    public function getCategory(): ?Category
    {
        return $this->category;
    }

    public function setCategory(?Category $category): static
    {
        $this->category = $category;

        return $this;
    }

    /**
     * @return Collection<int, BookLoan>
     */
    public function getBookLoans(): Collection
    {
        return $this->bookLoans;
    }

    public function addBookLoan(BookLoan $bookLoan): static
    {
        if (!$this->bookLoans->contains($bookLoan)) {
            $this->bookLoans->add($bookLoan);
            $bookLoan->setBook($this);
        }

        return $this;
    }

    public function removeBookLoan(BookLoan $bookLoan): static
    {
        if ($this->bookLoans->removeElement($bookLoan)) {
            // set the owning side to null (unless already changed)
            if ($bookLoan->getBook() === $this) {
                $bookLoan->setBook(null);
            }
        }

        return $this;
    }
}
