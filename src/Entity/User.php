<?php

namespace App\Entity;

use App\Enum\Role;
use App\Repository\UserRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: "user")]
#[UniqueEntity("email", message: "L'email {{ value }} est déjà utilisé.")]
#[ORM\InheritanceType("SINGLE_TABLE")]
#[ORM\DiscriminatorColumn(name: "role", type: "string")]
#[ORM\DiscriminatorMap([
    "sportif" => Sportif::class,
    "admin" => Admin::class,
    "responsable_salle" => ResponsableSalle::class,
    "entraineur" => Entraineur::class
])]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 50)]
    private ?string $nom = null;

    #[ORM\Column(length: 50)]
    private ?string $prenom = null;

    #[ORM\Column(length: 100, unique: true)]
    #[Assert\NotBlank(message: "L'email est obligatoire.")]
    #[Assert\Email(message: "Veuillez entrer une adresse email valide.")]
    private ?string $email = null;

    #[ORM\Column(length: 255)]
    private ?string $password = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $imageUrl = null;

    #[ORM\Column(type: Types::DATE_MUTABLE)]
    private ?\DateTimeInterface $dateNaissance = null;

    #[ORM\Column(type: 'string', length: 100, nullable: true)]
    private ?string $specialite = null;

    #[ORM\OneToMany(mappedBy: 'user', targetEntity: Post::class)]
    private Collection $posts;

    #[ORM\OneToMany(mappedBy: 'user', targetEntity: Comment::class)]
    private Collection $comments;

    #[ORM\OneToMany(mappedBy: 'entraineur', targetEntity: Cours::class)]
    private Collection $cours;

    #[ORM\OneToMany(mappedBy: 'entraineur', targetEntity: Planning::class)]
    private Collection $plannings;
    #[ORM\OneToMany(mappedBy: 'sportif', targetEntity: Infosportif::class)]
    private Collection $Infosportifs;

    #[ORM\OneToMany(mappedBy: 'user', targetEntity: Reactions::class, cascade: ['persist', 'remove'])]
    private Collection $reactions;

    /**
     * @var Collection<int, Infosportif>
     */
    #[ORM\OneToMany(targetEntity: Infosportif::class, mappedBy: 'sportif')]
    private Collection $sportif;

    #[ORM\ManyToOne(inversedBy: 'equipes')]
    private ?Equipe $equipe = null;

    public function __construct()
    {
        $this->posts = new ArrayCollection();
        $this->comments = new ArrayCollection();
        $this->cours = new ArrayCollection();
        $this->plannings = new ArrayCollection();
        $this->reactions = new ArrayCollection();
        $this->sportif = new ArrayCollection();
        $this->infosportifs = new ArrayCollection();

    

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

    public function getPrenom(): ?string
    {
        return $this->prenom;
    }

    public function setPrenom(string $prenom): static
    {
        $this->prenom = $prenom;
        return $this;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(string $email): static
    {
        $this->email = $email;
        return $this;
    }

    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function setPassword(string $password): static
    {
        $this->password = $password;
        return $this;
    }

    public function getImageUrl(): ?string
    {
        return $this->imageUrl;
    }

    public function setImageUrl(?string $imageUrl): static
    {
        $this->imageUrl = $imageUrl;
        return $this;
    }

    public function getSpecialite(): ?string
    {
        return $this->specialite;
    }

    public function setSpecialite(?string $specialite): static
    {
        $this->specialite = $specialite;
        return $this;
    }

    public function getDateNaissance(): ?\DateTimeInterface
    {
        return $this->dateNaissance;
    }

    public function setDateNaissance(\DateTimeInterface $dateNaissance): static
    {
        $this->dateNaissance = $dateNaissance;
        return $this;
    }

    /**
     * @return Collection<int, Cours>
     */
    public function getCours(): Collection
    {
        return $this->cours;
    }

    public function addCours(Cours $cours): static
    {
        if (!$this->cours->contains($cours)) {
            $this->cours[] = $cours;
            $cours->setEntraineur($this);
        }
        return $this;
    }

    public function removeCours(Cours $cours): static
    {
        if ($this->cours->removeElement($cours) && $cours->getEntraineur() === $this) {
            $cours->setEntraineur(null);
        }
        return $this;
    }

    public function getPlannings(): Collection
    {
        return $this->plannings;
    }

    public function addPlanning(Planning $planning): static
    {
        if (!$this->plannings->contains($planning)) {
            $this->plannings[] = $planning;
            $planning->setEntraineur($this);
        }
        return $this;
    }

    public function removePlanning(Planning $planning): static
    {
        if ($this->plannings->removeElement($planning) && $planning->getEntraineur() === $this) {
            $planning->setEntraineur(null);
        }
        return $this;
    }

    public function getReactions(): Collection
    {
        return $this->reactions;
    }

    public function addReaction(Reactions $reaction): static
    {
        if (!$this->reactions->contains($reaction)) {
            $this->reactions[] = $reaction;
            $reaction->setUser($this);
        }
        return $this;
    }

    public function removeReaction(Reactions $reaction): static
    {
        if ($this->reactions->removeElement($reaction) && $reaction->getUser() === $this) {
            $reaction->setUser(null);
        }
        return $this;
    }

    public function getEquipe(): ?Equipe
    {
        return $this->equipe;
    }

    public function setEquipe(?Equipe $equipe): static
    {
        $this->equipe = $equipe;
        return $this;
    }

    public function getRole(): Role
    {
        $class = get_class($this);
        return match ($class) {
            Sportif::class => Role::SPORTIF,
            Admin::class => Role::ADMIN,
            ResponsableSalle::class => Role::RESPONSABLE_SALLE,
            Entraineur::class => Role::ENTRAINEUR,
            default => throw new \LogicException("Unexpected class name $class"),
        };
    }

    public function setRole(Role $role): static
    {
        return $this;
    }

    public function getRoles(): array
    {
        $roles = ['ROLE_USER'];
        
        if ($this instanceof Admin) {
            $roles[] = 'ROLE_ADMIN';
        } elseif ($this instanceof ResponsableSalle) {
            $roles[] = 'ROLE_RESPONSABLE_SALLE';
        } elseif ($this instanceof Entraineur) {
            $roles[] = 'ROLE_ENTRAINEUR';
        } elseif ($this instanceof Sportif) {
            $roles[] = 'ROLE_SPORTIF';
        }
        
        return $roles;
    }

    public function eraseCredentials(): void
    {
        // Il n'est pas utile d'effacer les données sensibles dans cette implémentation.
    }

    public function getUserIdentifier(): string
    {
        return $this->email;
    }

    /**
     * @return Collection<int, Infosportif>
     */
    public function getSportif(): Collection
    {
        return $this->sportif;
    }

    public function addSportif(Infosportif $sportif): static
    {
        if (!$this->sportif->contains($sportif)) {
            $this->sportif->add($sportif);
            $sportif->setSportif($this);
        }

        return $this;
    }

    public function removeSportif(Infosportif $sportif): static
    {
        if ($this->sportif->removeElement($sportif)) {
            // set the owning side to null (unless already changed)
            if ($sportif->getSportif() === $this) {
                $sportif->setSportif(null);
            }
        }

        return $this;
    }

    #[ORM\Column(type: 'boolean', options: ['default' => false])]
    private bool $isBlocked = false;

    public function isBlocked(): bool
    {
        return $this->isBlocked;
    }

    public function setIsBlocked(bool $isBlocked): self
    {
        $this->isBlocked = $isBlocked;
        return $this;
    }

    #[ORM\Column(type: 'string', length: 100, nullable: true)]
    private ?string $resetToken = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $resetTokenExpiration = null;

    public function getResetToken(): ?string
    {
        return $this->resetToken;
    }

    public function setResetToken(?string $resetToken): static
    {
        $this->resetToken = $resetToken;
        return $this;
    }

    public function getResetTokenExpiration(): ?\DateTimeInterface
    {
        return $this->resetTokenExpiration;
    }

    public function setResetTokenExpiration(?\DateTimeInterface $resetTokenExpiration): static
    {
        $this->resetTokenExpiration = $resetTokenExpiration;
        return $this;
    }
    
    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $googleId = null;
    
    public function getGoogleId(): ?string
    {
        return $this->googleId;
    }
    
    public function setGoogleId(?string $googleId): self
    {
        $this->googleId = $googleId;
        return $this;
    }
}

