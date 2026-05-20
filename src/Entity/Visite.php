<?php

namespace App\Entity;

use App\Repository\VisiteRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: VisiteRepository::class)]
class Visite
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $nomVisiteur = null;

    #[ORM\Column(length: 100)]
    private ?string $institutionHote = null;

    #[ORM\Column]
    private ?\DateTime $heureRdv = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $motif = null;

    #[ORM\Column(length: 50)]
    private ?string $statut = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getNomVisiteur(): ?string
    {
        return $this->nomVisiteur;
    }

    public function setNomVisiteur(string $nomVisiteur): static
    {
        $this->nomVisiteur = $nomVisiteur;

        return $this;
    }

    public function getInstitutionHote(): ?string
    {
        return $this->institutionHote;
    }

    public function setInstitutionHote(string $institutionHote): static
    {
        $this->institutionHote = $institutionHote;

        return $this;
    }

    public function getHeureRdv(): ?\DateTime
    {
        return $this->heureRdv;
    }

    public function setHeureRdv(\DateTime $heureRdv): static
    {
        $this->heureRdv = $heureRdv;

        return $this;
    }

    public function getMotif(): ?string
    {
        return $this->motif;
    }

    public function setMotif(?string $motif): static
    {
        $this->motif = $motif;

        return $this;
    }

    public function getStatut(): ?string
    {
        return $this->statut;
    }

    public function setStatut(string $statut): static
    {
        $this->statut = $statut;

        return $this;
    }
}
