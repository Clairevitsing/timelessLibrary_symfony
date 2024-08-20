<?php

namespace App\Entity;

use App\Repository\AuthorRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

#[ORM\Entity(repositoryClass: AuthorRepository::class)]
class Author
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['author:read', 'book:read'])]
    private ?int $id = null;

    #[ORM\Column(length: 100)]
    #[Groups(['author:read', 'book:read'])]
    private ?string $firstName = null;

    #[ORM\Column(length: 100)]
    #[Groups(['author:read', 'book:read'])]
    private ?string $lastName = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    #[Groups(['author:read', 'book:read'])]
    private ?\DateTimeInterface $birthDate = null;

    #[ORM\Column(type: Types::TEXT)]
    #[Groups(['author:read', 'book:read'])]
    private ?string $biography = null;

    /**
     * @var Collection<int, Book>
     */
    #[ORM\ManyToMany(targetEntity: Book::class, mappedBy:'authors', cascade: ["persist"], orphanRemoval: true),]
    #[Groups(['author:read'])]
    private Collection $books;

    public function __construct()
    {
        $this->books = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getFirstName(): ?string
    {
        return $this->firstName;
    }

    public function setFirstName(string $firstName): static
    {
        $this->firstName = $firstName;

        return $this;
    }

    public function getLastName(): ?string
    {
        return $this->lastName;
    }

    public function setLastName(string $lastName): static
    {
        $this->lastName = $lastName;

        return $this;
    }

    public function getBirthDate(): ?\DateTimeInterface
    {
        return $this->birthDate;
    }

    public function setBirthDate(\DateTimeInterface $birthDate): static
    {
        $this->birthDate = $birthDate;

        return $this;
    }

    public function getBiography(): ?string
    {
        return $this->biography;
    }

    public function setBiography(string $biography): static
    {
        $this->biography = $biography;

        return $this;
    }

    /**
     * @return Collection<int, Book>
     */
    public function getBooks(): Collection
    {
        return $this->books;
    }

    public function addBook(Book $book): static
    {
        if (!$this->books->contains($book)) {
            $this->books->add($book);
            $book->addAuthor($this);
        }

        return $this;
    }

    public function removeBook(Book $book): static
    {
        $this->books->removeElement($book);
        $book->removeAuthor($this);

        return $this;
    }
}
