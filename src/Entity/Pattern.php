<?php

namespace App\Entity;

use App\Repository\PatternRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PatternRepository::class)]
class Pattern
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $title = null;

    #[ORM\Column(nullable: true)]
    private ?int $createdBy = null;

    #[ORM\Column]
    private ?int $shafts = null;

    #[ORM\Column]
    private ?int $treadles = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $updateAt = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private $wif = null;

    /**
     * @var Collection<int, PatternFavorite>
     */
    #[ORM\OneToMany(targetEntity: PatternFavorite::class, mappedBy: 'pattern', orphanRemoval: true)]
    private Collection $patternFavorites;

    public function __construct()
    {
        $this->patternFavorites = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(?string $title): static
    {
        $this->title = $title;

        return $this;
    }

    public function getCreatedBy(): ?int
    {
        return $this->createdBy;
    }

    public function setCreatedBy(?int $createdBy): static
    {
        $this->createdBy = $createdBy;

        return $this;
    }

    public function getShafts(): ?int
    {
        return $this->shafts;
    }

    public function setShafts(int $shafts): static
    {
        $this->shafts = $shafts;

        return $this;
    }

    public function getTreadles(): ?int
    {
        return $this->treadles;
    }

    public function setTreadles(int $treadles): static
    {
        $this->treadles = $treadles;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getUpdateAt(): ?\DateTimeImmutable
    {
        return $this->updateAt;
    }

    public function setUpdateAt(\DateTimeImmutable $updateAt): static
    {
        $this->updateAt = $updateAt;

        return $this;
    }

    public function getWif()
    {
        return $this->wif;
    }

    public function setWif($wif): static
    {
        $this->wif = $wif;

        return $this;
    }

    /**
     * @return Collection<int, PatternFavorite>
     */
    public function getPatternFavorites(): Collection
    {
        return $this->patternFavorites;
    }

    public function addPatternFavorite(PatternFavorite $patternFavorite): static
    {
        if (!$this->patternFavorites->contains($patternFavorite)) {
            $this->patternFavorites->add($patternFavorite);
            $patternFavorite->setPattern($this);
        }

        return $this;
    }

    public function removePatternFavorite(PatternFavorite $patternFavorite): static
    {
        if ($this->patternFavorites->removeElement($patternFavorite)) {
            // set the owning side to null (unless already changed)
            if ($patternFavorite->getPattern() === $this) {
                $patternFavorite->setPattern(null);
            }
        }

        return $this;
    }
}
