<?php

namespace App\Entity;

use App\Enum\EventType;
use App\Enum\Reward;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;

#[ORM\Entity]
#[UniqueEntity(fields: ['nom'], message: 'This event name already exists.')]
class Events
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: 'Event name is required.')]
    #[Assert\Length(max: 255, maxMessage: 'Event name cannot exceed 255 characters.')]
    private ?string $nom = null;

    #[ORM\Column(type: 'text')]
    #[Assert\NotBlank(message: 'Description is required.')]
    #[Assert\Length(max: 500, maxMessage: 'Description cannot exceed 500 characters.')]
    private ?string $description = null;

    #[ORM\Column(type: 'date')]
    #[Assert\NotNull(message: 'Date is required.')]
    #[Assert\GreaterThanOrEqual('today', message: 'The date must be today or in the future.')]
    private ?\DateTimeInterface $date = null;

    #[ORM\Column(type: 'datetime')]
    #[Assert\NotNull(message: 'Start time is required.')]
    private ?\DateTimeInterface $heure_debut = null;

    #[ORM\Column(type: 'datetime')]
    #[Assert\NotNull(message: 'End time is required.')]
    private ?\DateTimeInterface $heure_fin = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $image_url = null;

    #[ORM\Column(type: 'string', enumType: EventType::class)]
    #[Assert\NotNull(message: 'Event type is required.')]
    private ?EventType $type = null;

    #[ORM\Column(type: 'string', enumType: Reward::class)]
    #[Assert\NotNull(message: 'Reward is required.')]
    private ?Reward $reward = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: 'Location is required.')]
    private ?string $lieu = null;

    #[ORM\Column(type: 'float', nullable: true)]
    private ?float $latitude = null;

    #[ORM\Column(type: 'float', nullable: true)]
    private ?float $longitude = null;

    #[ORM\ManyToOne(targetEntity: Salle::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?Salle $salle = null;

    /**
     * @var Collection<int, EquipeEvent>
     */
    #[ORM\OneToMany(targetEntity: EquipeEvent::class, mappedBy: 'event', cascade: ['persist', 'remove'])]
    private Collection $equipeEvents;

    public function __construct()
    {
        $this->equipeEvents = new ArrayCollection();
    }

    #[Assert\Callback]
    public function validateTimes(ExecutionContextInterface $context): void
    {
        if ($this->heure_debut && $this->heure_fin && $this->heure_debut >= $this->heure_fin) {
            $context->buildViolation('Start time must be earlier than end time.')
                ->atPath('heure_debut')
                ->addViolation();
        }
    }

    // Getters and setters
    public function getId(): ?int
    {
        return $this->id;
    }

    public function getNom(): ?string
    {
        return $this->nom;
    }

    public function setNom(string $nom): self
    {
        $this->nom = $nom;
        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(string $description): self
    {
        $this->description = $description;
        return $this;
    }

    public function getDate(): ?\DateTimeInterface
    {
        return $this->date;
    }

    public function setDate(\DateTimeInterface $date): self
    {
        $this->date = $date;
        return $this;
    }

    public function getHeureDebut(): ?\DateTimeInterface
    {
        return $this->heure_debut;
    }

    public function setHeureDebut(\DateTimeInterface $heure_debut): self
    {
        $this->heure_debut = $heure_debut;
        return $this;
    }

    public function getHeureFin(): ?\DateTimeInterface
    {
        return $this->heure_fin;
    }

    public function setHeureFin(\DateTimeInterface $heure_fin): self
    {
        $this->heure_fin = $heure_fin;
        return $this;
    }

    public function getImageUrl(): ?string
    {
        return $this->image_url;
    }

    public function setImageUrl(?string $image_url): self
    {
        $this->image_url = $image_url;
        return $this;
    }

    public function getType(): ?EventType
    {
        return $this->type;
    }

    public function setType(?EventType $type): self
    {
        $this->type = $type;
        return $this;
    }

    public function getReward(): ?Reward
    {
        return $this->reward;
    }

    public function setReward(Reward $reward): self
    {
        $this->reward = $reward;
        return $this;
    }

    public function getLieu(): ?string
    {
        return $this->lieu;
    }

    public function setLieu(string $lieu): self
    {
        $this->lieu = $lieu;
        return $this;
    }

    public function getLatitude(): ?float
    {
        return $this->latitude;
    }

    public function setLatitude(?float $latitude): self
    {
        $this->latitude = $latitude;
        return $this;
    }

    public function getLongitude(): ?float
    {
        return $this->longitude;
    }

    public function setLongitude(?float $longitude): self
    {
        $this->longitude = $longitude;
        return $this;
    }

    public function getSalle(): ?Salle
    {
        return $this->salle;
    }

    public function setSalle(?Salle $salle): self
    {
        $this->salle = $salle;
        return $this;
    }

    /**
     * @return Collection<int, EquipeEvent>
     */
    public function getEquipeEvents(): Collection
    {
        return $this->equipeEvents;
    }

    public function addEquipeEvent(EquipeEvent $equipeEvent): self
    {
        if (!$this->equipeEvents->contains($equipeEvent)) {
            $this->equipeEvents->add($equipeEvent);
            $equipeEvent->setEvent($this);
        }
        return $this;
    }

    public function removeEquipeEvent(EquipeEvent $equipeEvent): self
    {
        if ($this->equipeEvents->removeElement($equipeEvent)) {
            if ($equipeEvent->getEvent() === $this) {
                $equipeEvent->setEvent(null);
            }
        }
        return $this;
    }
}