<?php

namespace App\Entity;

use App\Enum\Role;
use App\Repository\SportifRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: SportifRepository::class)]
class Sportif extends User
{
    #[ORM\OneToMany(mappedBy: 'user', targetEntity: Paiement::class)]
    private Collection $paiements;

    public function __construct()
    {
        parent::__construct();
        $this->paiements = new ArrayCollection();
        $this->setRole(Role::SPORTIF);
    }

    /**
     * @return Collection<int, Paiement>
     */
    public function getPaiements(): Collection
    {
        return $this->paiements;
    }

    public function addPaiement(Paiement $paiement): self
    {
        if (!$this->paiements->contains($paiement)) {
            $this->paiements[] = $paiement;
            $paiement->setUser($this);
        }
        return $this;
    }

    public function removePaiement(Paiement $paiement): self
    {
        if ($this->paiements->removeElement($paiement)) {
            if ($paiement->getUser() === $this) {
                $paiement->setUser(null);
            }
        }
        return $this;
    }
}