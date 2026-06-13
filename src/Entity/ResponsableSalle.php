<?php

namespace App\Entity;

use App\Enum\Role;
use App\Repository\ResponsableSalleRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ResponsableSalleRepository::class)]
class ResponsableSalle extends User
{
    #[ORM\OneToOne(mappedBy: 'responsable', targetEntity: Salle::class)]
    private ?Salle $salle = null;

    public function __construct()
    {
        parent::__construct();
        // Passez l'objet Role::SPORTIF ici
       $this->setRole(Role::RESPONSABLE_SALLE);  // Définit le rôle comme responsable de salle
    }

    /**
     * Récupère la salle associée au responsable.
     *
     * @return Salle|null
     */
    public function getSalle(): ?Salle
    {
        return $this->salle;
    }

    /**
     * Associe une salle au responsable de salle.
     *
     * @param Salle|null $salle
     * @return self
     */
    public function setSalle(?Salle $salle): static
    {
        // Si la salle est dissociée, nous mettons à null le responsable dans la salle
        if ($salle === null && $this->salle !== null) {
            $this->salle->setResponsable(null);
        }

        // Si la salle est assignée, nous nous assurons qu'elle est bien associée au responsable
        if ($salle !== null && $salle->getResponsable() !== $this) {
            $salle->setResponsable($this);
        }

        $this->salle = $salle;
        return $this;
    }
}

