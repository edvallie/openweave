<?php

namespace App\Entity;

use App\Repository\PatternFavoriteRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PatternFavoriteRepository::class)]
#[ORM\UniqueConstraint(name: 'unique_user_pattern_favorite', columns: ['pattern_id', 'account_id'])]
class PatternFavorite
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'patternFavorites')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Pattern $pattern = null;

    #[ORM\ManyToOne(inversedBy: 'patternFavorites')]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $account = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getPattern(): ?Pattern
    {
        return $this->pattern;
    }

    public function setPattern(?Pattern $pattern): static
    {
        $this->pattern = $pattern;

        return $this;
    }

    public function getAccount(): ?User
    {
        return $this->account;
    }

    public function setAccount(?User $account): static
    {
        $this->account = $account;

        return $this;
    }
}
