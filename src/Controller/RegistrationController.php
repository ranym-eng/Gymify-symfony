<?php
namespace App\Controller;

use Symfony\Component\HttpFoundation\RedirectResponse;


use App\Enum\Role;
use App\Entity\Sportif;
use App\Form\SportifType;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class RegistrationController extends AbstractController
{
    #[Route('/register/sportif', name: 'app_register_sportif')]
    public function registerSportif(
        Request $request,
        EntityManagerInterface $entityManager,
        UserPasswordHasherInterface $passwordHasher,
        UserRepository $userRepository
    ): Response {
        $sportif = new Sportif();
        $sportif->setRole(Role::SPORTIF);

        $form = $this->createForm(SportifType::class, $sportif);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $existingUser = $userRepository->findOneBy(['email' => $sportif->getEmail()]);
                if ($existingUser) {
                    $this->addFlash('danger', 'Cet email est déjà utilisé.');
                    return $this->render('registration/register.html.twig', [
                        'form' => $form->createView(),
                    ]);
                }

                $plainPassword = $form->get('password')->getData();
                $hashedPassword = $passwordHasher->hashPassword($sportif, $plainPassword);
                $sportif->setPassword($hashedPassword);
                $entityManager->persist($sportif);
                $entityManager->flush();


            return new RedirectResponse($this->generateUrl('app_login'));

                $this->addFlash('success', 'Compte Sportif créé avec succès !');
                return $this->redirectToRoute('app_login');
            } catch (\Exception $e) {
                $this->addFlash('danger', 'Une erreur est survenue lors de l\'inscription.');
                return $this->render('registration/register.html.twig', [
                    'form' => $form->createView(),
                ]);
            }

        }

        return $this->render('registration/register.html.twig', [
            'form' => $form->createView(),
        ]);
    }
}