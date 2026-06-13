<?php

namespace App\Entity;

use App\Repository\CommentRepository;
use App\Entity\Post;  // Importation de l'entité Post
use App\Entity\User;  // Importation de l'entité User
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;




#[ORM\Entity(repositoryClass: CommentRepository::class)]
class Comment
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: 'Le commentaire ne peut pas être vide.')]
    #[Assert\Length(
        min: 3,
        minMessage: 'Le commentaire doit contenir au moins {{ limit }} caractères.'
    )]
    #[Assert\Regex(
        pattern: '/\b(arnaque|insulte|escroc|arnaquer|injure)\b/i',
        match: false,
        message: 'Ce commentaire contient des mots inappropriés.'
    )]
    private ?string $content = null;

    #[ORM\Column(name: 'createdAt', type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $createdAt = null;



     // Relation ManyToOne avec Post, name matches exact database column name
     #[ORM\ManyToOne(targetEntity: Post::class, inversedBy: 'comments')]
     #[ORM\JoinColumn(name: 'postId', referencedColumnName: 'id', nullable: false)]
     private ?Post $post = null;
 
     // Relation ManyToOne avec User, name matches exact database column name
     #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'comments')]
     #[ORM\JoinColumn(name: 'id_User', referencedColumnName: 'id', nullable: false)]
     private ?User $user = null;
 
    public function getId(): ?int
    {
        return $this->id;
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

    public function getCreatedAt(): ?\DateTimeInterface
    {
        return $this->createdAt;
    }

    public function setCreatedAt(?\DateTimeInterface $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }
  // Getter et Setter pour la relation ManyToOne avec Post
  public function getPost(): ?Post
  {
      return $this->post;
  }

  public function setPost(?Post $post): static
  {
      $this->post = $post;
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

  /**
   * Vérifie si le commentaire est sensible
   */
  public function isSensitive(): bool
  {
      return strpos($this->content, '[SENSITIVE]') === 0;
  }
  
  /**
   * Récupère le contenu original (sans le préfixe [SENSITIVE])
   */
  public function getOriginalContent(): ?string
  {
      if ($this->isSensitive()) {
          return substr($this->content, 11); // Enlever le préfixe '[SENSITIVE]'
      }
      
      return $this->content;
  }
  
  /**
   * Récupère le contenu à afficher (avec avertissement pour les commentaires sensibles)
   */
  public function getDisplayContent(): ?string
  {
      if ($this->isSensitive()) {
          return '⚠️ Votre commentaire contient un contenu sensible. Veuillez le modifier si nécessaire.';
      }
      
      return $this->content;
  }
}
