<?php

namespace App\Entity;

use App\Repository\CoursRepository;
use App\Enum\ObjectifCours;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use Symfony\Component\Validator\Constraints as Assert;


#[ORM\Entity(repositoryClass: CoursRepository::class)]
class Cours
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: "Le titre est obligatoire.")]
    private ?string $title = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: "La description est obligatoire.")]
    private ?string $description = null;

    #[ORM\Column(type: 'string',enumType: ObjectifCours::class,columnDefinition: "ENUM('PERTE_PROIDS','PRISE_DE_MASSE','ENDURANCE','RELAXATION')")]
    #[Assert\NotNull(message: "L'objectif est obligatoire.")]
    private ?ObjectifCours $objectif = null;

    #[ORM\Column(type: Types::DATE_MUTABLE)]
    #[Assert\NotNull(message: "La date est obligatoire.")]
    private ?\DateTimeInterface $dateDebut = null;

    #[ORM\Column(type: Types::TIME_MUTABLE)]
    #[Assert\NotNull(message: "L'heure de début est obligatoire.")]
    private ?\DateTimeInterface $heurDebut = null;

    #[ORM\Column(type: Types::TIME_MUTABLE)]
    #[Assert\NotNull(message: "L'heure de fin est obligatoire.")]
    private ?\DateTimeInterface $heurFin = null;

    #[ORM\ManyToOne(inversedBy: 'activité')]
    #[Assert\NotNull(message: "L'activité est obligatoire.")]
    private ?Activité $activité = null;

    #[ORM\ManyToOne(inversedBy: 'planning')]
    private ?Planning $planning = null;
    
    #[ORM\ManyToOne(inversedBy: 'cours')]
    private ?User $entaineur = null;

    #[ORM\ManyToOne(inversedBy: 'salle')]
    #[Assert\NotNull(message: "La salle est obligatoire.")]
    private ?Salle $salle = null;

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

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(string $description): static
    {
        $this->description = $description;

        return $this;
    }

    public function getObjectif(): ?ObjectifCours
    {
        return $this->objectif;
    }

    public function setObjectif(ObjectifCours $objectif): static
    {
        $this->objectif = $objectif;

        return $this;
    }

    public function getDateDebut(): ?\DateTimeInterface
    {
        return $this->dateDebut;
    }

    public function setDateDebut(\DateTimeInterface $dateDebut): static
    {
        $this->dateDebut = $dateDebut;

        return $this;
    }

    public function getHeurDebut(): ?\DateTimeInterface
    {
        return $this->heurDebut;
    }

    public function setHeurDebut(\DateTimeInterface $heurDebut): static
    {
        $this->heurDebut = $heurDebut;

        return $this;
    }

    public function getHeurFin(): ?\DateTimeInterface
    {
        return $this->heurFin;
    }

    public function setHeurFin(\DateTimeInterface $heurFin): static
    {
        $this->heurFin = $heurFin;

        return $this;
    }

    public function getActivité(): ?Activité
    {
        return $this->activité;
    }

    public function setActivité(?Activité $activité): static
    {
        $this->activité = $activité;

        return $this;
    }

    public function getPlanning(): ?Planning
    {
        return $this->planning;
    }

    public function setPlanning(?Planning $planning): static
    {
        $this->planning = $planning;

        return $this;
    }

    public function getEntaineur(): ?User
    {
        return $this->entaineur;
    }

    public function setEntaineur(?User $entaineur): static
    {
        $this->entaineur = $entaineur;

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
    public function validateTimes(ExecutionContextInterface $context): void
    {
        if ($this->heurDebut instanceof \DateTimeInterface && $this->heurFin instanceof \DateTimeInterface) {
            if ($this->heurFin <= $this->heurDebut) {
                $context->buildViolation('L\'heure de fin doit être postérieure à l\'heure de début.')
                    ->atPath('heurFin')
                    ->addViolation();
            }
        }
    }

}
