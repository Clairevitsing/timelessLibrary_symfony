<?php

namespace App\Entity;

use App\Repository\LoanRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

#[ORM\Entity(repositoryClass: LoanRepository::class)]
class Loan
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['loan:read', 'user:read'])]
    private ?int $id = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    #[Groups(['loan:read', 'user:read'])]
    private ?\DateTimeInterface $loanDate = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    #[Groups(['loan:read', 'user:read'])]
    private ?\DateTimeInterface $dueDate = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable:true)]
    #[Groups(['loan:read', 'user:read'])]
    private ?\DateTimeInterface $returnDate = null;

    #[ORM\ManyToOne(targetEntity: User::class,inversedBy: 'loans', cascade:['persist', 'remove'])]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['loan:read'])]
    private ?User $user = null;


    /**
     * @var Collection<int, BookLoan>
     */
    #[ORM\OneToMany(targetEntity: BookLoan::class, mappedBy: 'loan', orphanRemoval: true)]
    #[Groups(['loan:read'])]
    private Collection $bookLoans;

    public function __construct()
    {
        $this->bookLoans = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getLoanDate(): ?\DateTimeInterface
    {
        return $this->loanDate;
    }

    public function setLoanDate(\DateTimeInterface $loanDate): static
    {
        $this->loanDate = $loanDate;

        return $this;
    }

    public function getDueDate(): ?\DateTimeInterface
    {
        return $this->dueDate;
    }

    public function setDueDate(\DateTimeInterface $dueDate): static
    {
        $this->dueDate = $dueDate;

        return $this;
    }

    public function getReturnDate(): ?\DateTimeInterface
    {
        return $this->returnDate;
    }

    public function setReturnDate(?\DateTimeInterface $returnDate): static
    {
        $this->returnDate = $returnDate;

        return $this;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): static
    {
        $this->user = $user;

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
        }

        return $this;
    }

    public function removeBook(Book $book): static
    {
        $this->books->removeElement($book);

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
            $bookLoan->setLoan($this);
        }

        return $this;
    }

    public function removeBookLoan(BookLoan $bookLoan): static
    {
        if ($this->bookLoans->removeElement($bookLoan)) {
            // set the owning side to null (unless already changed)
            if ($bookLoan->getLoan() === $this) {
                $bookLoan->setLoan(null);
            }
        }

        return $this;
    }
}
