<?php

namespace App\Entity;

use App\Repository\BookLoanRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

#[ORM\Entity(repositoryClass: BookLoanRepository::class)]
class BookLoan
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['bookLoan:read','book:read','loan:read' ])]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'bookLoans')]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['bookLoan:read','loan:read' ])]
    private ?Book $book = null;

    #[ORM\ManyToOne(inversedBy: 'bookLoans')]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['bookLoan:read','book:read','loan:read' ])]
    private ?Loan $loan = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getBook(): ?Book
    {
        return $this->book;
    }

    public function setBook(?Book $book): static
    {
        $this->book = $book;

        return $this;
    }

    public function getLoan(): ?Loan
    {
        return $this->loan;
    }

    public function setLoan(?Loan $loan): static
    {
        $this->loan = $loan;

        return $this;
    }

}
