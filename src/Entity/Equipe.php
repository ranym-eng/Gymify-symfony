<?php

namespace App\Entity;

use App\Enum\Niveau;
use App\Repository\EquipeRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;

#[ORM\Entity(repositoryClass: EquipeRepository::class)]
#[UniqueEntity(fields: ['nom'], message: 'Team name already exists.')]
class Equipe
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: 'Team name is required.')]
    #[Assert\Length(max: 255, maxMessage: 'Team name cannot exceed 255 characters.')]
    private ?string $nom = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $image_url = null;

    #[ORM\Column(type: 'string', enumType: Niveau::class)]
    #[Assert\NotNull(message: 'Level is required.')]
    private ?Niveau $niveau = null;

    #[ORM\Column]
    #[Assert\NotNull(message: 'Number of members is required.')]
    #[Assert\GreaterThanOrEqual(value: 0, message: 'Number of members must be 0 or greater.')]
    #[Assert\LessThanOrEqual(value: 8, message: 'Number of members cannot exceed 8.')]
    private ?int $nombre_membres = null;

    /**
     * @var Collection<int, EquipeEvent>
     */
    #[ORM\OneToMany(targetEntity: EquipeEvent::class, mappedBy: 'equipe', cascade: ['persist', 'remove'])]
    private Collection $equipeEvents;

    /**
     * @var Collection<int, User>
     */
    #[ORM\OneToMany(targetEntity: User::class, mappedBy: 'equipe')]
    private Collection $users;

    public function __construct()
    {
        $this->equipeEvents = new ArrayCollection();
        $this->users = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getNom(): ?string
    {
        return $this->nom;
    }

    public function setNom(string $nom): static
    {
        $this->nom = $nom;
        return $this;
    }

    public function getImageUrl(): ?string
    {
        return $this->image_url;
    }

    public function setImageUrl(?string $image_url): static
    {
        $this->image_url = $image_url;
        return $this;
    }

    public function getNiveau(): ?Niveau
    {
        return $this->niveau;
    }

    public function setNiveau(Niveau $niveau): static
    {
        $this->niveau = $niveau;
        return $this;
    }

    public function getNombreMembres(): ?int
    {
        return $this->nombre_membres;
    }

    public function setNombreMembres(int $nombre_membres): static
    {
        $this->nombre_membres = $nombre_membres;
        return $this;
    }

    /**
     * @return Collection<int, EquipeEvent>
     */
    public function getEquipeEvents(): Collection
    {
        return $this->equipeEvents;
    }

    public function addEquipeEvent(EquipeEvent $equipeEvent): static
    {
        if (!$this->equipeEvents->contains($equipeEvent)) {
            $this->equipeEvents->add($equipeEvent);
            $equipeEvent->setEquipe($this);
        }
        return $this;
    }

    public function removeEquipeEvent(EquipeEvent $equipeEvent): static
    {
        if ($this->equipeEvents->removeElement($equipeEvent)) {
            if ($equipeEvent->getEquipe() === $this) {
                $equipeEvent->setEquipe(null);
            }
        }
        return $this;
    }

    /**
     * @return Collection<int, User>
     */
    public function getUsers(): Collection
    {
        return $this->users;
    }

    public function addUser(User $user): static
    {
        if (!$this->users->contains($user)) {
            $this->users->add($user);
            $user->setEquipe($this);
        }
        return $this;
    }

    public function removeUser(User $user): static
    {
        if ($this->users->removeElement($user)) {
            if ($user->getEquipe() === $this) {
                $user->setEquipe(null);
            }
        }
        return $this;
    }
}