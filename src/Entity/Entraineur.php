<?php

namespace App\Entity;

use App\Repository\EntraineurRepository;
use Doctrine\ORM\Mapping as ORM;
use App\Enum\Role; // Assurez-vous que Role est bien importé

// On dit à Doctrine que Entraineur hérite de User
#[ORM\Entity(repositoryClass: EntraineurRepository::class)]
class Entraineur extends User
{
   
    // Définition de la colonne 'specialite' dans la base de données
  // Spécialité de l'entraîneur, optionnelle

    // Le constructeur définit le rôle d'Entraîneur
   
            public function __construct()
            {
                parent::__construct();
                $this->setRole(Role::ENTRAINEUR); // Sets role to 'admin', which getRoles() will transform to ROLE_ADMIN
            }
    

    

    // Méthode spécifique à l'entraîneur si nécessaire, par exemple pour ajouter des comportements ou des validations.
}
