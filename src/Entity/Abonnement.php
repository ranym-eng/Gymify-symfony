<?php

namespace App\Entity;

use App\Enum\TypeAbonnement;
use App\Repository\AbonnementRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: AbonnementRepository::class)]
class Abonnement
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'id_Abonnement', type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(name: 'type_abonnement', type: 'string', enumType: TypeAbonnement::class)]
    private ?TypeAbonnement $type = null;

    #[ORM\Column]
    #[Assert\NotBlank(message: 'Le tarif est requis.')]
    #[Assert\GreaterThan(
        value: 0,
        message: 'Le tarif doit être supérieur à 0.'
    )]
    #[Assert\LessThanOrEqual(
        value: 10000,
        message: 'Le tarif ne peut pas dépasser 10 000.'
    )]
    #[Assert\Type(
        type: 'numeric',
        message: 'Veuillez entrer un nombre valide pour le tarif.'
    )]
    private ?float $tarif = null;

    #[ORM\ManyToOne(targetEntity: Activité::class, inversedBy: 'abonnements')]
    #[ORM\JoinColumn(name: 'activite_id', referencedColumnName: 'id')]
    private ?Activité $activite = null;

    #[ORM\ManyToOne(targetEntity: Salle::class)]
    #[ORM\JoinColumn(name: 'id_salle', referencedColumnName: 'id')]
    private ?Salle $salle = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getType(): ?TypeAbonnement
    {
        return $this->type;
    }

    public function setType(TypeAbonnement $type): static
    {
        $this->type = $type;
        return $this;
    }

    public function getTarif(): ?float
    {
        return $this->tarif;
    }

    public function setTarif(float $tarif): static
    {
        $this->tarif = $tarif;
        return $this;
    }

    public function getActivite(): ?Activité
    {
        return $this->activite;
    }

    public function setActivite(?Activité $activite): static
    {
        $this->activite = $activite;
        return $this;
    }

    public function getSalle(): ?Salle
    {
        return $this->salle;
    }

    public function setSalle(?Salle $salle): static
    {
        $this->salle = $salle;
        return $this;
    }
}