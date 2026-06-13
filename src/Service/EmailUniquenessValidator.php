<?php

namespace App\Service;

use Doctrine\ORM\EntityManagerInterface;
use App\Repository\UserRepository;

class EmailUniquenessValidator
{
    private $userRepository;

    public function __construct(UserRepository $userRepository)
    {
        $this->userRepository = $userRepository;
    }

    /**
     * Vérifie si l'email existe déjà dans la base de données.
     *
     * @param string $email
     * @return bool
     */
    public function emailExists(string $email): bool
    {
        $existingUser = $this->userRepository->findOneBy(['email' => $email]);
        return $existingUser !== null;
    }
}
