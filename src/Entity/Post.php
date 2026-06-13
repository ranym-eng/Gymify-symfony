<?php

namespace App\Entity;

use App\Repository\PostRepository;
use App\Entity\User;  // Ajout de l'entité User
use App\Entity\Comment;  // Ajout de l'entité Comment
use App\Entity\Reactions;
use Doctrine\DBAL\Types\Types;
use Symfony\Component\Validator\Constraints as Assert;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

#[ORM\Entity(repositoryClass: PostRepository::class)]
class Post
{
    #[ORM\Id]
#[ORM\GeneratedValue(strategy: "AUTO")]
#[ORM\Column(type: "integer")]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
#[Assert\NotBlank(message: 'Le titre est obligatoire.')]
#[Assert\Length(
    min: 3,
    max: 100,
    minMessage: 'Le titre doit contenir au moins {{ limit }} caractères.',
    maxMessage: 'Le titre ne peut pas dépasser {{ limit }} caractères.'
)]
#[Assert\Regex(
    pattern: '/^(?!.*\b(spam|arnaque|insulte)\b).*/i',
    message: 'Le titre contient un mot interdit.'
)]
private ?string $title = null;

#[ORM\Column(type: Types::TEXT)] // Changement de TEXT pour supporter le HTML
#[Assert\NotBlank(message: 'Le contenu est obligatoire.')]
#[Assert\Regex(
    pattern: '/\b(spam|arnaque|insulte)\b/i',
    message: 'Contenu inapproprié détecté !',
    match: false
)]
private ?string $content = null;


#[ORM\Column(length: 255, nullable: true)]
#[Assert\Url(message: "Veuillez saisir une URL valide (http(s)://...) pour l'image.")]
#[Assert\Regex(
    pattern: '/\.(jpg|jpeg|png|gif)$/i',
    message: "L'URL de l'image doit se terminer par .jpg, .jpeg, .png ou .gif"
)]
private ?string $image_url = null;




    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $createdAt = null;

    // Relation ManyToOne avec User
    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'posts')]
    #[ORM\JoinColumn(name: 'id_User', referencedColumnName: 'id')]
    private ?User $user = null;  // Cette propriété représente l'utilisateur auquel le post appartient.

    // Relation OneToMany avec Comment
    #[ORM\OneToMany(mappedBy: 'post', targetEntity: Comment::class)]
    private Collection $comments;
    
    //reactions
    //#[ORM\OneToMany(mappedBy: 'post', targetEntity: Reactions::class, cascade: ['persist', 'remove'])]
    #[ORM\OneToMany(mappedBy: 'post', targetEntity: Reactions::class, orphanRemoval: true)]
private Collection $reactions;


  

    
    public function __construct()
    {
        $this->comments = new ArrayCollection();
        $this->reactions = new ArrayCollection();

       
        
    }

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

    public function getContent(): ?string
    {
        return $this->content;
    }

    public function setContent(string $content): static
    {
        $this->content = $content;
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

    public function getCreatedAt(): ?\DateTimeInterface
    {
        return $this->createdAt;
    }

    public function setCreatedAt(?\DateTimeInterface $createdAt): static
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    // Getter et Setter pour la relation ManyToOne avec User
    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): static
    {
        $this->user = $user;
        return $this;
    }

    // Getter et Setter pour la relation OneToMany avec Comment
    public function getComments(): Collection
    {
        return $this->comments;
    }

    public function addComment(Comment $comment): static
    {
        if (!$this->comments->contains($comment)) {
            $this->comments->add($comment);
            $comment->setPost($this);  // Associe ce post au commentaire
        }
        return $this;
    }

    public function removeComment(Comment $comment): static
    {
        if ($this->comments->removeElement($comment)) {
            // Désassocie ce post du commentaire
            if ($comment->getPost() === $this) {
                $comment->setPost(null);
            }
        }
        return $this;
    }


   /**
     * @return Collection|Reactions[]
     */
    public function getReactions(): Collection
    {
        return $this->reactions;
    }

public function addReaction(Reactions $reaction): static
{
    if (!$this->reactions->contains($reaction)) {
        $this->reactions->add($reaction);
        $reaction->setPost($this);
    }

    return $this;
}

public function removeReaction(Reactions $reaction): static
{
    if ($this->reactions->removeElement($reaction)) {
        if ($reaction->getPost() === $this) {
            $reaction->setPost(null);
        }
    }

    return $this;
}

  



   /**
     * Renvoie un array ['like' => 3, 'love' => 1, …]
     */
    public function getReactionsCountByType(): array
    {
        $counts = array_fill_keys(array_keys(Reactions::TYPES), 0);
        foreach ($this->reactions as $r) {
            $type = $r->getType();
            if (isset($counts[$type])) {
                $counts[$type]++;
            }
        }
        return $counts;
    }

    /**
     * Renvoie la réaction de cet utilisateur ou null
     */
    public function getReactionByUser(User $user): ?Reactions
    {
        foreach ($this->reactions as $r) {
            if ($r->getUser() === $user) {
                return $r;
            }
        }
        return null;
    }

    /**
     * Renvoie le type de réaction de l'utilisateur ou null
     */
    public function userReaction(User $user): ?string
    {
        $reaction = $this->getReactionByUser($user);
        return $reaction ? $reaction->getType() : null;
    }





    


















public function getWebPath(): ?string
{
    if ($this->image_url === null) {
        return null;
    }

    // Normalise le chemin (Windows -> compatible avec URL)
    $path = str_replace('\\', '/', $this->image_url);

    // Enlève la partie absolue jusqu'à "public"
    $publicPos = strpos($path, '/public');

    if ($publicPos !== false) {
        return substr($path, $publicPos + 7); // 7 = longueur de "/public"
    }

    // Si le chemin ne contient pas "public", on retourne tel quel
    return $path;
}









}